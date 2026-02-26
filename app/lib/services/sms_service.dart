import 'dart:async';
import 'dart:ui';
import 'package:flutter/material.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_background_service_android/flutter_background_service_android.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:telephony/telephony.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:convert';

class SmsService {
  final Telephony telephony = Telephony.instance;

  static Future<void> initializeBackgroundService() async {
    final service = FlutterBackgroundService();

    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'sms_gateway_channel',
      'SMS Gateway Service',
      description: 'SMS gönderme servisi için notification',
      importance: Importance.low,
    );

    final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
        FlutterLocalNotificationsPlugin();

    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    await service.configure(
      androidConfiguration: AndroidConfiguration(
        onStart: onStart,
        autoStart: false,
        isForegroundMode: true,
        notificationChannelId: 'sms_gateway_channel',
        initialNotificationTitle: 'SMS Gateway',
        initialNotificationContent: 'Servis başlatılıyor...',
        foregroundServiceNotificationId: 888,
      ),
      iosConfiguration: IosConfiguration(
        autoStart: false,
        onForeground: onStart,
        onBackground: onIosBackground,
      ),
    );
  }

  static Future<bool> startService() async {
    final service = FlutterBackgroundService();
    return await service.startService();
  }

  static Future<bool> stopService() async {
    final service = FlutterBackgroundService();
    return service.isRunning().then((running) {
      if (running) {
        service.invoke('stop');
      }
      return true;
    });
  }

  Future<bool> sendSms({
    required String phoneNumber,
    required String message,
  }) async {
    try {
      await telephony.sendSms(
        to: phoneNumber,
        message: message,
      );
      return true;
    } catch (e) {
      debugPrint('SMS gönderme hatası: $e');
      return false;
    }
  }

  Future<bool> hasPermissions() async {
    final permissions = await telephony.requestPhoneAndSmsPermissions;
    return permissions ?? false;
  }
}

// ============================================================
// Arka plan servisi başlangıç fonksiyonu
// Timer.periodic kullanarak stack overflow riski ortadan kaldırıldı
// Exponential backoff ile hata durumunda bekleme süresi artırılır
// ============================================================
@pragma('vm:entry-point')
void onStart(ServiceInstance service) async {
  DartPluginRegistrant.ensureInitialized();

  if (service is AndroidServiceInstance) {
    service.on('stop').listen((event) {
      service.stopSelf();
    });

    // İstatistik güncellemelerini dinle
    service.on('getStats').listen((event) {
      service.invoke('statsUpdate', {
        'sent': _sentCount,
        'failed': _failedCount,
        'lastActivity': _lastActivity,
      });
    });

    service.setForegroundNotificationInfo(
      title: 'SMS Gateway',
      content: 'Servis başlatıldı...',
    );
  }

  // Gelen SMS dinleme
  _listenForIncomingSms(service);

  // Timer.periodic ile polling — recursive çağrı yerine
  Timer.periodic(const Duration(seconds: 15), (timer) async {
    await _pollAndProcess(service);
  });

  // Komut polling - her 30 saniyede bir uzaktan komutları kontrol et
  Timer.periodic(const Duration(seconds: 30), (timer) async {
    await _pollAndExecuteCommands(service);
  });

  // İlk çalıştırma
  await _pollAndProcess(service);
}

/// Gelen SMS'leri dinle ve API'ye bildir
void _listenForIncomingSms(ServiceInstance service) {
  try {
    final telephony = Telephony.instance;
    telephony.listenIncomingSms(
      onNewMessage: (SmsMessage message) async {
        if (message.address == null || message.body == null) return;

        try {
          final prefs = await SharedPreferences.getInstance();
          final apiUrl = prefs.getString('api_url');
          final apiKey = prefs.getString('api_key');

          if (apiUrl == null || apiKey == null) return;

          await http
              .post(
                Uri.parse('$apiUrl?request=report-incoming'),
                headers: {
                  'X-API-Key': apiKey,
                  'Content-Type': 'application/json',
                },
                body: json.encode({
                  'sender_number': message.address,
                  'message': message.body,
                }),
              )
              .timeout(const Duration(seconds: 10));
        } catch (_) {
          // Gelen SMS bildirimi kritik değil
        }
      },
      listenInBackground: true,
    );
  } catch (_) {
    // SMS dinleme başlatılamadıysa devam et
  }
}

