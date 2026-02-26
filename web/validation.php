<?php
declare(strict_types=1);

// ============================================================
// Telefon Numarası Doğrulama
// ============================================================
function validatePhoneNumber(string $phone): ?string
{
    // Boşluk ve tire temizle
    $phone = preg_replace('/[\s\-\(\)]/', '', $phone);
    if ($phone === null) {
        return null;
    }

    // +90, 0090, 90 veya 0 ile başlayan TR numaraları ve uluslararası numaralar
    // Minimum 10, maksimum 15 karakter (E.164 standardı)
    if (!preg_match('/^\+?[1-9]\d{6,14}$/', $phone)) {
        return null;
    }

    return $phone;
}

// ============================================================
// SMS Mesaj Doğrulama
// ============================================================
function validateMessage(string $message): ?string
{
    $message = trim($message);

    if ($message === '') {
        return null;
    }

    $maxLength = defined('MAX_SMS_LENGTH') ? MAX_SMS_LENGTH : 918;
    if (mb_strlen($message, 'UTF-8') > $maxLength) {
        return null;
    }

    return $message;
}

// ============================================================
// Öncelik Doğrulama (1-10)
// ============================================================
function validatePriority(mixed $priority): int
{
    $p = (int) $priority;
    return max(1, min(10, $p));
}

// ============================================================
// Status Doğrulama
// ============================================================
function validateStatus(string $status): ?string
{
    $allowed = ['pending', 'processing', 'sent', 'delivered', 'failed', 'cancelled'];
    return in_array($status, $allowed, true) ? $status : null;
}

// ============================================================
// Device ID Doğrulama
// ============================================================
function validateDeviceId(string $deviceId): ?string
{
    $deviceId = trim($deviceId);

    if ($deviceId === '' || strlen($deviceId) > 255) {
        return null;
    }

    // Sadece alfanümerik, tire, alt çizgi, nokta
    if (!preg_match('/^[a-zA-Z0-9._\-]+$/', $deviceId)) {
        return null;
    }

    return $deviceId;
}

// ============================================================
// Device Name Doğrulama
// ============================================================
function validateDeviceName(string $name): string
{
    $name = trim($name);
    return mb_substr($name, 0, 255, 'UTF-8');
}

// ============================================================
// URL Doğrulama
// ============================================================
function validateUrl(string $url): ?string
{
    $url = trim($url);

    if ($url === '' || strlen($url) > 500) {
        return null;
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'], true)) {
        return null;
    }

    return $url;
}

// ============================================================
// Kara Liste Kontrolü
// ============================================================
function isBlacklisted(string $phoneNumber): bool
{
    try {
        $db = getDBConnection();
        $stmt = $db->prepare('SELECT 1 FROM phone_blacklist WHERE phone_number = ? LIMIT 1');
        $stmt->execute([$phoneNumber]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        error_log('Blacklist check error: ' . $e->getMessage());
        return false;
    }
}

// ============================================================
// Şablon Değişken Yerleştirme
// ============================================================
function applyTemplate(string $template, array $variables): string
{
    foreach ($variables as $key => $value) {
        $template = str_replace('{{' . $key . '}}', (string) $value, $template);
    }
    return $template;
}

// ============================================================
// Toplu Doğrulama - SMS Gönderim İsteği
// ============================================================
function validateSmsRequest(array $input): array
{
    $errors = [];

    // device_id
    $deviceId = $input['device_id'] ?? '';
    if (validateDeviceId((string) $deviceId) === null) {
        $errors[] = 'Geçerli bir device_id gerekli';
    }

    // phone_number
    $phone = $input['phone_number'] ?? '';
    if (validatePhoneNumber((string) $phone) === null) {
        $errors[] = 'Geçerli bir telefon numarası gerekli (E.164 formatı)';
    }

    // message
    $message = $input['message'] ?? '';
    if (validateMessage((string) $message) === null) {
        $maxLen = defined('MAX_SMS_LENGTH') ? MAX_SMS_LENGTH : 918;
        $errors[] = "Mesaj boş olamaz ve en fazla {$maxLen} karakter olabilir";
    }

    return $errors;
}
