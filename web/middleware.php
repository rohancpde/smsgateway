<?php
declare(strict_types=1);

// ============================================================
// Güvenlik Header'ları (OWASP)
// ============================================================
function applySecurityHeaders(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
    header('Content-Security-Policy: default-src \'none\'');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
}

// ============================================================
// CORS Yönetimi (Yapılandırılabilir Origin)
// ============================================================
function handleCors(): void
{
    $allowedOrigins = array_filter(
        array_map('trim', explode(',', CORS_ALLOWED_ORIGINS))
    );

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

    if (empty($allowedOrigins) || in_array('*', $allowedOrigins, true)) {
        // Üretimde wildcard kullanılmamalı - sadece geliştirme için
        if (APP_ENV === 'development') {
            header('Access-Control-Allow-Origin: *');
        } else {
            // Üretimde origin belirtilmediyse CORS'u kapat
            if ($origin !== '' && !empty($allowedOrigins)) {
                header('Access-Control-Allow-Origin: ' . $allowedOrigins[0]);
            }
        }
    } elseif (in_array($origin, $allowedOrigins, true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }

    header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-API-Key, X-Admin-Token, X-Registration-Secret');
    header('Access-Control-Max-Age: 86400');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

// ============================================================
// Rate Limiting (DB Tabanlı - IP bazlı)
// ============================================================
function checkRateLimit(string $endpoint = 'global'): bool
{
    $ip = getClientIp();
    $maxRequests = RATE_LIMIT_REQUESTS;
    $windowSeconds = RATE_LIMIT_WINDOW;

    try {
        $db = getDBConnection();

        // Eski kayıtları temizle
        $stmt = $db->prepare(
            'DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([$windowSeconds]);

        // Mevcut sayacı kontrol et
        $stmt = $db->prepare(
            'SELECT request_count, window_start FROM rate_limits WHERE identifier = ? AND endpoint = ?'
        );
        $stmt->execute([$ip, $endpoint]);
        $record = $stmt->fetch();

        if ($record) {
            $windowStart = strtotime($record['window_start']);
            if (time() - $windowStart > $windowSeconds) {
                // Pencere süresi dolmuş, sıfırla
                $stmt = $db->prepare(
                    'UPDATE rate_limits SET request_count = 1, window_start = NOW() WHERE identifier = ? AND endpoint = ?'
                );
                $stmt->execute([$ip, $endpoint]);
                return true;
            }

            if ((int) $record['request_count'] >= $maxRequests) {
                $retryAfter = $windowSeconds - (time() - $windowStart);
                header('Retry-After: ' . max(1, $retryAfter));
                header('X-RateLimit-Limit: ' . $maxRequests);
                header('X-RateLimit-Remaining: 0');
                return false;
            }

            $stmt = $db->prepare(
                'UPDATE rate_limits SET request_count = request_count + 1 WHERE identifier = ? AND endpoint = ?'
            );
            $stmt->execute([$ip, $endpoint]);

            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: ' . ($maxRequests - (int) $record['request_count'] - 1));
        } else {
            $stmt = $db->prepare(
                'INSERT INTO rate_limits (identifier, endpoint, request_count, window_start) VALUES (?, ?, 1, NOW())'
            );
            $stmt->execute([$ip, $endpoint]);

            header('X-RateLimit-Limit: ' . $maxRequests);
            header('X-RateLimit-Remaining: ' . ($maxRequests - 1));
        }

        return true;
    } catch (PDOException $e) {
        error_log('Rate limit error: ' . $e->getMessage());
        return true; // Rate limit hatası durumunda isteğe izin ver (fail open for availability)
    }
}

// ============================================================
// API Key Authentication (SHA-256 Hash)
// ============================================================
function authenticateDevice(): array
{
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $apiKey = $headers['x-api-key'] ?? '';

    if ($apiKey === '') {
        return ['error' => 'API Key gerekli', 'code' => 401];
    }

    if (strlen($apiKey) !== 64) {
        return ['error' => 'Geçersiz API Key formatı', 'code' => 401];
    }

    $keyHash = hash('sha256', $apiKey);

    $db = getDBConnection();
    $stmt = $db->prepare(
        'SELECT device_id, is_active, deleted_at FROM devices WHERE api_key_hash = ?'
    );
    $stmt->execute([$keyHash]);
    $device = $stmt->fetch();

    if (!$device) {
        return ['error' => 'Geçersiz API Key', 'code' => 401];
    }

    if ($device['deleted_at'] !== null) {
        return ['error' => 'Cihaz silinmiş', 'code' => 410];
    }

    if ((int) $device['is_active'] !== 1) {
        return ['error' => 'Cihaz aktif değil', 'code' => 403];
    }

    return ['success' => true, 'device_id' => $device['device_id']];
}

// ============================================================
// Admin Token Authentication
// ============================================================
function authenticateAdmin(): array
{
    if (ADMIN_TOKEN === '') {
        return ['error' => 'Admin token yapılandırılmamış', 'code' => 500];
    }

    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $token = $headers['x-admin-token'] ?? '';

    if ($token === '') {
        return ['error' => 'Admin token gerekli', 'code' => 401];
    }

    if (!hash_equals(ADMIN_TOKEN, $token)) {
        auditLog('auth.admin_failed', 'anonymous', null, ['reason' => 'invalid_token']);
        return ['error' => 'Geçersiz admin token', 'code' => 401];
    }

    return ['success' => true, 'role' => 'admin'];
}

// ============================================================
// Kayıt Anahtarı Doğrulama
// ============================================================
function authenticateRegistration(): array
{
    if (REGISTRATION_SECRET === '') {
        // Kayıt anahtarı yoksa kayıt açık (geriye uyumluluk)
        return ['success' => true];
    }

    $headers = array_change_key_case(getallheaders(), CASE_LOWER);
    $input = getJsonInput();
    $secret = $headers['x-registration-secret'] ?? $input['registration_secret'] ?? '';

    if ($secret === '') {
        return ['error' => 'Kayıt anahtarı gerekli', 'code' => 401];
    }

    if (!hash_equals(REGISTRATION_SECRET, $secret)) {
        auditLog('auth.registration_failed', 'anonymous', null, ['reason' => 'invalid_secret']);
        return ['error' => 'Geçersiz kayıt anahtarı', 'code' => 401];
    }

    return ['success' => true];
}

// ============================================================
// Audit Logging
// ============================================================
function auditLog(
    string $action,
    string $actorType = 'anonymous',
    ?string $actorId = null,
    ?array $details = null
): void {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare(
            'INSERT INTO audit_logs (action, actor_type, actor_id, ip_address, user_agent, request_method, request_path, response_code, details)
             VALUES (?, ?, ?, ?, ?, ?, ?, NULL, ?)'
        );
        $stmt->execute([
            $action,
            $actorType,
            $actorId,
            getClientIp(),
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $_SERVER['REQUEST_METHOD'] ?? '',
            substr(($_SERVER['REQUEST_URI'] ?? ''), 0, 255),
            $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    } catch (PDOException $e) {
        error_log('Audit log error: ' . $e->getMessage());
    }
}

// ============================================================
// Yardımcı Fonksiyonlar
// ============================================================

function getClientIp(): string
{
    // Proxy arkasındaki gerçek IP
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        $ip = $_SERVER[$header] ?? '';
        if ($ip !== '') {
            // X-Forwarded-For birden fazla IP içerebilir
            $ip = trim(explode(',', $ip)[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function sendResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

function getJsonInput(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    // Payload boyut limiti (1MB)
    if (strlen($raw) > 1_048_576) {
        sendResponse(['error' => 'İstek boyutu çok büyük'], 413);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        sendResponse(['error' => 'Geçersiz JSON formatı'], 400);
    }

    return $data;
}

function getPaginationParams(): array
{
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    return ['page' => $page, 'limit' => $limit, 'offset' => $offset];
}