// Global sayaçlar
int _sentCount = 0;
int _failedCount = 0;
String _lastActivity = '';
int _consecutiveErrors = 0;
const int _maxBackoffSeconds = 300; // 5 dakika maks bekleme

Future<void> _pollAndProcess(ServiceInstance service) async {
  if (service is! AndroidServiceInstance) return;

  try {
    if (!await service.isForegroundService()) return;
  } catch (_) {
    return;
  }

  try {
    final prefs = await SharedPreferences.getInstance();
    final apiUrl = prefs.getString('api_url');
    final apiKey = prefs.getString('api_key');

    if (apiUrl == null || apiKey == null) {
      _updateNotification(service, 'SMS Gateway', 'Yapılandırma bekleniyor...');
      return;
    }

    // Exponential backoff - ardışık hata durumunda bekleme
    if (_consecutiveErrors > 0) {
      final backoffSeconds = _calculateBackoff(_consecutiveErrors);
      _updateNotification(service, 'SMS Gateway',
          'Yeniden bağlanılıyor... (${backoffSeconds}s)');
      // Timer.periodic zaten 15sn'de bir çalışıyor, ekstra bekleme gerekmiyor
      // Sadece bir kaç ardışık hata sonrası skip ederek doğal backoff sağlıyoruz
      if (_consecutiveErrors > 3) {
        // Her 3 hatadan sonra bir tur atla
        _consecutiveErrors--; // Azalt ki sonsuza kadar beklemesin
        return;
      }
    }

    // Bekleyen mesajları al
    final response = await http.get(
      Uri.parse('$apiUrl?request=pending'),
      headers: {'X-API-Key': apiKey},
    ).timeout(
      const Duration(seconds: 10),
      onTimeout: () => http.Response('{"error":"timeout"}', 408),
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body) as Map<String, dynamic>;

      if (data['error'] != null) {
        _consecutiveErrors++;
        _updateNotification(
            service, 'SMS Gateway', 'API Hatası: ${data['error']}');
        return;
      }

      // Başarılı bağlantı - hata sayacını sıfırla
      _consecutiveErrors = 0;

      final messages = data['messages'] as List? ?? [];

      _updateNotification(service, 'SMS Gateway Aktif',
          'Gönderilen: $_sentCount | Başarısız: $_failedCount | Bekleyen: ${messages.length}');

      // Mesajları gönder
      for (var msg in messages) {
        await _sendSingleMessage(service, apiUrl, apiKey, msg);
        // Mesajlar arası bekleme (SMS flood önleme)
        await Future.delayed(const Duration(milliseconds: 2000));
      }
    } else {
      _consecutiveErrors++;
      _updateNotification(
          service, 'SMS Gateway', 'API yanıt: ${response.statusCode}');
    }
  } catch (e) {
    _consecutiveErrors++;
    _updateNotification(service, 'SMS Gateway',
        'Bekleniyor... ($_sentCount gönderildi)');
  }
}

Future<void> _sendSingleMessage(ServiceInstance service, String apiUrl,
    String apiKey, Map<String, dynamic> msg) async {
  try {
    final telephony = Telephony.instance;

    await telephony.sendSms(
      to: msg['phone_number'] as String,
      message: msg['message'] as String,
    );

    // Durumu güncelle - sent
    await _updateStatus(apiUrl, apiKey, msg['id'], 'sent', null);
    _sentCount++;
    _lastActivity = DateTime.now().toIso8601String();

    _updateNotification(service, 'SMS Gateway Aktif',
        'Gönderilen: $_sentCount | Başarısız: $_failedCount');
  } catch (e) {
    _failedCount++;
    // Durumu güncelle - failed
    await _updateStatus(apiUrl, apiKey, msg['id'], 'failed', e.toString());
  }
}

