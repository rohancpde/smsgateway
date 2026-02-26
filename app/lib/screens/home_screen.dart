import 'dart:async';
import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import '../services/sms_service.dart';
import '../services/secure_storage_service.dart';
import '../services/api_service.dart';
import 'sms_history_screen.dart';
import 'manual_sms_screen.dart';

class HomeScreen extends StatefulWidget {
  final bool autoStart;

  const HomeScreen({super.key, this.autoStart = false});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _isInitialized = false;
  bool _isServiceRunning = false;
  String _apiUrl = '';
  String _deviceId = '';
  int _sentCount = 0;
  int _failedCount = 0;
  String _connectionStatus = 'Kontrol ediliyor...';
  Timer? _statusTimer;

  @override
  void initState() {
    super.initState();
    _initialize();
  }

  Future<void> _initialize() async {
    _apiUrl = await SecureStorageService.getApiUrl() ?? '';
    _deviceId = await SecureStorageService.getDeviceId() ?? '';

    if (widget.autoStart) {
      try {
        await SmsService.initializeBackgroundService();
        await SmsService.startService();
        _isServiceRunning = true;
      } catch (e) {
        debugPrint('Service start error: $e');
      }
    } else {
      // Servis zaten çalışıyor mu kontrol et
      final service = FlutterBackgroundService();
      _isServiceRunning = await service.isRunning();
    }

    // Bağlantı testi
    await _checkConnection();

    // Periyodik durum güncellemesi (30 saniye)
    _statusTimer = Timer.periodic(const Duration(seconds: 30), (_) {
      _checkConnection();
      _refreshServiceStatus();
    });

    if (mounted) {
      setState(() {
        _isInitialized = true;
      });
    }
  }

  Future<void> _checkConnection() async {
    final apiKey = await SecureStorageService.getApiKey();
    if (_apiUrl.isEmpty || apiKey == null) return;

    final api = ApiService(_apiUrl, apiKey: apiKey);
    final connected = await api.testConnection();

    if (mounted) {
      setState(() {
        _connectionStatus = connected ? 'Bağlı' : 'Bağlantı yok';
      });
    }
  }

  Future<void> _refreshServiceStatus() async {
    final service = FlutterBackgroundService();
    final running = await service.isRunning();
    if (mounted) {
      setState(() {
        _isServiceRunning = running;
      });
    }
  }

  Future<void> _toggleService() async {
    if (_isServiceRunning) {
      await SmsService.stopService();
    } else {
      await SmsService.initializeBackgroundService();
      await SmsService.startService();
    }

    await Future.delayed(const Duration(milliseconds: 500));
    await _refreshServiceStatus();
  }

  Future<void> _logout() async {
    // Servisi durdur
    await SmsService.stopService();

    // Güvenli depolamayı temizle
    await SecureStorageService.clearAll();

    // SharedPreferences'ı temizle
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();

    if (mounted) {
      // Named route ile setup ekranına git
      Navigator.of(context).pushReplacementNamed('/setup');
    }
  }

