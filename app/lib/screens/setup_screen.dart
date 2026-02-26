import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:device_info_plus/device_info_plus.dart';
import '../services/api_service.dart';
import '../services/secure_storage_service.dart';
import 'home_screen.dart';

class SetupScreen extends StatefulWidget {
  const SetupScreen({super.key});

  @override
  State<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends State<SetupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _apiUrlController = TextEditingController();
  final _regSecretController = TextEditingController();
  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    _apiUrlController.text = 'https://';
  }

  Future<void> _requestPermissions() async {
    final statuses = await [
      Permission.sms,
      Permission.phone,
      Permission.notification,
    ].request();

    if (statuses[Permission.sms] != PermissionStatus.granted) {
      throw Exception('SMS izni gerekli');
    }
  }

  Future<String> _getDeviceId() async {
    final deviceInfo = DeviceInfoPlugin();
    final androidInfo = await deviceInfo.androidInfo;
    return androidInfo.id;
  }

  Future<Map<String, dynamic>> _getDeviceInfo() async {
    final deviceInfo = DeviceInfoPlugin();
    final info = await deviceInfo.androidInfo;
    return {
      'android_version': info.version.release,
      'sdk_version': info.version.sdkInt,
      'model': info.model,
      'brand': info.brand,
      'manufacturer': info.manufacturer,
      'hardware_id': info.id,
      'board': info.board,
      'fingerprint': info.fingerprint,
      'display_resolution': info.display,
      'app_version': '3.0.0',
    };
  }

  Future<void> _startService() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    try {
      await _requestPermissions();

      // Pil optimizasyonunu devre dışı bırakma isteği
      if (await Permission.ignoreBatteryOptimizations.isDenied) {
        await Permission.ignoreBatteryOptimizations.request();
      }

      final deviceId = await _getDeviceId();
      final deviceInfoMap = await _getDeviceInfo();
      var apiUrl = _apiUrlController.text.trim();
      final regSecret = _regSecretController.text.trim();

      // URL düzeltme
      if (!apiUrl.endsWith('/api.php')) {
        apiUrl = apiUrl.endsWith('/') ? '${apiUrl}api.php' : '$apiUrl/api.php';
      }

      // API'ye cihazı kaydet
      final apiService = ApiService(apiUrl);
      final response = await apiService.registerDevice(
        deviceId: deviceId,
        deviceName: deviceInfoMap['brand'] != null
            ? '${deviceInfoMap['brand']} ${deviceInfoMap['model']}'
            : 'Android SMS Gateway',
        registrationSecret: regSecret.isNotEmpty ? regSecret : null,
        deviceInfo: deviceInfoMap,
      );

      if (response['success'] == true) {
        final apiKey = response['api_key'] as String;

        // Güvenli depolamaya kaydet
        await SecureStorageService.setApiUrl(apiUrl);
        await SecureStorageService.setApiKey(apiKey);
        await SecureStorageService.setDeviceId(deviceId);
        if (regSecret.isNotEmpty) {
          await SecureStorageService.setRegistrationSecret(regSecret);
        }

        // SharedPreferences'a da kaydet (arka plan servisi için)
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('api_url', apiUrl);
        await prefs.setString('api_key', apiKey);
        await prefs.setString('device_id', deviceId);

        if (!mounted) return;

        Navigator.of(context).pushReplacement(
          MaterialPageRoute(
            builder: (_) => const HomeScreen(autoStart: true),
          ),
        );
      } else {
        setState(() {
          _errorMessage = response['message'] ?? 'Kayıt başarısız';
        });
      }
    } catch (e) {
      setState(() {
        _errorMessage = 'Hata: $e';
      });
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.blue.shade50,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 40),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: const BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.sms,
                    size: 80,
                    color: Colors.blue,
                  ),
                ),
                const SizedBox(height: 30),
                const Text(
                  'SMS Gateway',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 32,
                    fontWeight: FontWeight.bold,
                    color: Colors.blue,
                  ),
                ),
                const SizedBox(height: 10),
                const Text(
                  'API bağlantısı kurun ve başlayın',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 16, color: Colors.grey),
                ),
                const SizedBox(height: 40),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.blue.withValues(alpha: 0.1),
                        blurRadius: 20,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: Column(
                    children: [
                      TextFormField(
                        controller: _apiUrlController,
                        decoration: InputDecoration(
                          labelText: 'API URL',
                          hintText: 'https://sunucu.com/api.php',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          prefixIcon: const Icon(Icons.link),
                          filled: true,
                          fillColor: Colors.grey.shade50,
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'API URL gerekli';
                          }
                          if (!value.startsWith('https://')) {
                            return 'Güvenli bağlantı gerekli (https://)';
                          }
                          return null;
                        },
                        keyboardType: TextInputType.url,
                      ),
                      const SizedBox(height: 16),
                      TextFormField(
                        controller: _regSecretController,
                        decoration: InputDecoration(
                          labelText: 'Kayıt Anahtarı (Opsiyonel)',
                          hintText: 'Sunucu ayarlarındaki REGISTRATION_SECRET',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          prefixIcon: const Icon(Icons.key),
                          filled: true,
                          fillColor: Colors.grey.shade50,
                        ),
                        obscureText: true,
                        keyboardType: TextInputType.visiblePassword,
                      ),
                      const SizedBox(height: 20),
                      if (_errorMessage != null)
                        Container(
                          padding: const EdgeInsets.all(12),
                          margin: const EdgeInsets.only(bottom: 20),
                          decoration: BoxDecoration(
                            color: Colors.red.shade50,
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: Colors.red.shade200),
                          ),
                          child: Row(
                            children: [
                              Icon(Icons.error_outline,
                                  color: Colors.red.shade700),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  _errorMessage!,
                                  style:
                                      TextStyle(color: Colors.red.shade900),
                                ),
                              ),
                            ],
                          ),
                        ),
                      SizedBox(
                        width: double.infinity,
                        height: 56,
                        child: ElevatedButton(
                          onPressed: _isLoading ? null : _startService,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: Colors.blue,
                            foregroundColor: Colors.white,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                            elevation: 0,
                          ),
                          child: _isLoading
                              ? const SizedBox(
                                  height: 24,
                                  width: 24,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text(
                                  'Bağlan ve Başlat',
                                  style: TextStyle(
                                    fontSize: 18,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 30),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Row(
                        children: [
                          Icon(Icons.info_outline, color: Colors.blue),
                          SizedBox(width: 10),
                          Text(
                            'Gerekli İzinler',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 15),
                      _buildPermissionItem('SMS gönderme'),
                      _buildPermissionItem('Telefon durumu okuma'),
                      _buildPermissionItem('Bildirimler'),
                      _buildPermissionItem('Pil optimizasyonu muafiyeti'),
                      const SizedBox(height: 10),
                      Text(
                        'Bu uygulama arka planda çalışarak API\'den gelen isteklere göre otomatik SMS gönderir.',
                        style: TextStyle(
                          fontSize: 12,
                          color: Colors.grey.shade600,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildPermissionItem(String text) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          const Icon(Icons.check_circle, size: 18, color: Colors.green),
          const SizedBox(width: 10),
          Text(text),
        ],
      ),
    );
  }

  @override
  void dispose() {
    _apiUrlController.dispose();
    _regSecretController.dispose();
    super.dispose();
  }
}