Future<void> _updateStatus(String apiUrl, String apiKey, dynamic requestId,
    String status, String? errorMessage) async {
  try {
    await http
        .post(
          Uri.parse('$apiUrl?request=update-status'),
          headers: {
            'X-API-Key': apiKey,
            'Content-Type': 'application/json',
          },
          body: json.encode({
            'request_id': requestId,
            'status': status,
            if (errorMessage != null) 'error_message': errorMessage,
          }),
        )
        .timeout(const Duration(seconds: 5));
  } catch (_) {
    // Status update hatası kritik değil, devam et
  }
}

void _updateNotification(
    ServiceInstance service, String title, String content) {
  if (service is AndroidServiceInstance) {
    try {
      service.setForegroundNotificationInfo(title: title, content: content);
    } catch (_) {}
  }
}

/// Uzaktan komutları kontrol et ve yürüt
Future<void> _pollAndExecuteCommands(ServiceInstance service) async {
  try {
    final prefs = await SharedPreferences.getInstance();
    final apiUrl = prefs.getString('api_url');
    final apiKey = prefs.getString('api_key');

    if (apiUrl == null || apiKey == null) return;

    final response = await http.get(
      Uri.parse('$apiUrl?request=poll-commands'),
      headers: {'X-API-Key': apiKey},
    ).timeout(const Duration(seconds: 10));

    if (response.statusCode != 200) return;

    final data = json.decode(response.body) as Map<String, dynamic>;
    if (data['success'] != true) return;

    final commands = data['commands'] as List? ?? [];

    for (var cmd in commands) {
      final commandId = cmd['id'];
      final commandType = cmd['command_type'] as String? ?? '';
      bool success = false;
      Map<String, dynamic>? result;

      switch (commandType) {
        case 'restart_service':
          // Servisi yeniden başlat
          if (service is AndroidServiceInstance) {
            service.setForegroundNotificationInfo(
              title: 'SMS Gateway',
              content: 'Servis yeniden baslatiliyor...',
            );
          }
          _sentCount = 0;
          _failedCount = 0;
          _consecutiveErrors = 0;
          success = true;
          result = {'message': 'Service counters reset'};
          break;

        case 'sync_config':
          // Yapılandırmayı yeniden yükle
          success = true;
          result = {'message': 'Config synced', 'api_url': apiUrl};
          break;

        case 'clear_queue':
          // Yerel sayaçları sıfırla
          _sentCount = 0;
          _failedCount = 0;
          success = true;
          result = {'message': 'Local counters cleared'};
          break;

        case 'send_log':
          // Durum bilgisi gönder
          success = true;
          result = {
            'sent_count': _sentCount,
            'failed_count': _failedCount,
            'consecutive_errors': _consecutiveErrors,
            'last_activity': _lastActivity,
            'timestamp': DateTime.now().toIso8601String(),
          };
          break;

        default:
          success = false;
          result = {'message': 'Unknown command: $commandType'};
      }

      // Sonucu bildir
      try {
        await http.post(
          Uri.parse('$apiUrl?request=command-result'),
          headers: {
            'X-API-Key': apiKey,
            'Content-Type': 'application/json',
          },
          body: json.encode({
            'command_id': commandId,
            'success': success,
            'result': result,
          }),
        ).timeout(const Duration(seconds: 5));
      } catch (_) {}
    }
  } catch (_) {
    // Komut polling hatası kritik değil
  }
}

int _calculateBackoff(int errorCount) {
  // Exponential backoff: 15, 30, 60, 120, 300 saniye
  final seconds = (15 * (1 << (errorCount - 1))).clamp(15, _maxBackoffSeconds);
  return seconds;
}

@pragma('vm:entry-point')
Future<bool> onIosBackground(ServiceInstance service) async {
  return true;
}
