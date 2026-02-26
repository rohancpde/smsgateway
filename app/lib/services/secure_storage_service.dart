import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Hassas verileri (API Key, token) güvenli olarak saklar.
/// SharedPreferences yerine EncryptedSharedPreferences/Keystore kullanır.
class SecureStorageService {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const _keyApiKey = 'api_key';
  static const _keyApiUrl = 'api_url';
  static const _keyDeviceId = 'device_id';
  static const _keyRegistrationSecret = 'registration_secret';

  // API Key
  static Future<void> setApiKey(String key) async {
    await _storage.write(key: _keyApiKey, value: key);
  }

  static Future<String?> getApiKey() async {
    return await _storage.read(key: _keyApiKey);
  }

  // API URL
  static Future<void> setApiUrl(String url) async {
    await _storage.write(key: _keyApiUrl, value: url);
  }

  static Future<String?> getApiUrl() async {
    return await _storage.read(key: _keyApiUrl);
  }

  // Device ID
  static Future<void> setDeviceId(String id) async {
    await _storage.write(key: _keyDeviceId, value: id);
  }

  static Future<String?> getDeviceId() async {
    return await _storage.read(key: _keyDeviceId);
  }

  // Registration Secret
  static Future<void> setRegistrationSecret(String secret) async {
    await _storage.write(key: _keyRegistrationSecret, value: secret);
  }

  static Future<String?> getRegistrationSecret() async {
    return await _storage.read(key: _keyRegistrationSecret);
  }

  // Tüm verileri sil (logout)
  static Future<void> clearAll() async {
    await _storage.deleteAll();
  }

  // Kurulum tamamlanmış mı?
  static Future<bool> isSetupComplete() async {
    final apiKey = await getApiKey();
    final apiUrl = await getApiUrl();
    return apiKey != null &&
        apiKey.isNotEmpty &&
        apiUrl != null &&
        apiUrl.isNotEmpty;
  }
}
