<?php
declare(strict_types=1);

// .env dosyasını yükle
function loadEnv(string $path): void
{
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) !== 2) {
            continue;
        }
        $key = trim($parts[0]);
        $value = trim($parts[1], " \t\n\r\0\x0B\"'");
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

loadEnv(__DIR__ . '/.env');

// Ortam fonksiyonu
function env(string $key, string $default = ''): string
{
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

// Veritabanı
define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'smsgateway'));
define('DB_USER', env('DB_USER', 'smsgateway'));
define('DB_PASS', env('DB_PASS', ''));

// Güvenlik
define('ADMIN_TOKEN', env('ADMIN_TOKEN', ''));
define('REGISTRATION_SECRET', env('REGISTRATION_SECRET', ''));
define('CORS_ALLOWED_ORIGINS', env('CORS_ALLOWED_ORIGINS', ''));
define('RATE_LIMIT_REQUESTS', (int) env('RATE_LIMIT_REQUESTS', '60'));
define('RATE_LIMIT_WINDOW', (int) env('RATE_LIMIT_WINDOW', '60'));

// Uygulama
define('APP_ENV', env('APP_ENV', 'production'));
define('APP_DEBUG', env('APP_DEBUG', 'false') === 'true');
define('MAX_SMS_LENGTH', (int) env('MAX_SMS_LENGTH', '918'));
define('MAX_BULK_SIZE', (int) env('MAX_BULK_SIZE', '500'));
define('SMS_RETRY_MAX', (int) env('SMS_RETRY_MAX', '3'));
define('DEVICE_OFFLINE_THRESHOLD', (int) env('DEVICE_OFFLINE_THRESHOLD', '300'));

// Timezone
date_default_timezone_set(env('TIMEZONE', 'Europe/Istanbul'));

// Veritabanı bağlantısı (Singleton + Persistent)
function getDBConnection(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                DB_HOST,
                DB_PORT,
                DB_NAME
            );
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => true,
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,
            ]);
            // Sorgu timeout (5 saniye)
            $pdo->exec('SET SESSION wait_timeout = 28800');
            $pdo->exec('SET SESSION max_execution_time = 5000');
        } catch (PDOException $e) {
            error_log('Database Connection Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Sunucu hatası'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    return $pdo;
}
