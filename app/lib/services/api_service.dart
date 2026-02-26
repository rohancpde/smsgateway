import 'dart:convert';
import 'dart:async';
import 'package:http/http.dart' as http;
import 'package:logger/logger.dart';
import '../models/sms_request.dart';

class ApiService {
  final String baseUrl;
  final String? apiKey;
  final Logger _log = Logger(printer: PrettyPrinter(methodCount: 0));

  static const _timeout = Duration(seconds: 10);
  static const _statusTimeout = Duration(seconds: 5);

  ApiService(this.baseUrl, {this.apiKey});

  Map<String, String> _getHeaders() {
    final headers = <String, String>{
      'Content-Type': 'application/json',
    };
    if (apiKey != null && apiKey!.isNotEmpty) {
      headers['X-API-Key'] = apiKey!;
    }
    return headers;
  }

  /// Cihaz kaydı
  Future<Map<String, dynamic>> registerDevice({
    required String deviceId,
    required String deviceName,
    String? phoneNumber,
    String? registrationSecret,
    Map<String, dynamic>? deviceInfo,
  }) async {
    try {
      final headers = <String, String>{
        'Content-Type': 'application/json',
      };
      if (registrationSecret != null && registrationSecret.isNotEmpty) {
        headers['X-Registration-Secret'] = registrationSecret;
      }

      final response = await http
          .post(
            Uri.parse('$baseUrl?request=register-device'),
            headers: headers,
            body: jsonEncode({
              'device_id': deviceId,
              'device_name': deviceName,
              'phone_number': phoneNumber,
              if (deviceInfo != null) 'device_info': deviceInfo,
            }),
          )
          .timeout(_timeout);

      if (response.statusCode == 200 || response.statusCode == 201) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      } else {
        final body = jsonDecode(response.body) as Map<String, dynamic>;
        return {
          'success': false,
          'message': body['error'] ?? 'Sunucu hatası: ${response.statusCode}',
        };
      }
    } on TimeoutException {
      return {'success': false, 'message': 'Bağlantı zaman aşımına uğradı'};
    } catch (e) {
      _log.e('Register device error', error: e);
      return {'success': false, 'message': 'Bağlantı hatası: $e'};
    }
  }

  /// Bekleyen mesajları al
  Future<List<SmsRequest>> getPendingMessages() async {
    try {
      final response = await http
          .get(
            Uri.parse('$baseUrl?request=pending'),
            headers: _getHeaders(),
          )
          .timeout(_timeout);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        if (data['success'] == true) {
          final List<dynamic> messages = data['messages'] ?? [];
          return messages
              .map((m) => SmsRequest.fromJson(m as Map<String, dynamic>))
              .toList();
        }
      }
      return [];
    } on TimeoutException {
      _log.w('Pending messages timeout');
      return [];
    } catch (e) {
      _log.e('Fetch messages error', error: e);
      return [];
    }
  }

  /// Mesaj durumunu güncelle
  Future<bool> updateMessageStatus({
    required int requestId,
    required String status,
    String? errorMessage,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl?request=update-status'),
            headers: _getHeaders(),
            body: jsonEncode({
              'request_id': requestId,
              'status': status,
              if (errorMessage != null) 'error_message': errorMessage,
            }),
          )
          .timeout(_statusTimeout);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        return data['success'] == true;
      }
      return false;
    } on TimeoutException {
      _log.w('Status update timeout for request $requestId');
      return false;
    } catch (e) {
      _log.e('Update status error', error: e);
      return false;
    }
  }

  /// API bağlantısını test et
  Future<bool> testConnection() async {
    try {
      final response = await http
          .get(
            Uri.parse('$baseUrl?request=pending'),
            headers: _getHeaders(),
          )
          .timeout(const Duration(seconds: 5));
      return response.statusCode == 200;
    } catch (_) {
      return false;
    }
  }

  /// Gelen SMS bildir (incoming)
  Future<Map<String, dynamic>> reportIncoming({
    required String senderNumber,
    required String message,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl?request=report-incoming'),
            headers: _getHeaders(),
            body: jsonEncode({
              'sender_number': senderNumber,
              'message': message,
            }),
          )
          .timeout(_timeout);

      if (response.statusCode == 200 || response.statusCode == 201) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
      return {'success': false, 'message': 'Hata: ${response.statusCode}'};
    } catch (e) {
      _log.e('Report incoming error', error: e);
      return {'success': false, 'message': 'Hata: $e'};
    }
  }

  /// Manuel SMS gönder (cihaz üzerinden)
  Future<Map<String, dynamic>> sendManualSms({
    required String phoneNumber,
    required String message,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl?request=send-manual-sms'),
            headers: _getHeaders(),
            body: jsonEncode({
              'phone_number': phoneNumber,
              'message': message,
            }),
          )
          .timeout(_timeout);

      if (response.statusCode == 200 || response.statusCode == 201) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
      final body = jsonDecode(response.body) as Map<String, dynamic>;
      return {'success': false, 'message': body['error'] ?? 'Hata: ${response.statusCode}'};
    } catch (e) {
      _log.e('Send manual SMS error', error: e);
      return {'success': false, 'message': 'Hata: $e'};
    }
  }

  /// Bekleyen komutları al (poll-commands)
  Future<List<Map<String, dynamic>>> pollCommands() async {
    try {
      final response = await http
          .get(
            Uri.parse('$baseUrl?request=poll-commands'),
            headers: _getHeaders(),
          )
          .timeout(_timeout);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        if (data['success'] == true) {
          final List<dynamic> commands = data['commands'] ?? [];
          return commands.cast<Map<String, dynamic>>();
        }
      }
      return [];
    } catch (e) {
      _log.e('Poll commands error', error: e);
      return [];
    }
  }

  /// Komut sonucu bildir
  Future<bool> reportCommandResult({
    required int commandId,
    required bool success,
    Map<String, dynamic>? result,
  }) async {
    try {
      final response = await http
          .post(
            Uri.parse('$baseUrl?request=command-result'),
            headers: _getHeaders(),
            body: jsonEncode({
              'command_id': commandId,
              'success': success,
              if (result != null) 'result': result,
            }),
          )
          .timeout(_timeout);

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body) as Map<String, dynamic>;
        return data['success'] == true;
      }
      return false;
    } catch (e) {
      _log.e('Report command result error', error: e);
      return false;
    }
  }

  /// SMS geçmişi (cihazın kendi mesajları - pending endpoint üzerinden)
  Future<Map<String, dynamic>> getSmsHistory() async {
    try {
      final response = await http
          .get(
            Uri.parse('$baseUrl?request=pending'),
            headers: _getHeaders(),
          )
          .timeout(_timeout);

      if (response.statusCode == 200) {
        return jsonDecode(response.body) as Map<String, dynamic>;
      }
      return {'success': false};
    } catch (e) {
      _log.e('SMS history error', error: e);
      return {'success': false};
    }
  }
}