  @override
  void dispose() {
    _statusTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_isInitialized) {
      return const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      );
    }

    return Scaffold(
      backgroundColor: Colors.blue.shade50,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        title: const Text(
          'SMS Gateway',
          style: TextStyle(color: Colors.blue, fontWeight: FontWeight.bold),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout, color: Colors.red),
            onPressed: () {
              showDialog(
                context: context,
                builder: (ctx) => AlertDialog(
                  title: const Text('Çıkış'),
                  content: const Text(
                      'Servis durdurulacak ve ayarlar silinecek. Emin misiniz?'),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx),
                      child: const Text('İptal'),
                    ),
                    TextButton(
                      onPressed: () {
                        Navigator.pop(ctx);
                        _logout();
                      },
                      child: const Text('Çıkış',
                          style: TextStyle(color: Colors.red)),
                    ),
                  ],
                ),
              );
            },
            tooltip: 'Çıkış Yap',
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              // Servis Durumu
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: _isServiceRunning ? Colors.green.shade50 : Colors.red.shade50,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: _isServiceRunning ? Colors.green.shade200 : Colors.red.shade200,
                  ),
                ),
                child: Column(
                  children: [
                    Icon(
                      _isServiceRunning ? Icons.check_circle : Icons.cancel,
                      size: 64,
                      color: _isServiceRunning ? Colors.green : Colors.red,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _isServiceRunning ? 'Servis Aktif' : 'Servis Durdu',
                      style: TextStyle(
                        fontSize: 24,
                        fontWeight: FontWeight.bold,
                        color: _isServiceRunning ? Colors.green.shade700 : Colors.red.shade700,
                      ),
                    ),
                    const SizedBox(height: 16),
                    SizedBox(
                      width: double.infinity,
                      child: ElevatedButton.icon(
                        onPressed: _toggleService,
                        icon: Icon(_isServiceRunning ? Icons.stop : Icons.play_arrow),
                        label: Text(_isServiceRunning ? 'Servisi Durdur' : 'Servisi Başlat'),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: _isServiceRunning ? Colors.red : Colors.green,
                          foregroundColor: Colors.white,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // İstatistikler
              Row(
                children: [
                  Expanded(child: _buildStatCard('Gönderilen', '$_sentCount', Colors.green)),
                  const SizedBox(width: 12),
                  Expanded(child: _buildStatCard('Başarısız', '$_failedCount', Colors.red)),
                ],
              ),

              const SizedBox(height: 16),

              // Bağlantı Bilgileri
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.grey.withValues(alpha: 0.1),
                      blurRadius: 10,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _buildInfoRow(Icons.cloud_done, 'API Bağlantısı', _connectionStatus,
                        _connectionStatus == 'Bağlı' ? Colors.green : Colors.orange),
                    const Divider(height: 24),
                    _buildInfoRow(Icons.link, 'API URL', _apiUrl, Colors.blue),
                    const Divider(height: 24),
                    _buildInfoRow(Icons.phone_android, 'Cihaz ID',
                        _deviceId.length > 20 ? '${_deviceId.substring(0, 20)}...' : _deviceId, Colors.grey),
                    const Divider(height: 24),
                    _buildInfoRow(Icons.timer, 'Kontrol Aralığı', '15 saniye', Colors.blue),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // Hızlı Aksiyonlar
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(16),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.grey.withValues(alpha: 0.1),
                      blurRadius: 10,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Hizli Aksiyonlar',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: _buildActionButton(
                            icon: Icons.send,
                            label: 'SMS Gonder',
                            color: Colors.blue,
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const ManualSmsScreen(),
                                ),
                              );
                            },
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: _buildActionButton(
                            icon: Icons.history,
                            label: 'Gecmis',
                            color: Colors.orange,
                            onTap: () {
                              Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => const SmsHistoryScreen(),
                                ),
                              );
                            },
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildActionButton({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 16),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.1),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: color.withValues(alpha: 0.2)),
        ),
        child: Column(
          children: [
            Icon(icon, color: color, size: 28),
            const SizedBox(height: 8),
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStatCard(String label, String value, Color color) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        boxShadow: [
          BoxShadow(
            color: Colors.grey.withValues(alpha: 0.1),
            blurRadius: 10,
          ),
        ],
      ),
      child: Column(
        children: [
          Text(
            value,
            style: TextStyle(
              fontSize: 32,
              fontWeight: FontWeight.bold,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(
              fontSize: 14,
              color: Colors.grey.shade600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoRow(IconData icon, String label, String value, Color color) {
    return Row(
      children: [
        Icon(icon, size: 20, color: color),
        const SizedBox(width: 12),
        Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label,
                style: TextStyle(fontSize: 12, color: Colors.grey.shade500)),
            const SizedBox(height: 2),
            Text(
              value,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w500),
            ),
          ],
        ),
      ],
    );
  }
}
