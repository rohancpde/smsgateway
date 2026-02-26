<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/middleware.php';
require_once __DIR__ . '/validation.php';

// ============================================================
// Global Middleware
// ============================================================
applySecurityHeaders();
handleCors();

if (!checkRateLimit()) {
    auditLog('rate_limit.exceeded', 'anonymous');
    sendResponse(['error' => 'Çok fazla istek, lütfen bekleyin'], 429);
}

// ============================================================
// Router
// ============================================================
$method  = $_SERVER['REQUEST_METHOD'];
$request = $_GET['request'] ?? '';

match (true) {
    // --- Cihaz Endpoint'leri (API Key Auth) ---
    $method === 'GET'  && $request === 'pending'         => handleGetPending(),
    $method === 'POST' && $request === 'update-status'    => handleUpdateStatus(),

    // --- Kayıt (Registration Secret) ---
    $method === 'POST' && $request === 'register-device'  => handleRegisterDevice(),

    // --- SMS Endpoint'leri (Admin Auth) ---
    $method === 'POST' && $request === 'send-sms'         => handleSendSms(),
    $method === 'POST' && $request === 'send-bulk'         => handleSendBulk(),
    $method === 'GET'  && $request === 'sms-status'       => handleSmsStatus(),
    $method === 'GET'  && $request === 'sms-history'      => handleSmsHistory(),

    // --- Cihaz Yönetimi (Admin Auth) ---
    $method === 'GET'    && $request === 'devices'         => handleListDevices(),
    $method === 'POST'   && $request === 'device-toggle'   => handleToggleDevice(),
    $method === 'DELETE' && $request === 'device-delete'   => handleDeleteDevice(),
    $method === 'GET'    && $request === 'device-health'   => handleDeviceHealth(),

    // --- Şablonlar (Admin Auth) ---
    $method === 'POST' && $request === 'template-create'   => handleCreateTemplate(),
    $method === 'GET'  && $request === 'templates'         => handleListTemplates(),

    // --- Webhook'lar (Admin Auth) ---
    $method === 'POST' && $request === 'webhook-create'    => handleCreateWebhook(),
    $method === 'GET'  && $request === 'webhooks'          => handleListWebhooks(),

    // --- Kişiler (Admin Auth) ---
    $method === 'POST' && $request === 'contact-add'       => handleAddContact(),
    $method === 'GET'  && $request === 'contacts'          => handleListContacts(),

    // --- Kara Liste (Admin Auth) ---
    $method === 'POST'   && $request === 'blacklist-add'    => handleAddBlacklist(),
    $method === 'GET'    && $request === 'blacklist'        => handleListBlacklist(),
    $method === 'DELETE' && $request === 'blacklist-remove'  => handleRemoveBlacklist(),

    // --- İstatistikler (Admin Auth) ---
    $method === 'GET' && $request === 'stats'              => handleGetStats(),

    // --- Toplu Dağıtımlı SMS (Admin Auth) ---
    $method === 'POST' && $request === 'distribute-sms'    => handleDistributeSms(),

    // --- Gelen SMS (Device Auth) ---
    $method === 'POST' && $request === 'report-incoming'    => handleReportIncoming(),

    // --- Gelen SMS (Admin Auth) ---
    $method === 'GET'  && $request === 'incoming-sms'       => handleListIncoming(),

    // --- Otomatik Yanıt Kuralları (Admin Auth) ---
    $method === 'POST'   && $request === 'auto-reply-rule'  => handleCreateAutoReplyRule(),
    $method === 'GET'    && $request === 'auto-reply-rules' => handleListAutoReplyRules(),
    $method === 'DELETE' && $request === 'auto-reply-rule'  => handleDeleteAutoReplyRule(),

    // --- OTP Sistemi (Admin Auth) ---
    $method === 'POST' && $request === 'send-otp'           => handleSendOtp(),
    $method === 'POST' && $request === 'verify-otp'         => handleVerifyOtp(),

    // --- Kısa Link (Admin/Public) ---
    $method === 'POST' && $request === 'create-short-link'  => handleCreateShortLink(),
    $method === 'GET'  && $request === 'short-links'        => handleListShortLinks(),
    $method === 'GET'  && $request === 'r'                  => handleRedirect(),

    // --- CSV Import (Admin Auth) ---
    $method === 'POST' && $request === 'import-csv'         => handleImportCsv(),

    // --- API Key Rotation (Admin Auth) ---
    $method === 'POST' && $request === 'rotate-api-key'     => handleRotateApiKey(),
    $method === 'GET'  && $request === 'api-key-history'    => handleApiKeyHistory(),

    // --- Anomali Tespiti (Admin Auth) ---
    $method === 'GET'  && $request === 'anomaly-rules'      => handleListAnomalyRules(),
    $method === 'POST' && $request === 'anomaly-rule'       => handleCreateAnomalyRule(),
    $method === 'GET'  && $request === 'anomaly-events'     => handleListAnomalyEvents(),
    $method === 'POST' && $request === 'resolve-anomaly'    => handleResolveAnomaly(),

    // --- Alert Kanalları (Admin Auth) ---
    $method === 'POST'   && $request === 'alert-channel'    => handleCreateAlertChannel(),
    $method === 'GET'    && $request === 'alert-channels'   => handleListAlertChannels(),
    $method === 'DELETE' && $request === 'alert-channel'    => handleDeleteAlertChannel(),

    // --- Maliyet Takibi (Admin Auth) ---
    $method === 'GET'  && $request === 'cost-report'        => handleCostReport(),
    $method === 'POST' && $request === 'update-device-cost' => handleUpdateDeviceCost(),

    // --- Heatmap (Admin Auth) ---
    $method === 'GET'  && $request === 'heatmap-data'       => handleHeatmapData(),

    // --- Export (Admin Auth) ---
    $method === 'GET'  && $request === 'export-csv'         => handleExportCsv(),

    // --- Cihaz Skorları (Admin Auth) ---
    $method === 'GET'  && $request === 'device-scores'      => handleDeviceScores(),

    // --- Uptime Durumu (Admin Auth) ---
    $method === 'GET'  && $request === 'uptime-status'      => handleUptimeStatus(),

    // --- Log Viewer (Admin Auth) ---
    $method === 'GET'  && $request === 'audit-logs'         => handleListAuditLogs(),

    // --- Gelişmiş Yük Dengeleme (Admin Auth) ---
    $method === 'POST' && $request === 'distribute-weighted' => handleDistributeWeighted(),

    // --- SSE Stream (Admin Auth) ---
    $method === 'GET'  && $request === 'stream'             => handleStream(),

    // --- Manuel SMS (Device Auth) ---
    $method === 'POST' && $request === 'send-manual-sms'    => handleSendManualSms(),

    // --- Cihaz Uzaktan Komut (Admin Auth + Device Poll) ---
    $method === 'POST' && $request === 'device-command'     => handleCreateDeviceCommand(),
    $method === 'GET'  && $request === 'device-commands'    => handleListDeviceCommands(),
    $method === 'GET'  && $request === 'poll-commands'      => handlePollCommands(),
    $method === 'POST' && $request === 'command-result'     => handleCommandResult(),

    // --- SMS Kuyruk Görselleştirme (Admin Auth) ---
    $method === 'GET'  && $request === 'queue-stats'        => handleQueueStats(),
    $method === 'GET'  && $request === 'queue-live'         => handleQueueLive(),

    // --- Otomatik Ölçeklendirme (Admin Auth) ---
    $method === 'POST' && $request === 'autoscale-rule'     => handleCreateAutoscaleRule(),
    $method === 'GET'  && $request === 'autoscale-rules'    => handleListAutoscaleRules(),
    $method === 'DELETE' && $request === 'autoscale-rule'   => handleDeleteAutoscaleRule(),
    $method === 'GET'  && $request === 'autoscale-history'  => handleAutoscaleHistory(),
    $method === 'POST' && $request === 'autoscale-toggle'   => handleAutoscaleToggle(),

    // --- Public Durum Sayfası (No Auth) ---
    $method === 'GET'  && $request === 'public-status'      => handlePublicStatus(),

    // --- Durum Yönetimi (Admin Auth) ---
    $method === 'POST' && $request === 'update-component-status' => handleUpdateComponentStatus(),
    $method === 'POST' && $request === 'create-incident'    => handleCreateIncident(),
    $method === 'POST' && $request === 'update-incident'    => handleUpdateIncident(),
    $method === 'GET'  && $request === 'incidents'          => handleListIncidents(),
    $method === 'GET'  && $request === 'system-status'      => handleSystemStatus(),

    // --- API Versiyon Yönetimi (Admin Auth) ---
    $method === 'GET'  && $request === 'api-versions'       => handleListApiVersions(),
    $method === 'POST' && $request === 'api-version'        => handleUpdateApiVersion(),

    default => sendResponse(['error' => 'Geçersiz API isteği'], 404),
};

// ============================================================
// HANDLER: Bekleyen SMS'leri Al (Cihaz)
// ============================================================
function handleGetPending(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $deviceId = $auth['device_id'];
    $db = getDBConnection();

    // Son görülme zamanını güncelle
    $stmt = $db->prepare('UPDATE devices SET last_seen = NOW() WHERE device_id = ?');
    $stmt->execute([$deviceId]);

    // Retry edilmesi gereken failed mesajları tekrar pending yap
    $stmt = $db->prepare(
        'UPDATE sms_requests SET status = \'pending\', retry_count = retry_count
         WHERE device_id = ? AND status = \'failed\'
         AND retry_count < max_retries AND next_retry_at IS NOT NULL AND next_retry_at <= NOW()'
    );
    $stmt->execute([$deviceId]);

    // Bekleyen SMS'leri getir
    $stmt = $db->prepare(
        'SELECT id, phone_number, message, priority, scheduled_at, sim_slot, retry_count
         FROM sms_requests
         WHERE device_id = ? AND status = \'pending\'
         AND (scheduled_at IS NULL OR scheduled_at <= NOW())
         ORDER BY priority DESC, created_at ASC
         LIMIT 10'
    );
    $stmt->execute([$deviceId]);
    $messages = $stmt->fetchAll();

    // Alınan mesajları "processing" olarak işaretle (race condition önleme)
    if (!empty($messages)) {
        $ids = array_column($messages, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare(
            "UPDATE sms_requests SET status = 'processing' WHERE id IN ({$placeholders}) AND status = 'pending'"
        );
        $stmt->execute($ids);
    }

    sendResponse([
        'success'  => true,
        'count'    => count($messages),
        'messages' => $messages,
    ]);
}

// ============================================================
// HANDLER: SMS Durumunu Güncelle (Cihaz)
// ============================================================
function handleUpdateStatus(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input     = getJsonInput();
    $requestId = (int) ($input['request_id'] ?? 0);
    $status    = validateStatus((string) ($input['status'] ?? ''));
    $errorMsg  = isset($input['error_message']) ? mb_substr((string) $input['error_message'], 0, 1000, 'UTF-8') : null;

    if ($requestId <= 0) {
        sendResponse(['error' => 'Geçerli bir request_id gerekli'], 400);
    }
    if ($status === null) {
        sendResponse(['error' => 'Geçerli bir status gerekli (sent, delivered, failed)'], 400);
    }

    $db = getDBConnection();
    $db->beginTransaction();

    try {
        $updateFields = ['status = ?'];
        $params       = [$status];

        if ($status === 'sent') {
            $updateFields[] = 'sent_at = NOW()';
        } elseif ($status === 'delivered') {
            $updateFields[] = 'delivered_at = NOW()';
        } elseif ($status === 'failed') {
            // Retry mekanizması
            $updateFields[] = 'retry_count = retry_count + 1';
            $updateFields[] = 'next_retry_at = DATE_ADD(NOW(), INTERVAL POWER(2, retry_count) * 30 SECOND)';
        }

        if ($errorMsg !== null) {
            $updateFields[] = 'error_message = ?';
            $params[]       = $errorMsg;
        }

        $params[] = $requestId;
        $params[] = $auth['device_id'];

        $sql  = 'UPDATE sms_requests SET ' . implode(', ', $updateFields) . ' WHERE id = ? AND device_id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() === 0) {
            $db->rollBack();
            sendResponse(['error' => 'SMS isteği bulunamadı'], 404);
        }

        $db->commit();

        // Webhook tetikle
        triggerWebhook('sms.' . $status, [
            'request_id'    => $requestId,
            'device_id'     => $auth['device_id'],
            'status'        => $status,
            'error_message' => $errorMsg,
        ]);

        auditLog('sms.status_updated', 'device', $auth['device_id'], [
            'request_id' => $requestId,
            'status'     => $status,
        ]);

        sendResponse(['success' => true, 'message' => 'Durum güncellendi']);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Status update error: ' . $e->getMessage());
        sendResponse(['error' => 'Durum güncellenemedi'], 500);
    }
}

// ============================================================
// HANDLER: Cihaz Kaydı
// ============================================================
function handleRegisterDevice(): never
{
    // Kayıt anahtarı kontrolü
    $regAuth = authenticateRegistration();
    if (isset($regAuth['error'])) {
        sendResponse($regAuth, $regAuth['code']);
    }

    // Kayıt endpoint'i için ekstra rate limiting
    if (!checkRateLimit('register')) {
        sendResponse(['error' => 'Çok fazla kayıt denemesi'], 429);
    }

    $input      = getJsonInput();
    $deviceId   = validateDeviceId((string) ($input['device_id'] ?? ''));
    $deviceName = validateDeviceName((string) ($input['device_name'] ?? 'Bilinmeyen Cihaz'));
    $phone      = isset($input['phone_number']) ? validatePhoneNumber((string) $input['phone_number']) : null;

    // Cihaz detay bilgileri
    $deviceInfo = $input['device_info'] ?? [];
    $imei             = substr(trim((string) ($deviceInfo['imei'] ?? '')), 0, 20) ?: null;
    $imei2            = substr(trim((string) ($deviceInfo['imei2'] ?? '')), 0, 20) ?: null;
    $androidVersion   = substr(trim((string) ($deviceInfo['android_version'] ?? '')), 0, 20) ?: null;
    $sdkVersion       = isset($deviceInfo['sdk_version']) ? (int) $deviceInfo['sdk_version'] : null;
    $deviceModel      = substr(trim((string) ($deviceInfo['model'] ?? '')), 0, 100) ?: null;
    $deviceBrand      = substr(trim((string) ($deviceInfo['brand'] ?? '')), 0, 50) ?: null;
    $deviceManufacturer = substr(trim((string) ($deviceInfo['manufacturer'] ?? '')), 0, 50) ?: null;
    $hardwareId       = substr(trim((string) ($deviceInfo['hardware_id'] ?? '')), 0, 64) ?: null;
    $board            = substr(trim((string) ($deviceInfo['board'] ?? '')), 0, 50) ?: null;
    $fingerprint      = substr(trim((string) ($deviceInfo['fingerprint'] ?? '')), 0, 255) ?: null;
    $displayRes       = substr(trim((string) ($deviceInfo['display_resolution'] ?? '')), 0, 20) ?: null;
    $appVersion       = substr(trim((string) ($deviceInfo['app_version'] ?? '')), 0, 20) ?: null;

    if ($deviceId === null) {
        sendResponse(['error' => 'Geçerli bir device_id gerekli (alfanümerik, tire, alt çizgi)'], 400);
    }

    $db = getDBConnection();

    // Cihaz zaten kayıtlı mı?
    $stmt = $db->prepare('SELECT id, api_key_prefix, deleted_at FROM devices WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $existing = $stmt->fetch();

    // Cihaz bilgi güncelleme SQL parçası
    $infoUpdateSql = ', imei = ?, imei2 = ?, android_version = ?, sdk_version = ?,
        device_model = ?, device_brand = ?, device_manufacturer = ?, hardware_id = ?,
        board = ?, fingerprint = ?, display_resolution = ?, app_version = ?';
    $infoParams = [$imei, $imei2, $androidVersion, $sdkVersion, $deviceModel,
        $deviceBrand, $deviceManufacturer, $hardwareId, $board, $fingerprint, $displayRes, $appVersion];

    if ($existing) {
        if ($existing['deleted_at'] !== null) {
            // Silinmiş cihaz - yeni key ile yeniden aktif et
            $apiKey    = bin2hex(random_bytes(32));
            $keyHash   = hash('sha256', $apiKey);
            $keyPrefix = substr($apiKey, 0, 8);

            $stmt = $db->prepare(
                "UPDATE devices SET api_key_hash = ?, api_key_prefix = ?, device_name = ?,
                 phone_number = ?, is_active = 1, deleted_at = NULL, last_seen = NOW()
                 {$infoUpdateSql}
                 WHERE device_id = ?"
            );
            $stmt->execute(array_merge([$keyHash, $keyPrefix, $deviceName, $phone], $infoParams, [$deviceId]));

            auditLog('device.reactivated', 'device', $deviceId);

            sendResponse([
                'success' => true,
                'message' => 'Cihaz yeniden aktifleştirildi',
                'api_key' => $apiKey,
            ], 201);
        }

        // SECURITY FIX: CWE-200 - Mevcut API key'i döndürme, yeni key üret
        $apiKey    = bin2hex(random_bytes(32));
        $keyHash   = hash('sha256', $apiKey);
        $keyPrefix = substr($apiKey, 0, 8);

        $stmt = $db->prepare(
            "UPDATE devices SET api_key_hash = ?, api_key_prefix = ?, device_name = ?,
             phone_number = ?, last_seen = NOW()
             {$infoUpdateSql}
             WHERE device_id = ?"
        );
        $stmt->execute(array_merge([$keyHash, $keyPrefix, $deviceName, $phone], $infoParams, [$deviceId]));

        auditLog('device.key_rotated', 'device', $deviceId);

        sendResponse([
            'success' => true,
            'message' => 'Cihaz zaten kayıtlı, yeni API Key üretildi',
            'api_key' => $apiKey,
        ]);
    }

    // Yeni API key oluştur
    $apiKey    = bin2hex(random_bytes(32));
    $keyHash   = hash('sha256', $apiKey);
    $keyPrefix = substr($apiKey, 0, 8);

    $stmt = $db->prepare(
        'INSERT INTO devices (device_id, device_name, phone_number, api_key_hash, api_key_prefix, last_seen,
         imei, imei2, android_version, sdk_version, device_model, device_brand, device_manufacturer,
         hardware_id, board, fingerprint, display_resolution, app_version)
         VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute(array_merge([$deviceId, $deviceName, $phone, $keyHash, $keyPrefix], $infoParams));

    auditLog('device.registered', 'device', $deviceId);

    sendResponse([
        'success' => true,
        'message' => 'Cihaz kaydedildi',
        'api_key' => $apiKey,
    ], 201);
}

// ============================================================
// HANDLER: SMS Gönderme Talebi (Admin Auth)
// ============================================================
function handleSendSms(): never
{
    // SECURITY FIX: CWE-306 - Authentication eklendi
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input    = getJsonInput();
    $errors   = validateSmsRequest($input);

    if (!empty($errors)) {
        sendResponse(['error' => 'Doğrulama hatası', 'details' => $errors], 400);
    }

    $deviceId = validateDeviceId((string) $input['device_id']);
    $phone    = validatePhoneNumber((string) $input['phone_number']);
    $message  = validateMessage((string) $input['message']);
    $priority = validatePriority($input['priority'] ?? 5);
    $simSlot  = max(0, min(2, (int) ($input['sim_slot'] ?? 0)));
    $schedAt  = isset($input['scheduled_at']) ? (string) $input['scheduled_at'] : null;
    $webhook  = isset($input['webhook_url']) ? validateUrl((string) $input['webhook_url']) : null;

    // Template desteği
    if (isset($input['template_id'])) {
        $templateResult = resolveTemplate((int) $input['template_id'], $input['variables'] ?? []);
        if ($templateResult !== null) {
            $message = $templateResult;
        }
    }

    // Kara liste kontrolü
    if (isBlacklisted($phone)) {
        sendResponse(['error' => 'Bu numara kara listede'], 403);
    }

    $db = getDBConnection();

    // Cihaz kontrolü
    $stmt = $db->prepare('SELECT is_active, deleted_at FROM devices WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        sendResponse(['error' => 'Cihaz bulunamadı'], 404);
    }
    if ($device['deleted_at'] !== null) {
        sendResponse(['error' => 'Cihaz silinmiş'], 410);
    }
    if ((int) $device['is_active'] !== 1) {
        sendResponse(['error' => 'Cihaz aktif değil'], 403);
    }

    // SMS isteğini ekle
    $stmt = $db->prepare(
        'INSERT INTO sms_requests (device_id, phone_number, message, priority, sim_slot, scheduled_at, webhook_url, max_retries)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$deviceId, $phone, $message, $priority, $simSlot, $schedAt, $webhook, SMS_RETRY_MAX]);
    $requestId = (int) $db->lastInsertId();

    auditLog('sms.created', 'admin', null, [
        'request_id' => $requestId,
        'device_id'  => $deviceId,
        'phone'      => maskPhone($phone),
    ]);

    sendResponse([
        'success'    => true,
        'message'    => 'SMS kuyruğa eklendi',
        'request_id' => $requestId,
    ], 201);
}

// ============================================================
// HANDLER: Toplu SMS Gönderimi (Admin Auth)
// ============================================================
function handleSendBulk(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input    = getJsonInput();
    $deviceId = validateDeviceId((string) ($input['device_id'] ?? ''));
    $messages = $input['messages'] ?? [];

    if ($deviceId === null) {
        sendResponse(['error' => 'Geçerli bir device_id gerekli'], 400);
    }

    if (!is_array($messages) || empty($messages)) {
        sendResponse(['error' => 'messages dizisi gerekli'], 400);
    }

    if (count($messages) > MAX_BULK_SIZE) {
        sendResponse(['error' => 'Tek seferde en fazla ' . MAX_BULK_SIZE . ' SMS gönderilebilir'], 400);
    }

    // Cihaz kontrolü
    $db = getDBConnection();
    $stmt = $db->prepare('SELECT is_active, deleted_at FROM devices WHERE device_id = ?');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device || $device['deleted_at'] !== null || (int) $device['is_active'] !== 1) {
        sendResponse(['error' => 'Cihaz bulunamadı veya aktif değil'], 404);
    }

    $batchId   = sprintf('%s-%s', date('Ymd-His'), bin2hex(random_bytes(4)));
    $inserted  = 0;
    $skipped   = 0;
    $ids       = [];

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO sms_requests (device_id, phone_number, message, priority, batch_id, max_retries)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($messages as $msg) {
            $phone   = validatePhoneNumber((string) ($msg['phone_number'] ?? ''));
            $text    = validateMessage((string) ($msg['message'] ?? ''));
            $prio    = validatePriority($msg['priority'] ?? 5);

            if ($phone === null || $text === null) {
                $skipped++;
                continue;
            }

            if (isBlacklisted($phone)) {
                $skipped++;
                continue;
            }

            $stmt->execute([$deviceId, $phone, $text, $prio, $batchId, SMS_RETRY_MAX]);
            $ids[] = (int) $db->lastInsertId();
            $inserted++;
        }

        $db->commit();

        auditLog('sms.bulk_created', 'admin', null, [
            'batch_id' => $batchId,
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ]);

        sendResponse([
            'success'   => true,
            'batch_id'  => $batchId,
            'inserted'  => $inserted,
            'skipped'   => $skipped,
            'ids'       => $ids,
        ], 201);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Bulk insert error: ' . $e->getMessage());
        sendResponse(['error' => 'Toplu ekleme başarısız'], 500);
    }
}

// ============================================================
// HANDLER: SMS Durumu Sorgula (Admin Auth)
// ============================================================
function handleSmsStatus(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(['error' => 'Geçerli bir id parametresi gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'SELECT id, device_id, phone_number, message, status, priority, scheduled_at,
                sent_at, delivered_at, error_message, retry_count, batch_id, created_at
         FROM sms_requests WHERE id = ?'
    );
    $stmt->execute([$id]);
    $sms = $stmt->fetch();

    if (!$sms) {
        sendResponse(['error' => 'SMS bulunamadı'], 404);
    }

    sendResponse(['success' => true, 'sms' => $sms]);
}

// ============================================================
// HANDLER: SMS Geçmişi (Admin Auth, Paginated)
// ============================================================
function handleSmsHistory(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $pagination = getPaginationParams();
    $deviceId   = isset($_GET['device_id']) ? validateDeviceId((string) $_GET['device_id']) : null;
    $status     = isset($_GET['status']) ? validateStatus((string) $_GET['status']) : null;
    $batchId    = isset($_GET['batch_id']) ? trim((string) $_GET['batch_id']) : null;

    $where  = [];
    $params = [];

    if ($deviceId !== null) {
        $where[]  = 'device_id = ?';
        $params[] = $deviceId;
    }
    if ($status !== null) {
        $where[]  = 'status = ?';
        $params[] = $status;
    }
    if ($batchId !== null && $batchId !== '') {
        $where[]  = 'batch_id = ?';
        $params[] = $batchId;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $db = getDBConnection();

    // Toplam sayı
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM sms_requests {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    // Sayfalı veriler
    $stmt = $db->prepare(
        "SELECT id, device_id, phone_number, status, priority, batch_id, sent_at, created_at
         FROM sms_requests {$whereClause}
         ORDER BY created_at DESC
         LIMIT ? OFFSET ?"
    );
    $allParams   = array_merge($params, [$pagination['limit'], $pagination['offset']]);
    $stmt->execute($allParams);
    $messages = $stmt->fetchAll();

    sendResponse([
        'success'    => true,
        'total'      => $total,
        'page'       => $pagination['page'],
        'limit'      => $pagination['limit'],
        'total_pages' => (int) ceil($total / $pagination['limit']),
        'messages'   => $messages,
    ]);
}

// ============================================================
// HANDLER: Cihaz Listesi (Admin Auth)
// ============================================================
function handleListDevices(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'SELECT d.id, d.device_id, d.device_name, d.phone_number, d.api_key_prefix,
                d.is_active, d.sim_slot, d.last_seen, d.created_at,
                d.performance_score, d.total_sent, d.total_failed, d.weight, d.cost_per_sms,
                d.imei, d.imei2, d.android_version, d.sdk_version, d.device_model,
                d.device_brand, d.device_manufacturer, d.hardware_id, d.board,
                d.fingerprint, d.display_resolution, d.app_version,
                (SELECT COUNT(*) FROM sms_requests WHERE device_id = d.device_id AND status = \'pending\') as pending_count,
                (SELECT COUNT(*) FROM sms_requests WHERE device_id = d.device_id AND status = \'sent\') as sent_count,
                (SELECT COUNT(*) FROM sms_requests WHERE device_id = d.device_id AND status = \'failed\') as failed_count
         FROM devices d
         WHERE d.deleted_at IS NULL
         ORDER BY d.last_seen DESC'
    );
    $stmt->execute();
    $devices = $stmt->fetchAll();

    sendResponse(['success' => true, 'devices' => $devices]);
}

// ============================================================
// HANDLER: Cihaz Aktif/Pasif (Admin Auth)
// ============================================================
function handleToggleDevice(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input    = getJsonInput();
    $deviceId = validateDeviceId((string) ($input['device_id'] ?? ''));
    $active   = isset($input['is_active']) ? ((bool) $input['is_active'] ? 1 : 0) : null;

    if ($deviceId === null) {
        sendResponse(['error' => 'Geçerli bir device_id gerekli'], 400);
    }

    $db = getDBConnection();

    if ($active !== null) {
        $stmt = $db->prepare('UPDATE devices SET is_active = ? WHERE device_id = ? AND deleted_at IS NULL');
        $stmt->execute([$active, $deviceId]);
    } else {
        // Toggle
        $stmt = $db->prepare('UPDATE devices SET is_active = NOT is_active WHERE device_id = ? AND deleted_at IS NULL');
        $stmt->execute([$deviceId]);
    }

    auditLog('device.toggled', 'admin', $deviceId);

    sendResponse(['success' => true, 'message' => 'Cihaz durumu güncellendi']);
}

// ============================================================
// HANDLER: Cihaz Sil - Soft Delete (Admin Auth)
// ============================================================
function handleDeleteDevice(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $deviceId = validateDeviceId((string) ($_GET['device_id'] ?? ''));

    if ($deviceId === null) {
        sendResponse(['error' => 'Geçerli bir device_id gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('UPDATE devices SET deleted_at = NOW(), is_active = 0 WHERE device_id = ? AND deleted_at IS NULL');
    $stmt->execute([$deviceId]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Cihaz bulunamadı'], 404);
    }

    // Bekleyen SMS'leri iptal et
    $stmt = $db->prepare('UPDATE sms_requests SET status = \'cancelled\' WHERE device_id = ? AND status IN (\'pending\', \'processing\')');
    $stmt->execute([$deviceId]);

    auditLog('device.deleted', 'admin', $deviceId);

    sendResponse(['success' => true, 'message' => 'Cihaz silindi']);
}

// ============================================================
// HANDLER: Cihaz Sağlık Durumu (Admin Auth)
// ============================================================
function handleDeviceHealth(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $threshold = DEVICE_OFFLINE_THRESHOLD;

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'SELECT device_id, device_name, last_seen, is_active,
                TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_since_seen,
                CASE
                    WHEN last_seen IS NULL THEN \'never_connected\'
                    WHEN TIMESTAMPDIFF(SECOND, last_seen, NOW()) > ? THEN \'offline\'
                    ELSE \'online\'
                END as health_status
         FROM devices
         WHERE deleted_at IS NULL
         ORDER BY last_seen DESC'
    );
    $stmt->execute([$threshold]);
    $devices = $stmt->fetchAll();

    $online  = 0;
    $offline = 0;
    foreach ($devices as $d) {
        if ($d['health_status'] === 'online') {
            $online++;
        } else {
            $offline++;
        }
    }

    // Offline cihazlar için webhook tetikle
    foreach ($devices as $d) {
        if ($d['health_status'] === 'offline' && (int) $d['is_active'] === 1) {
            triggerWebhook('device.offline', [
                'device_id'       => $d['device_id'],
                'last_seen'       => $d['last_seen'],
                'seconds_offline' => $d['seconds_since_seen'],
            ]);
        }
    }

    sendResponse([
        'success'         => true,
        'online_count'    => $online,
        'offline_count'   => $offline,
        'threshold_secs'  => $threshold,
        'devices'         => $devices,
    ]);
}

// ============================================================
// HANDLER: Şablon Oluştur (Admin Auth)
// ============================================================
function handleCreateTemplate(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input     = getJsonInput();
    $name      = trim((string) ($input['name'] ?? ''));
    $content   = trim((string) ($input['content'] ?? ''));
    $variables = $input['variables'] ?? [];

    if ($name === '' || strlen($name) > 100) {
        sendResponse(['error' => 'Şablon adı gerekli (maks 100 karakter)'], 400);
    }
    if ($content === '') {
        sendResponse(['error' => 'Şablon içeriği gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'INSERT INTO sms_templates (name, content, variables) VALUES (?, ?, ?)'
    );
    $stmt->execute([$name, $content, json_encode($variables, JSON_UNESCAPED_UNICODE)]);

    sendResponse([
        'success'     => true,
        'template_id' => (int) $db->lastInsertId(),
        'message'     => 'Şablon oluşturuldu',
    ], 201);
}

// ============================================================
// HANDLER: Şablon Listesi (Admin Auth)
// ============================================================
function handleListTemplates(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, name, content, variables, is_active, usage_count, created_at FROM sms_templates ORDER BY name');
    $stmt->execute();

    sendResponse(['success' => true, 'templates' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Webhook Oluştur (Admin Auth)
// ============================================================
function handleCreateWebhook(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input  = getJsonInput();
    $url    = validateUrl((string) ($input['url'] ?? ''));
    $events = $input['events'] ?? [];

    if ($url === null) {
        sendResponse(['error' => 'Geçerli bir URL gerekli'], 400);
    }
    if (!is_array($events) || empty($events)) {
        sendResponse(['error' => 'En az bir event türü gerekli'], 400);
    }

    $allowedEvents = ['sms.sent', 'sms.failed', 'sms.delivered', 'device.offline', 'device.registered'];
    $validEvents   = array_intersect($events, $allowedEvents);
    if (empty($validEvents)) {
        sendResponse(['error' => 'Geçerli event türleri: ' . implode(', ', $allowedEvents)], 400);
    }

    $secret = bin2hex(random_bytes(16));

    $db   = getDBConnection();
    $stmt = $db->prepare('INSERT INTO webhooks (url, events, secret) VALUES (?, ?, ?)');
    $stmt->execute([$url, json_encode(array_values($validEvents)), $secret]);

    sendResponse([
        'success'    => true,
        'webhook_id' => (int) $db->lastInsertId(),
        'secret'     => $secret,
        'message'    => 'Webhook oluşturuldu',
    ], 201);
}

// ============================================================
// HANDLER: Webhook Listesi (Admin Auth)
// ============================================================
function handleListWebhooks(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, url, events, is_active, last_triggered_at, failure_count, created_at FROM webhooks ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'webhooks' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Kişi Ekle (Admin Auth)
// ============================================================
function handleAddContact(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();
    $name  = trim((string) ($input['name'] ?? ''));
    $phone = validatePhoneNumber((string) ($input['phone_number'] ?? ''));
    $group = isset($input['group_name']) ? mb_substr(trim((string) $input['group_name']), 0, 100, 'UTF-8') : null;

    if ($name === '' || strlen($name) > 255) {
        sendResponse(['error' => 'Kişi adı gerekli'], 400);
    }
    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('INSERT INTO contacts (name, phone_number, group_name) VALUES (?, ?, ?)');
    $stmt->execute([$name, $phone, $group]);

    sendResponse([
        'success'    => true,
        'contact_id' => (int) $db->lastInsertId(),
        'message'    => 'Kişi eklendi',
    ], 201);
}

// ============================================================
// HANDLER: Kişi Listesi (Admin Auth)
// ============================================================
function handleListContacts(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $pagination = getPaginationParams();
    $group      = isset($_GET['group']) ? trim((string) $_GET['group']) : null;

    $db = getDBConnection();

    if ($group !== null && $group !== '') {
        $stmt = $db->prepare(
            'SELECT id, name, phone_number, group_name, created_at FROM contacts WHERE group_name = ? ORDER BY name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$group, $pagination['limit'], $pagination['offset']]);
    } else {
        $stmt = $db->prepare(
            'SELECT id, name, phone_number, group_name, created_at FROM contacts ORDER BY name LIMIT ? OFFSET ?'
        );
        $stmt->execute([$pagination['limit'], $pagination['offset']]);
    }

    sendResponse(['success' => true, 'contacts' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Kara Listeye Ekle (Admin Auth)
// ============================================================
function handleAddBlacklist(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input  = getJsonInput();
    $phone  = validatePhoneNumber((string) ($input['phone_number'] ?? ''));
    $reason = isset($input['reason']) ? mb_substr(trim((string) $input['reason']), 0, 255, 'UTF-8') : null;

    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }

    $db = getDBConnection();
    try {
        $stmt = $db->prepare('INSERT INTO phone_blacklist (phone_number, reason) VALUES (?, ?)');
        $stmt->execute([$phone, $reason]);
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            sendResponse(['error' => 'Bu numara zaten kara listede'], 409);
        }
        throw $e;
    }

    // Bu numaraya bekleyen SMS'leri iptal et
    $stmt = $db->prepare('UPDATE sms_requests SET status = \'cancelled\' WHERE phone_number = ? AND status IN (\'pending\', \'processing\')');
    $stmt->execute([$phone]);

    auditLog('blacklist.added', 'admin', null, ['phone' => maskPhone($phone)]);

    sendResponse(['success' => true, 'message' => 'Numara kara listeye eklendi'], 201);
}

// ============================================================
// HANDLER: Kara Liste Görüntüle (Admin Auth)
// ============================================================
function handleListBlacklist(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, phone_number, reason, created_at FROM phone_blacklist ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'blacklist' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Kara Listeden Kaldır (Admin Auth)
// ============================================================
function handleRemoveBlacklist(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $phone = validatePhoneNumber((string) ($_GET['phone_number'] ?? ''));
    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('DELETE FROM phone_blacklist WHERE phone_number = ?');
    $stmt->execute([$phone]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Numara kara listede bulunamadı'], 404);
    }

    auditLog('blacklist.removed', 'admin', null, ['phone' => maskPhone($phone)]);

    sendResponse(['success' => true, 'message' => 'Numara kara listeden kaldırıldı']);
}

// ============================================================
// HANDLER: İstatistikler (Admin Auth)
// ============================================================
function handleGetStats(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db = getDBConnection();

    // Genel istatistikler
    $stmt = $db->prepare(
        'SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = \'processing\' THEN 1 ELSE 0 END) as processing,
            SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = \'delivered\' THEN 1 ELSE 0 END) as delivered,
            SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = \'cancelled\' THEN 1 ELSE 0 END) as cancelled
         FROM sms_requests'
    );
    $stmt->execute();
    $overall = $stmt->fetch();

    // Bugünkü istatistikler
    $stmt = $db->prepare(
        'SELECT
            COUNT(*) as total,
            SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
         FROM sms_requests
         WHERE DATE(created_at) = CURDATE()'
    );
    $stmt->execute();
    $today = $stmt->fetch();

    // Son 7 gün trend
    $stmt = $db->prepare(
        'SELECT DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
         FROM sms_requests
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
         GROUP BY DATE(created_at)
         ORDER BY date'
    );
    $stmt->execute();
    $weekly = $stmt->fetchAll();

    // Cihaz bazlı istatistikler
    $stmt = $db->prepare(
        'SELECT d.device_id, d.device_name, d.is_active, d.last_seen,
                COUNT(s.id) as total_sms,
                SUM(CASE WHEN s.status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN s.status = \'failed\' THEN 1 ELSE 0 END) as failed
         FROM devices d
         LEFT JOIN sms_requests s ON d.device_id = s.device_id
         WHERE d.deleted_at IS NULL
         GROUP BY d.device_id, d.device_name, d.is_active, d.last_seen
         ORDER BY total_sms DESC'
    );
    $stmt->execute();
    $perDevice = $stmt->fetchAll();

    // Aktif cihaz sayısı
    $stmt = $db->prepare('SELECT COUNT(*) as count FROM devices WHERE deleted_at IS NULL AND is_active = 1');
    $stmt->execute();
    $activeDevices = (int) $stmt->fetch()['count'];

    sendResponse([
        'success'        => true,
        'overall'        => $overall,
        'today'          => $today,
        'weekly_trend'   => $weekly,
        'per_device'     => $perDevice,
        'active_devices' => $activeDevices,
    ]);
}

// ============================================================
// HANDLER: Toplu Dağıtımlı SMS - Numaraları cihazlara eşit dağıt (Admin Auth)
// ============================================================
function handleDistributeSms(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();

    // Cihaz ID listesi
    $deviceIds = $input['device_ids'] ?? [];
    if (!is_array($deviceIds) || empty($deviceIds)) {
        sendResponse(['error' => 'En az bir device_id gerekli (device_ids dizisi)'], 400);
    }

    // Telefon numarası listesi (alt alta veya dizi)
    $rawNumbers = $input['phone_numbers'] ?? '';
    $phoneNumbers = [];
    if (is_string($rawNumbers)) {
        // Alt alta yazılmış numaraları ayır (newline, virgül, noktalı virgül)
        $lines = preg_split('/[\r\n,;]+/', $rawNumbers);
        foreach ($lines as $line) {
            $num = validatePhoneNumber(trim($line));
            if ($num !== null) {
                $phoneNumbers[] = $num;
            }
        }
    } elseif (is_array($rawNumbers)) {
        foreach ($rawNumbers as $num) {
            $validated = validatePhoneNumber(trim((string) $num));
            if ($validated !== null) {
                $phoneNumbers[] = $validated;
            }
        }
    }

    // Tekrarlayan numaraları temizle
    $phoneNumbers = array_values(array_unique($phoneNumbers));

    if (empty($phoneNumbers)) {
        sendResponse(['error' => 'En az bir geçerli telefon numarası gerekli'], 400);
    }

    // Mesaj — düz metin veya şablondan
    $message  = null;
    $priority = validatePriority($input['priority'] ?? 5);

    if (isset($input['template_id'])) {
        $templateResult = resolveTemplate((int) $input['template_id'], $input['variables'] ?? []);
        if ($templateResult === null) {
            sendResponse(['error' => 'Şablon bulunamadı veya aktif değil'], 404);
        }
        $message = $templateResult;
    } else {
        $message = validateMessage((string) ($input['message'] ?? ''));
    }

    if ($message === null) {
        sendResponse(['error' => 'Mesaj veya geçerli bir template_id gerekli'], 400);
    }

    // Cihazları doğrula
    $db = getDBConnection();
    $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
    $stmt = $db->prepare(
        "SELECT device_id FROM devices WHERE device_id IN ({$placeholders}) AND is_active = 1 AND deleted_at IS NULL"
    );
    $stmt->execute($deviceIds);
    $activeDevices = array_column($stmt->fetchAll(), 'device_id');

    if (empty($activeDevices)) {
        sendResponse(['error' => 'Seçilen cihazlardan hiçbiri aktif değil'], 400);
    }

    // Kara listedeki numaraları filtrele
    $cleanNumbers = [];
    $blacklisted  = 0;
    foreach ($phoneNumbers as $phone) {
        if (isBlacklisted($phone)) {
            $blacklisted++;
        } else {
            $cleanNumbers[] = $phone;
        }
    }

    if (empty($cleanNumbers)) {
        sendResponse(['error' => 'Tüm numaralar kara listede'], 400);
    }

    // Round-robin dağıtım — numaraları cihazlara eşit dağıt
    $batchId       = sprintf('%s-%s', date('Ymd-His'), bin2hex(random_bytes(4)));
    $deviceCount   = count($activeDevices);
    $inserted      = 0;
    $distribution  = []; // cihaz başına kaç SMS

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO sms_requests (device_id, phone_number, message, priority, batch_id, max_retries)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($cleanNumbers as $index => $phone) {
            // Round-robin: her numara sırayla bir cihaza atanır
            $assignedDevice = $activeDevices[$index % $deviceCount];

            $stmt->execute([$assignedDevice, $phone, $message, $priority, $batchId, SMS_RETRY_MAX]);
            $inserted++;

            if (!isset($distribution[$assignedDevice])) {
                $distribution[$assignedDevice] = 0;
            }
            $distribution[$assignedDevice]++;
        }

        $db->commit();

        auditLog('sms.distributed', 'admin', null, [
            'batch_id'     => $batchId,
            'total'        => $inserted,
            'blacklisted'  => $blacklisted,
            'devices'      => $distribution,
        ]);

        sendResponse([
            'success'       => true,
            'batch_id'      => $batchId,
            'total_numbers' => count($phoneNumbers),
            'inserted'      => $inserted,
            'blacklisted'   => $blacklisted,
            'device_count'  => $deviceCount,
            'distribution'  => $distribution,
        ], 201);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Distribute SMS error: ' . $e->getMessage());
        sendResponse(['error' => 'Dağıtım başarısız'], 500);
    }
}

// ============================================================
// Yardımcı: Telefon numarası maskeleme
// ============================================================
function maskPhone(string $phone): string
{
    $len = strlen($phone);
    if ($len <= 4) {
        return '****';
    }
    return substr($phone, 0, 3) . str_repeat('*', $len - 6) . substr($phone, -3);
}

// ============================================================
// Yardımcı: Şablon çözümleme
// ============================================================
function resolveTemplate(int $templateId, array $variables): ?string
{
    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT content FROM sms_templates WHERE id = ? AND is_active = 1');
    $stmt->execute([$templateId]);
    $template = $stmt->fetch();

    if (!$template) {
        return null;
    }

    // Kullanım sayacını artır
    $stmt = $db->prepare('UPDATE sms_templates SET usage_count = usage_count + 1 WHERE id = ?');
    $stmt->execute([$templateId]);

    return applyTemplate($template['content'], $variables);
}

// ============================================================
// HANDLER: Gelen SMS Bildir (Device Auth)
// ============================================================
function handleReportIncoming(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input        = getJsonInput();
    $senderNumber = validatePhoneNumber((string) ($input['sender_number'] ?? ''));
    $message      = trim((string) ($input['message'] ?? ''));

    if ($senderNumber === null) {
        sendResponse(['error' => 'Geçerli bir sender_number gerekli'], 400);
    }
    if ($message === '') {
        sendResponse(['error' => 'Mesaj boş olamaz'], 400);
    }

    $db = getDBConnection();

    $stmt = $db->prepare(
        'INSERT INTO incoming_sms (device_id, sender_number, message) VALUES (?, ?, ?)'
    );
    $stmt->execute([$auth['device_id'], $senderNumber, mb_substr($message, 0, 5000, 'UTF-8')]);
    $incomingId = (int) $db->lastInsertId();

    // Otomatik yanıt kontrolü
    $autoReply = checkAutoReply($message);
    $replyMessage = null;

    if ($autoReply !== null) {
        $replyMessage = $autoReply['reply_message'];

        // Otomatik yanıt SMS'i kuyruğa ekle
        $stmt = $db->prepare(
            'INSERT INTO sms_requests (device_id, phone_number, message, priority, max_retries) VALUES (?, ?, ?, 8, ?)'
        );
        $stmt->execute([$auth['device_id'], $senderNumber, $replyMessage, SMS_RETRY_MAX]);

        // İşaretle
        $stmt = $db->prepare('UPDATE incoming_sms SET auto_reply_sent = 1 WHERE id = ?');
        $stmt->execute([$incomingId]);

        // Kullanım sayacı
        $stmt = $db->prepare('UPDATE auto_reply_rules SET usage_count = usage_count + 1 WHERE id = ?');
        $stmt->execute([$autoReply['id']]);
    }

    triggerWebhook('sms.incoming', [
        'device_id'     => $auth['device_id'],
        'sender_number' => maskPhone($senderNumber),
        'message_length' => mb_strlen($message, 'UTF-8'),
        'auto_replied'  => $autoReply !== null,
    ]);

    sendResponse([
        'success'       => true,
        'incoming_id'   => $incomingId,
        'auto_replied'  => $autoReply !== null,
        'reply_message' => $replyMessage,
    ], 201);
}

function checkAutoReply(string $message): ?array
{
    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, keyword, match_type, reply_message FROM auto_reply_rules WHERE is_active = 1');
    $stmt->execute();
    $rules = $stmt->fetchAll();

    $msgLower = mb_strtolower($message, 'UTF-8');

    foreach ($rules as $rule) {
        $keyword = mb_strtolower($rule['keyword'], 'UTF-8');
        $matched = match ($rule['match_type']) {
            'exact'       => $msgLower === $keyword,
            'contains'    => str_contains($msgLower, $keyword),
            'starts_with' => str_starts_with($msgLower, $keyword),
            'regex'       => @preg_match('/' . $rule['keyword'] . '/iu', $message) === 1,
            default       => false,
        };
        if ($matched) {
            return $rule;
        }
    }
    return null;
}

// ============================================================
// HANDLER: Gelen SMS Listesi (Admin Auth)
// ============================================================
function handleListIncoming(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $pagination = getPaginationParams();
    $deviceId   = isset($_GET['device_id']) ? validateDeviceId((string) $_GET['device_id']) : null;
    $unread     = isset($_GET['unread']) ? ((string) $_GET['unread'] === '1') : false;

    $where  = [];
    $params = [];

    if ($deviceId !== null) {
        $where[]  = 'device_id = ?';
        $params[] = $deviceId;
    }
    if ($unread) {
        $where[] = 'is_read = 0';
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM incoming_sms {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        "SELECT id, device_id, sender_number, message, received_at, is_read, auto_reply_sent, created_at
         FROM incoming_sms {$whereClause}
         ORDER BY received_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($params, [$pagination['limit'], $pagination['offset']]));

    sendResponse([
        'success'     => true,
        'total'       => $total,
        'page'        => $pagination['page'],
        'limit'       => $pagination['limit'],
        'total_pages' => (int) ceil($total / $pagination['limit']),
        'messages'    => $stmt->fetchAll(),
    ]);
}

// ============================================================
// HANDLER: Otomatik Yanıt Kuralı Oluştur (Admin Auth)
// ============================================================
function handleCreateAutoReplyRule(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input     = getJsonInput();
    $keyword   = trim((string) ($input['keyword'] ?? ''));
    $matchType = (string) ($input['match_type'] ?? 'contains');
    $reply     = trim((string) ($input['reply_message'] ?? ''));

    if ($keyword === '' || strlen($keyword) > 100) {
        sendResponse(['error' => 'Anahtar kelime gerekli (maks 100 karakter)'], 400);
    }
    if (!in_array($matchType, ['exact', 'contains', 'starts_with', 'regex'], true)) {
        sendResponse(['error' => 'Geçersiz match_type'], 400);
    }
    if ($reply === '') {
        sendResponse(['error' => 'Yanıt mesajı gerekli'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare('INSERT INTO auto_reply_rules (keyword, match_type, reply_message) VALUES (?, ?, ?)');
    $stmt->execute([$keyword, $matchType, $reply]);

    sendResponse(['success' => true, 'rule_id' => (int) $db->lastInsertId(), 'message' => 'Otomatik yanıt kuralı oluşturuldu'], 201);
}

// ============================================================
// HANDLER: Otomatik Yanıt Kuralları Listesi (Admin Auth)
// ============================================================
function handleListAutoReplyRules(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, keyword, match_type, reply_message, is_active, usage_count, created_at FROM auto_reply_rules ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'rules' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Otomatik Yanıt Kuralı Sil (Admin Auth)
// ============================================================
function handleDeleteAutoReplyRule(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(['error' => 'Geçerli bir id gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('DELETE FROM auto_reply_rules WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Kural bulunamadı'], 404);
    }

    sendResponse(['success' => true, 'message' => 'Kural silindi']);
}

// ============================================================
// HANDLER: OTP Gönder (Admin Auth)
// ============================================================
function handleSendOtp(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input   = getJsonInput();
    $phone   = validatePhoneNumber((string) ($input['phone_number'] ?? ''));
    $purpose = trim((string) ($input['purpose'] ?? 'verification'));
    $length  = max(4, min(8, (int) ($input['length'] ?? 6)));
    $ttl     = max(60, min(3600, (int) ($input['ttl_seconds'] ?? 300)));
    $deviceId = validateDeviceId((string) ($input['device_id'] ?? ''));

    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }
    if ($deviceId === null) {
        sendResponse(['error' => 'device_id gerekli'], 400);
    }
    if (strlen($purpose) > 50) {
        sendResponse(['error' => 'purpose en fazla 50 karakter'], 400);
    }

    // Brute-force koruması: Son 1 saatte aynı numaraya max 5 OTP
    $db = getDBConnection();
    $stmt = $db->prepare(
        'SELECT COUNT(*) as cnt FROM otp_codes WHERE phone_number = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->execute([$phone]);
    if ((int) $stmt->fetch()['cnt'] >= 5) {
        sendResponse(['error' => 'Çok fazla OTP isteği. Lütfen bekleyin.'], 429);
    }

    // OTP kodu üret
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= random_int(0, 9);
    }

    $expiresAt = date('Y-m-d H:i:s', time() + $ttl);

    // SMS kuyruğuna ekle
    $message = "Doğrulama kodunuz: {$code}";
    if (isset($input['message_template'])) {
        $message = str_replace('{{code}}', $code, (string) $input['message_template']);
    }

    $stmt = $db->prepare(
        'INSERT INTO sms_requests (device_id, phone_number, message, priority, max_retries) VALUES (?, ?, ?, 10, ?)'
    );
    $stmt->execute([$deviceId, $phone, $message, SMS_RETRY_MAX]);
    $smsId = (int) $db->lastInsertId();

    // OTP kaydı
    $stmt = $db->prepare(
        'INSERT INTO otp_codes (phone_number, code, purpose, expires_at, sms_request_id) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$phone, $code, $purpose, $expiresAt, $smsId]);

    auditLog('otp.sent', 'admin', null, ['phone' => maskPhone($phone), 'purpose' => $purpose]);

    sendResponse([
        'success'     => true,
        'otp_length'  => $length,
        'expires_at'  => $expiresAt,
        'sms_id'      => $smsId,
        'message'     => 'OTP gönderildi',
    ], 201);
}

// ============================================================
// HANDLER: OTP Doğrula (Admin Auth)
// ============================================================
function handleVerifyOtp(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input   = getJsonInput();
    $phone   = validatePhoneNumber((string) ($input['phone_number'] ?? ''));
    $code    = trim((string) ($input['code'] ?? ''));
    $purpose = trim((string) ($input['purpose'] ?? 'verification'));

    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }
    if ($code === '') {
        sendResponse(['error' => 'Doğrulama kodu gerekli'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare(
        'SELECT id, code, attempts, max_attempts, expires_at, is_used
         FROM otp_codes
         WHERE phone_number = ? AND purpose = ? AND is_used = 0
         ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$phone, $purpose]);
    $otp = $stmt->fetch();

    if (!$otp) {
        sendResponse(['error' => 'OTP bulunamadı veya zaten kullanılmış'], 404);
    }

    if (strtotime($otp['expires_at']) < time()) {
        sendResponse(['error' => 'OTP süresi dolmuş'], 410);
    }

    if ((int) $otp['attempts'] >= (int) $otp['max_attempts']) {
        sendResponse(['error' => 'Maksimum deneme sayısı aşıldı'], 429);
    }

    // Deneme sayısını artır
    $stmt = $db->prepare('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?');
    $stmt->execute([$otp['id']]);

    if (!hash_equals($otp['code'], $code)) {
        $remaining = (int) $otp['max_attempts'] - (int) $otp['attempts'] - 1;
        sendResponse(['error' => 'Geçersiz kod', 'remaining_attempts' => max(0, $remaining)], 400);
    }

    // Başarılı doğrulama
    $stmt = $db->prepare('UPDATE otp_codes SET is_used = 1, used_at = NOW() WHERE id = ?');
    $stmt->execute([$otp['id']]);

    sendResponse(['success' => true, 'message' => 'OTP doğrulandı', 'verified' => true]);
}

// ============================================================
// HANDLER: Kısa Link Oluştur (Admin Auth)
// ============================================================
function handleCreateShortLink(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();
    $url   = trim((string) ($input['url'] ?? ''));
    $ttl   = isset($input['ttl_hours']) ? max(1, (int) $input['ttl_hours']) : null;

    if ($url === '' || strlen($url) > 2000 || !filter_var($url, FILTER_VALIDATE_URL)) {
        sendResponse(['error' => 'Geçerli bir URL gerekli (maks 2000 karakter)'], 400);
    }

    $shortCode = generateShortCode();
    $expiresAt = $ttl !== null ? date('Y-m-d H:i:s', time() + ($ttl * 3600)) : null;

    $db   = getDBConnection();
    $stmt = $db->prepare('INSERT INTO short_links (short_code, original_url, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$shortCode, $url, $expiresAt]);

    $apiUrl = rtrim(env('APP_URL', ''), '/');
    $shortUrl = $apiUrl . '/api.php?request=r&c=' . $shortCode;

    sendResponse([
        'success'    => true,
        'short_code' => $shortCode,
        'short_url'  => $shortUrl,
        'expires_at' => $expiresAt,
    ], 201);
}

function generateShortCode(int $length = 6): string
{
    $chars = 'abcdefghijkmnopqrstuvwxyz23456789';
    $code  = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

// ============================================================
// HANDLER: Kısa Link Listesi (Admin Auth)
// ============================================================
function handleListShortLinks(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT id, short_code, original_url, click_count, expires_at, created_at FROM short_links ORDER BY created_at DESC LIMIT 100');
    $stmt->execute();

    sendResponse(['success' => true, 'links' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Kısa Link Yönlendirme (Public)
// ============================================================
function handleRedirect(): never
{
    $code = trim((string) ($_GET['c'] ?? ''));
    if ($code === '' || strlen($code) > 10) {
        sendResponse(['error' => 'Geçersiz kısa kod'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT original_url, expires_at FROM short_links WHERE short_code = ?');
    $stmt->execute([$code]);
    $link = $stmt->fetch();

    if (!$link) {
        sendResponse(['error' => 'Link bulunamadı'], 404);
    }

    if ($link['expires_at'] !== null && strtotime($link['expires_at']) < time()) {
        sendResponse(['error' => 'Link süresi dolmuş'], 410);
    }

    // Click counter
    $stmt = $db->prepare('UPDATE short_links SET click_count = click_count + 1 WHERE short_code = ?');
    $stmt->execute([$code]);

    header('Location: ' . $link['original_url'], true, 302);
    exit;
}

// ============================================================
// HANDLER: CSV Import (Admin Auth)
// ============================================================
function handleImportCsv(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input     = getJsonInput();
    $deviceId  = validateDeviceId((string) ($input['device_id'] ?? ''));
    $csvData   = $input['csv_data'] ?? '';
    $message   = isset($input['message']) ? validateMessage((string) $input['message']) : null;
    $templateId = isset($input['template_id']) ? (int) $input['template_id'] : null;
    $priority  = validatePriority($input['priority'] ?? 5);

    if ($deviceId === null) {
        sendResponse(['error' => 'device_id gerekli'], 400);
    }
    if ($message === null && $templateId === null) {
        sendResponse(['error' => 'message veya template_id gerekli'], 400);
    }

    // CSV satırlarını parse et
    $lines = preg_split('/[\r\n]+/', (string) $csvData);
    if (empty($lines)) {
        sendResponse(['error' => 'CSV verisi boş'], 400);
    }

    // İlk satır header
    $headers = str_getcsv(array_shift($lines));
    $headers = array_map(fn($h) => trim(mb_strtolower($h, 'UTF-8')), $headers);

    $phoneIdx = array_search('phone', $headers);
    if ($phoneIdx === false) {
        $phoneIdx = array_search('phone_number', $headers);
    }
    if ($phoneIdx === false) {
        $phoneIdx = array_search('telefon', $headers);
    }
    if ($phoneIdx === false) {
        sendResponse(['error' => 'CSV\'de phone, phone_number veya telefon sütunu bulunamadı'], 400);
    }

    // Template al
    $templateContent = null;
    if ($templateId !== null) {
        $templateContent = resolveTemplate($templateId, []);
        if ($templateContent === null) {
            sendResponse(['error' => 'Şablon bulunamadı'], 404);
        }
    }

    $db      = getDBConnection();
    $batchId = sprintf('%s-%s', date('Ymd-His'), bin2hex(random_bytes(4)));
    $inserted = 0;
    $skipped  = 0;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO sms_requests (device_id, phone_number, message, priority, batch_id, max_retries) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $cols = str_getcsv($line);
            $phone = validatePhoneNumber(trim($cols[$phoneIdx] ?? ''));
            if ($phone === null || isBlacklisted($phone)) {
                $skipped++;
                continue;
            }

            // Değişkenleri CSV sütunlarından al
            $variables = [];
            foreach ($headers as $idx => $header) {
                $variables[$header] = $cols[$idx] ?? '';
            }

            // Mesajı oluştur
            if ($templateContent !== null) {
                $smsText = applyTemplate($templateContent, $variables);
            } else {
                $smsText = applyTemplate($message, $variables);
            }

            if ($smsText === null || trim($smsText) === '') {
                $skipped++;
                continue;
            }

            $stmt->execute([$deviceId, $phone, $smsText, $priority, $batchId, SMS_RETRY_MAX]);
            $inserted++;
        }

        $db->commit();

        auditLog('sms.csv_imported', 'admin', null, ['batch_id' => $batchId, 'inserted' => $inserted, 'skipped' => $skipped]);

        sendResponse([
            'success'  => true,
            'batch_id' => $batchId,
            'inserted' => $inserted,
            'skipped'  => $skipped,
        ], 201);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('CSV import error: ' . $e->getMessage());
        sendResponse(['error' => 'CSV import başarısız'], 500);
    }
}

// ============================================================
// HANDLER: API Key Rotation (Admin Auth)
// ============================================================
function handleRotateApiKey(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input    = getJsonInput();
    $deviceId = validateDeviceId((string) ($input['device_id'] ?? ''));

    if ($deviceId === null) {
        sendResponse(['error' => 'device_id gerekli'], 400);
    }

    $db = getDBConnection();

    // Mevcut key'i geçmişe ekle
    $stmt = $db->prepare('SELECT api_key_hash, api_key_prefix FROM devices WHERE device_id = ? AND deleted_at IS NULL');
    $stmt->execute([$deviceId]);
    $device = $stmt->fetch();

    if (!$device) {
        sendResponse(['error' => 'Cihaz bulunamadı'], 404);
    }

    // Eski key'i geçmişe kaydet (24 saat sonra expire olsun - grace period)
    $stmt = $db->prepare(
        'INSERT INTO api_key_history (device_id, api_key_hash, api_key_prefix, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))'
    );
    $stmt->execute([$deviceId, $device['api_key_hash'], $device['api_key_prefix']]);

    // Eski geçmiş key'leri deaktif et
    $stmt = $db->prepare('UPDATE api_key_history SET is_active = 0 WHERE device_id = ? AND expires_at < NOW()');
    $stmt->execute([$deviceId]);

    // Yeni key üret
    $newKey    = bin2hex(random_bytes(32));
    $keyHash   = hash('sha256', $newKey);
    $keyPrefix = substr($newKey, 0, 8);

    $stmt = $db->prepare('UPDATE devices SET api_key_hash = ?, api_key_prefix = ? WHERE device_id = ?');
    $stmt->execute([$keyHash, $keyPrefix, $deviceId]);

    auditLog('device.key_rotated', 'admin', $deviceId);

    sendResponse([
        'success'      => true,
        'new_api_key'  => $newKey,
        'grace_period' => '24 saat (eski key bu süre boyunca çalışmaya devam eder)',
        'message'      => 'API Key başarıyla değiştirildi',
    ]);
}

// ============================================================
// HANDLER: API Key Geçmişi (Admin Auth)
// ============================================================
function handleApiKeyHistory(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $deviceId = isset($_GET['device_id']) ? validateDeviceId((string) $_GET['device_id']) : null;

    $db = getDBConnection();
    if ($deviceId !== null) {
        $stmt = $db->prepare(
            'SELECT id, device_id, api_key_prefix, is_active, expires_at, rotated_at, created_at
             FROM api_key_history WHERE device_id = ? ORDER BY created_at DESC LIMIT 50'
        );
        $stmt->execute([$deviceId]);
    } else {
        $stmt = $db->prepare(
            'SELECT id, device_id, api_key_prefix, is_active, expires_at, rotated_at, created_at
             FROM api_key_history ORDER BY created_at DESC LIMIT 100'
        );
        $stmt->execute();
    }

    sendResponse(['success' => true, 'history' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Anomali Kuralları Listesi (Admin Auth)
// ============================================================
function handleListAnomalyRules(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT * FROM anomaly_rules ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'rules' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Anomali Kuralı Oluştur (Admin Auth)
// ============================================================
function handleCreateAnomalyRule(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input     = getJsonInput();
    $name      = trim((string) ($input['rule_name'] ?? ''));
    $type      = (string) ($input['rule_type'] ?? '');
    $threshold = (int) ($input['threshold_value'] ?? 0);
    $window    = max(1, (int) ($input['time_window_minutes'] ?? 5));
    $action    = (string) ($input['action_type'] ?? 'alert');

    $validTypes   = ['spike', 'flood', 'night_activity', 'high_fail_rate', 'same_number'];
    $validActions = ['alert', 'block_device', 'block_number', 'throttle'];

    if ($name === '' || strlen($name) > 100) {
        sendResponse(['error' => 'Kural adı gerekli (maks 100)'], 400);
    }
    if (!in_array($type, $validTypes, true)) {
        sendResponse(['error' => 'Geçersiz rule_type. Geçerli: ' . implode(', ', $validTypes)], 400);
    }
    if ($threshold <= 0) {
        sendResponse(['error' => 'threshold_value pozitif olmalı'], 400);
    }
    if (!in_array($action, $validActions, true)) {
        sendResponse(['error' => 'Geçersiz action_type. Geçerli: ' . implode(', ', $validActions)], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'INSERT INTO anomaly_rules (rule_name, rule_type, threshold_value, time_window_minutes, action_type) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$name, $type, $threshold, $window, $action]);

    sendResponse(['success' => true, 'rule_id' => (int) $db->lastInsertId(), 'message' => 'Anomali kuralı oluşturuldu'], 201);
}

// ============================================================
// HANDLER: Anomali Olayları (Admin Auth)
// ============================================================
function handleListAnomalyEvents(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $pagination = getPaginationParams();
    $unresolved = isset($_GET['unresolved']) && $_GET['unresolved'] === '1';

    $db = getDBConnection();

    $where = $unresolved ? 'WHERE ae.is_resolved = 0' : '';

    $stmt = $db->prepare(
        "SELECT ae.*, ar.rule_name, ar.rule_type, ar.action_type
         FROM anomaly_events ae
         JOIN anomaly_rules ar ON ae.rule_id = ar.id
         {$where}
         ORDER BY ae.created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute([$pagination['limit'], $pagination['offset']]);

    sendResponse(['success' => true, 'events' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Anomali Çöz (Admin Auth)
// ============================================================
function handleResolveAnomaly(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();
    $id    = (int) ($input['event_id'] ?? 0);

    if ($id <= 0) {
        sendResponse(['error' => 'event_id gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('UPDATE anomaly_events SET is_resolved = 1, resolved_at = NOW() WHERE id = ? AND is_resolved = 0');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Olay bulunamadı veya zaten çözülmüş'], 404);
    }

    sendResponse(['success' => true, 'message' => 'Anomali olayı çözüldü']);
}

// ============================================================
// HANDLER: Alert Kanalı Oluştur (Admin Auth)
// ============================================================
function handleCreateAlertChannel(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input       = getJsonInput();
    $channelType = (string) ($input['channel_type'] ?? '');
    $config      = $input['config'] ?? [];
    $events      = $input['events'] ?? [];

    $validTypes = ['email', 'sms', 'telegram', 'slack', 'webhook'];
    if (!in_array($channelType, $validTypes, true)) {
        sendResponse(['error' => 'Geçersiz channel_type. Geçerli: ' . implode(', ', $validTypes)], 400);
    }
    if (!is_array($config) || empty($config)) {
        sendResponse(['error' => 'config objesi gerekli'], 400);
    }
    if (!is_array($events) || empty($events)) {
        sendResponse(['error' => 'events dizisi gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('INSERT INTO alert_channels (channel_type, config, events) VALUES (?, ?, ?)');
    $stmt->execute([$channelType, json_encode($config, JSON_UNESCAPED_UNICODE), json_encode($events)]);

    sendResponse(['success' => true, 'channel_id' => (int) $db->lastInsertId(), 'message' => 'Alert kanalı oluşturuldu'], 201);
}

// ============================================================
// HANDLER: Alert Kanalları Listesi (Admin Auth)
// ============================================================
function handleListAlertChannels(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('SELECT * FROM alert_channels ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'channels' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Alert Kanalı Sil (Admin Auth)
// ============================================================
function handleDeleteAlertChannel(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        sendResponse(['error' => 'Geçerli bir id gerekli'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('DELETE FROM alert_channels WHERE id = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Kanal bulunamadı'], 404);
    }

    sendResponse(['success' => true, 'message' => 'Alert kanalı silindi']);
}

// ============================================================
// HANDLER: Maliyet Raporu (Admin Auth)
// ============================================================
function handleCostReport(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $days     = max(1, min(90, (int) ($_GET['days'] ?? 30)));
    $deviceId = isset($_GET['device_id']) ? validateDeviceId((string) $_GET['device_id']) : null;

    $db = getDBConnection();

    // Günlük maliyet
    $where  = 'WHERE record_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)';
    $params = [$days];

    if ($deviceId !== null) {
        $where   .= ' AND device_id = ?';
        $params[] = $deviceId;
    }

    $stmt = $db->prepare(
        "SELECT record_date, device_id, sms_count, total_cost, currency
         FROM cost_records {$where}
         ORDER BY record_date DESC"
    );
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    // Toplam maliyet
    $stmt = $db->prepare("SELECT SUM(total_cost) as total, SUM(sms_count) as total_sms FROM cost_records {$where}");
    $stmt->execute($params);
    $summary = $stmt->fetch();

    // Cihaz bazlı maliyet
    $stmt = $db->prepare(
        "SELECT d.device_id, d.device_name, d.cost_per_sms,
                COALESCE(SUM(cr.total_cost), 0) as total_cost,
                COALESCE(SUM(cr.sms_count), 0) as total_sms
         FROM devices d
         LEFT JOIN cost_records cr ON d.device_id = cr.device_id AND cr.record_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         WHERE d.deleted_at IS NULL
         GROUP BY d.device_id, d.device_name, d.cost_per_sms"
    );
    $stmt->execute([$days]);
    $perDevice = $stmt->fetchAll();

    sendResponse([
        'success'    => true,
        'period_days' => $days,
        'total_cost' => (float) ($summary['total'] ?? 0),
        'total_sms'  => (int) ($summary['total_sms'] ?? 0),
        'records'    => $records,
        'per_device' => $perDevice,
    ]);
}

// ============================================================
// HANDLER: Cihaz SMS Maliyeti Güncelle (Admin Auth)
// ============================================================
function handleUpdateDeviceCost(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input    = getJsonInput();
    $deviceId = validateDeviceId((string) ($input['device_id'] ?? ''));
    $cost     = (float) ($input['cost_per_sms'] ?? -1);

    if ($deviceId === null) {
        sendResponse(['error' => 'device_id gerekli'], 400);
    }
    if ($cost < 0) {
        sendResponse(['error' => 'cost_per_sms 0 veya pozitif olmalı'], 400);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare('UPDATE devices SET cost_per_sms = ? WHERE device_id = ? AND deleted_at IS NULL');
    $stmt->execute([$cost, $deviceId]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Cihaz bulunamadı'], 404);
    }

    sendResponse(['success' => true, 'message' => 'SMS maliyeti güncellendi']);
}

// ============================================================
// HANDLER: Heatmap Verisi (Admin Auth)
// ============================================================
function handleHeatmapData(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $days = max(1, min(30, (int) ($_GET['days'] ?? 7)));

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'SELECT stat_date, stat_hour, device_id, sent_count, failed_count
         FROM hourly_stats
         WHERE stat_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
         ORDER BY stat_date, stat_hour'
    );
    $stmt->execute([$days]);
    $data = $stmt->fetchAll();

    // Ayrıca gerçek zamanlı veriden de hesapla (hourly_stats güncellenmediyse)
    $stmt = $db->prepare(
        'SELECT DATE(created_at) as stat_date, HOUR(created_at) as stat_hour,
                COUNT(CASE WHEN status = \'sent\' THEN 1 END) as sent_count,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed_count
         FROM sms_requests
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
         GROUP BY DATE(created_at), HOUR(created_at)
         ORDER BY stat_date, stat_hour'
    );
    $stmt->execute([$days]);
    $realtime = $stmt->fetchAll();

    sendResponse([
        'success'       => true,
        'period_days'   => $days,
        'hourly_stats'  => $data,
        'realtime_data' => $realtime,
    ]);
}

// ============================================================
// HANDLER: CSV Export (Admin Auth)
// ============================================================
function handleExportCsv(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $type     = (string) ($_GET['type'] ?? 'sms');
    $days     = max(1, min(365, (int) ($_GET['days'] ?? 30)));
    $deviceId = isset($_GET['device_id']) ? validateDeviceId((string) $_GET['device_id']) : null;

    $db = getDBConnection();

    $csvLines = [];

    if ($type === 'sms') {
        $where  = 'WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';
        $params = [$days];
        if ($deviceId !== null) {
            $where   .= ' AND device_id = ?';
            $params[] = $deviceId;
        }

        $stmt = $db->prepare(
            "SELECT id, device_id, phone_number, status, priority, batch_id, sent_at, error_message, retry_count, created_at
             FROM sms_requests {$where} ORDER BY created_at DESC LIMIT 10000"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $csvLines[] = 'ID,Cihaz,Telefon,Durum,Oncelik,Batch,Gonderim,Hata,Deneme,Olusturma';
        foreach ($rows as $r) {
            $csvLines[] = implode(',', [
                $r['id'], $r['device_id'], $r['phone_number'], $r['status'],
                $r['priority'], $r['batch_id'] ?? '', $r['sent_at'] ?? '',
                '"' . str_replace('"', '""', $r['error_message'] ?? '') . '"',
                $r['retry_count'], $r['created_at'],
            ]);
        }
    } elseif ($type === 'devices') {
        $stmt = $db->prepare(
            'SELECT device_id, device_name, phone_number, is_active, performance_score, total_sent, total_failed, cost_per_sms, last_seen, created_at
             FROM devices WHERE deleted_at IS NULL ORDER BY device_id'
        );
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $csvLines[] = 'CihazID,Ad,Telefon,Aktif,Skor,Gonderilen,Basarisiz,SMSMaliyet,SonGorulme,Kayit';
        foreach ($rows as $r) {
            $csvLines[] = implode(',', [
                $r['device_id'], '"' . str_replace('"', '""', $r['device_name'] ?? '') . '"',
                $r['phone_number'] ?? '', $r['is_active'], $r['performance_score'],
                $r['total_sent'], $r['total_failed'], $r['cost_per_sms'],
                $r['last_seen'] ?? '', $r['created_at'],
            ]);
        }
    } elseif ($type === 'audit') {
        $stmt = $db->prepare(
            'SELECT id, action, actor_type, actor_id, ip_address, request_method, request_path, created_at
             FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) ORDER BY created_at DESC LIMIT 10000'
        );
        $stmt->execute([$days]);
        $rows = $stmt->fetchAll();

        $csvLines[] = 'ID,Aksiyon,AktorTip,AktorID,IP,Method,Path,Tarih';
        foreach ($rows as $r) {
            $csvLines[] = implode(',', [
                $r['id'], $r['action'], $r['actor_type'], $r['actor_id'] ?? '',
                $r['ip_address'] ?? '', $r['request_method'] ?? '',
                '"' . str_replace('"', '""', $r['request_path'] ?? '') . '"',
                $r['created_at'],
            ]);
        }
    } else {
        sendResponse(['error' => 'Geçersiz type. Geçerli: sms, devices, audit'], 400);
    }

    $csv = implode("\n", $csvLines);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="export_' . $type . '_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    echo $csv;
    exit;
}

// ============================================================
// HANDLER: Cihaz Performans Skorları (Admin Auth)
// ============================================================
function handleDeviceScores(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db = getDBConnection();

    // Güncel skorları hesapla
    $stmt = $db->prepare(
        'SELECT d.device_id, d.device_name, d.performance_score, d.total_sent, d.total_failed, d.weight,
                d.last_seen, d.is_active,
                CASE WHEN d.total_sent + d.total_failed > 0
                     THEN ROUND(d.total_sent * 100.0 / (d.total_sent + d.total_failed), 2)
                     ELSE 100 END as success_rate,
                CASE WHEN d.last_seen IS NOT NULL AND TIMESTAMPDIFF(SECOND, d.last_seen, NOW()) < ?
                     THEN 1 ELSE 0 END as is_online
         FROM devices d
         WHERE d.deleted_at IS NULL
         ORDER BY d.performance_score DESC'
    );
    $stmt->execute([DEVICE_OFFLINE_THRESHOLD]);
    $devices = $stmt->fetchAll();

    // Skor geçmişi (son 7 gün)
    $stmt = $db->prepare(
        'SELECT device_id, score, success_rate, avg_response_ms, uptime_pct, recorded_at
         FROM device_score_history
         WHERE recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY recorded_at'
    );
    $stmt->execute();
    $history = $stmt->fetchAll();

    sendResponse([
        'success' => true,
        'devices' => $devices,
        'history' => $history,
    ]);
}

// ============================================================
// HANDLER: Uptime Durumu (Admin Auth)
// ============================================================
function handleUptimeStatus(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $db = getDBConnection();

    // Son 24 saatlik uptime verileri
    $stmt = $db->prepare(
        'SELECT check_type, target_id, is_up, response_time_ms, error_message, created_at
         FROM uptime_checks
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         ORDER BY created_at DESC
         LIMIT 500'
    );
    $stmt->execute();
    $checks = $stmt->fetchAll();

    // Cihaz bazlı uptime yüzdesi (son 24 saat)
    $stmt = $db->prepare(
        'SELECT target_id,
                COUNT(*) as total_checks,
                SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_count,
                ROUND(SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as uptime_pct,
                ROUND(AVG(response_time_ms)) as avg_response_ms
         FROM uptime_checks
         WHERE check_type = \'device\' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY target_id'
    );
    $stmt->execute();
    $deviceUptime = $stmt->fetchAll();

    // API uptime
    $stmt = $db->prepare(
        'SELECT ROUND(SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as uptime_pct,
                ROUND(AVG(response_time_ms)) as avg_response_ms
         FROM uptime_checks
         WHERE check_type = \'api\' AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $stmt->execute();
    $apiUptime = $stmt->fetch();

    sendResponse([
        'success'       => true,
        'checks'        => $checks,
        'device_uptime' => $deviceUptime,
        'api_uptime'    => [
            'uptime_pct'      => (float) ($apiUptime['uptime_pct'] ?? 100),
            'avg_response_ms' => (int) ($apiUptime['avg_response_ms'] ?? 0),
        ],
    ]);
}

// ============================================================
// HANDLER: Audit Log Görüntüleyici (Admin Auth)
// ============================================================
function handleListAuditLogs(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $pagination = getPaginationParams();
    $action     = isset($_GET['action']) ? trim((string) $_GET['action']) : null;
    $actorType  = isset($_GET['actor_type']) ? trim((string) $_GET['actor_type']) : null;

    $where  = [];
    $params = [];

    if ($action !== null && $action !== '') {
        $where[]  = 'action LIKE ?';
        $params[] = '%' . $action . '%';
    }
    if ($actorType !== null && $actorType !== '') {
        $where[]  = 'actor_type = ?';
        $params[] = $actorType;
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $db = getDBConnection();

    $stmt = $db->prepare("SELECT COUNT(*) as total FROM audit_logs {$whereClause}");
    $stmt->execute($params);
    $total = (int) $stmt->fetch()['total'];

    $stmt = $db->prepare(
        "SELECT id, action, actor_type, actor_id, ip_address, user_agent, request_method, request_path, details, created_at
         FROM audit_logs {$whereClause}
         ORDER BY created_at DESC LIMIT ? OFFSET ?"
    );
    $stmt->execute(array_merge($params, [$pagination['limit'], $pagination['offset']]));

    sendResponse([
        'success'     => true,
        'total'       => $total,
        'page'        => $pagination['page'],
        'limit'       => $pagination['limit'],
        'total_pages' => (int) ceil($total / $pagination['limit']),
        'logs'        => $stmt->fetchAll(),
    ]);
}

// ============================================================
// HANDLER: Gelişmiş Ağırlıklı Dağıtım (Admin Auth)
// ============================================================
function handleDistributeWeighted(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();

    $deviceIds = $input['device_ids'] ?? [];
    if (!is_array($deviceIds) || empty($deviceIds)) {
        sendResponse(['error' => 'device_ids dizisi gerekli'], 400);
    }

    $rawNumbers   = $input['phone_numbers'] ?? '';
    $phoneNumbers = parsePhoneNumbers($rawNumbers);
    if (empty($phoneNumbers)) {
        sendResponse(['error' => 'Geçerli telefon numarası bulunamadı'], 400);
    }

    $message  = null;
    $priority = validatePriority($input['priority'] ?? 5);
    $strategy = (string) ($input['strategy'] ?? 'weighted'); // weighted, performance, cost

    if (isset($input['template_id'])) {
        $message = resolveTemplate((int) $input['template_id'], $input['variables'] ?? []);
        if ($message === null) {
            sendResponse(['error' => 'Şablon bulunamadı'], 404);
        }
    } else {
        $message = validateMessage((string) ($input['message'] ?? ''));
    }

    if ($message === null) {
        sendResponse(['error' => 'Mesaj veya template_id gerekli'], 400);
    }

    $db = getDBConnection();

    // Cihaz bilgilerini al
    $placeholders = implode(',', array_fill(0, count($deviceIds), '?'));
    $stmt = $db->prepare(
        "SELECT device_id, weight, performance_score, cost_per_sms
         FROM devices
         WHERE device_id IN ({$placeholders}) AND is_active = 1 AND deleted_at IS NULL"
    );
    $stmt->execute($deviceIds);
    $devices = $stmt->fetchAll();

    if (empty($devices)) {
        sendResponse(['error' => 'Aktif cihaz bulunamadı'], 400);
    }

    // Kara liste filtresi
    $cleanNumbers = [];
    $blacklisted  = 0;
    foreach ($phoneNumbers as $phone) {
        if (isBlacklisted($phone)) {
            $blacklisted++;
        } else {
            $cleanNumbers[] = $phone;
        }
    }

    if (empty($cleanNumbers)) {
        sendResponse(['error' => 'Tüm numaralar kara listede'], 400);
    }

    // Strateji bazlı ağırlık hesaplama
    $deviceWeights = [];
    foreach ($devices as $d) {
        $w = match ($strategy) {
            'performance' => max(1, (int) $d['performance_score']),
            'cost'        => max(1, (int) (100 - ($d['cost_per_sms'] * 100))),
            default       => max(1, (int) $d['weight']),
        };
        $deviceWeights[$d['device_id']] = $w;
    }

    $totalWeight = array_sum($deviceWeights);

    // Ağırlıklı dağıtım
    $batchId      = sprintf('%s-%s', date('Ymd-His'), bin2hex(random_bytes(4)));
    $distribution = [];
    $deviceQueue  = [];

    // Her cihaza düşen SMS sayısını hesapla
    $remaining = count($cleanNumbers);
    foreach ($deviceWeights as $did => $w) {
        $share = (int) round(count($cleanNumbers) * $w / $totalWeight);
        $share = min($share, $remaining);
        $deviceQueue[$did] = $share;
        $remaining -= $share;
    }

    // Kalan varsa ilk cihaza ekle
    if ($remaining > 0) {
        $firstDevice = array_key_first($deviceQueue);
        $deviceQueue[$firstDevice] += $remaining;
    }

    $inserted = 0;
    $idx      = 0;

    $db->beginTransaction();
    try {
        $stmt = $db->prepare(
            'INSERT INTO sms_requests (device_id, phone_number, message, priority, batch_id, max_retries) VALUES (?, ?, ?, ?, ?, ?)'
        );

        foreach ($deviceQueue as $did => $count) {
            $distribution[$did] = 0;
            for ($i = 0; $i < $count && $idx < count($cleanNumbers); $i++, $idx++) {
                $stmt->execute([$did, $cleanNumbers[$idx], $message, $priority, $batchId, SMS_RETRY_MAX]);
                $inserted++;
                $distribution[$did]++;
            }
        }

        $db->commit();

        auditLog('sms.distributed_weighted', 'admin', null, [
            'batch_id'    => $batchId,
            'strategy'    => $strategy,
            'total'       => $inserted,
            'blacklisted' => $blacklisted,
            'weights'     => $deviceWeights,
        ]);

        sendResponse([
            'success'       => true,
            'batch_id'      => $batchId,
            'strategy'      => $strategy,
            'inserted'      => $inserted,
            'blacklisted'   => $blacklisted,
            'distribution'  => $distribution,
            'weights'       => $deviceWeights,
        ], 201);
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Weighted distribute error: ' . $e->getMessage());
        sendResponse(['error' => 'Ağırlıklı dağıtım başarısız'], 500);
    }
}

function parsePhoneNumbers(mixed $raw): array
{
    $phoneNumbers = [];
    if (is_string($raw)) {
        $lines = preg_split('/[\r\n,;]+/', $raw);
        foreach ($lines as $line) {
            $num = validatePhoneNumber(trim($line));
            if ($num !== null) {
                $phoneNumbers[] = $num;
            }
        }
    } elseif (is_array($raw)) {
        foreach ($raw as $num) {
            $validated = validatePhoneNumber(trim((string) $num));
            if ($validated !== null) {
                $phoneNumbers[] = $validated;
            }
        }
    }
    return array_values(array_unique($phoneNumbers));
}

// ============================================================
// HANDLER: SSE Stream - Gerçek Zamanlı Dashboard (Admin Auth)
// ============================================================
function handleStream(): never
{
    $auth = authenticateAdmin();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $db = getDBConnection();

    $startTime = time();
    $maxDuration = 300; // 5 dakika max

    while (time() - $startTime < $maxDuration) {
        // İstatistikleri çek
        $stmt = $db->prepare(
            'SELECT
                COUNT(*) as total,
                SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
             FROM sms_requests WHERE DATE(created_at) = CURDATE()'
        );
        $stmt->execute();
        $stats = $stmt->fetch();

        // Aktif cihaz sayısı
        $stmt = $db->prepare(
            'SELECT COUNT(*) as count FROM devices
             WHERE deleted_at IS NULL AND is_active = 1
             AND last_seen >= DATE_SUB(NOW(), INTERVAL ? SECOND)'
        );
        $stmt->execute([DEVICE_OFFLINE_THRESHOLD]);
        $online = (int) $stmt->fetch()['count'];

        // Son 5 SMS aktivitesi
        $stmt = $db->prepare(
            'SELECT id, device_id, phone_number, status, created_at
             FROM sms_requests ORDER BY updated_at DESC LIMIT 5'
        );
        $stmt->execute();
        $recent = $stmt->fetchAll();

        $data = json_encode([
            'stats'          => $stats,
            'online_devices' => $online,
            'recent'         => $recent,
            'timestamp'      => date('c'),
        ], JSON_UNESCAPED_UNICODE);

        echo "data: {$data}\n\n";

        if (ob_get_level()) {
            ob_flush();
        }
        flush();

        sleep(5);

        if (connection_aborted()) {
            break;
        }
    }

    echo "event: close\ndata: stream_ended\n\n";
    exit;
}

// ============================================================
// HANDLER: Manuel SMS Gönderimi (Device Auth)
// ============================================================
function handleSendManualSms(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input   = getJsonInput();
    $phone   = validatePhoneNumber((string) ($input['phone_number'] ?? ''));
    $message = validateMessage((string) ($input['message'] ?? ''));

    if ($phone === null) {
        sendResponse(['error' => 'Geçerli bir telefon numarası gerekli'], 400);
    }
    if ($message === null) {
        sendResponse(['error' => 'Mesaj boş olamaz'], 400);
    }
    if (isBlacklisted($phone)) {
        sendResponse(['error' => 'Bu numara kara listede'], 403);
    }

    $db   = getDBConnection();
    $stmt = $db->prepare(
        'INSERT INTO sms_requests (device_id, phone_number, message, priority, max_retries) VALUES (?, ?, ?, 5, ?)'
    );
    $stmt->execute([$auth['device_id'], $phone, $message, SMS_RETRY_MAX]);
    $requestId = (int) $db->lastInsertId();

    auditLog('sms.manual_created', 'device', $auth['device_id'], ['request_id' => $requestId, 'phone' => maskPhone($phone)]);

    sendResponse([
        'success'    => true,
        'request_id' => $requestId,
        'message'    => 'SMS kuyruğa eklendi',
    ], 201);
}

// ============================================================
// HANDLER: Cihaza Uzaktan Komut Gönder (Admin)
// ============================================================
function handleCreateDeviceCommand(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $deviceId = trim($input['device_id'] ?? '');
    $commandType = trim($input['command_type'] ?? '');
    $payload = $input['payload'] ?? null;
    $expiresMinutes = (int) ($input['expires_minutes'] ?? 30);

    if (empty($deviceId) || empty($commandType)) {
        sendResponse(['error' => 'device_id ve command_type zorunludur'], 400);
    }

    $validCommands = ['restart_service', 'sync_config', 'update_app', 'clear_queue', 'send_log', 'custom'];
    if (!in_array($commandType, $validCommands, true)) {
        sendResponse(['error' => 'Geçersiz komut tipi. Geçerli: ' . implode(', ', $validCommands)], 400);
    }

    $db = getDBConnection();

    // Cihaz var mı kontrol
    $stmt = $db->prepare('SELECT device_id FROM devices WHERE device_id = ? AND deleted_at IS NULL');
    $stmt->execute([$deviceId]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'Cihaz bulunamadı'], 404);
    }

    $expiresAt = date('Y-m-d H:i:s', time() + ($expiresMinutes * 60));

    $stmt = $db->prepare(
        'INSERT INTO device_commands (device_id, command_type, payload, expires_at, created_by) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $deviceId,
        $commandType,
        $payload !== null ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null,
        $expiresAt,
        'admin',
    ]);

    $commandId = (int) $db->lastInsertId();
    auditLog('device_command.created', 'admin', ['command_id' => $commandId, 'device_id' => $deviceId, 'type' => $commandType]);

    sendResponse([
        'success' => true,
        'command_id' => $commandId,
        'message' => "Komut oluşturuldu: {$commandType}",
        'expires_at' => $expiresAt,
    ], 201);
}

// ============================================================
// HANDLER: Cihaz Komutlarını Listele (Admin)
// ============================================================
function handleListDeviceCommands(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $deviceId = trim($_GET['device_id'] ?? '');
    $status = trim($_GET['status'] ?? '');
    $limit = min((int) ($_GET['limit'] ?? 50), 200);

    $where = [];
    $params = [];

    if (!empty($deviceId)) {
        $where[] = 'dc.device_id = ?';
        $params[] = $deviceId;
    }
    if (!empty($status)) {
        $where[] = 'dc.status = ?';
        $params[] = $status;
    }

    $whereStr = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $db->prepare(
        "SELECT dc.*, d.device_name
         FROM device_commands dc
         LEFT JOIN devices d ON dc.device_id = d.device_id
         {$whereStr}
         ORDER BY dc.created_at DESC LIMIT ?"
    );
    $params[] = $limit;
    $stmt->execute($params);

    sendResponse(['success' => true, 'commands' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Cihaz Komut Polling (Device Auth)
// ============================================================
function handlePollCommands(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $deviceId = $auth['device_id'];
    $db = getDBConnection();

    // Süresi dolmuş komutları expire et
    $stmt = $db->prepare(
        "UPDATE device_commands SET status = 'expired' WHERE device_id = ? AND status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()"
    );
    $stmt->execute([$deviceId]);

    // Bekleyen komutları al
    $stmt = $db->prepare(
        "SELECT id, command_type, payload, created_at, expires_at
         FROM device_commands
         WHERE device_id = ? AND status = 'pending'
         ORDER BY created_at ASC LIMIT 10"
    );
    $stmt->execute([$deviceId]);
    $commands = $stmt->fetchAll();

    // Alınan komutları 'sent' olarak işaretle
    if (!empty($commands)) {
        $ids = array_column($commands, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $db->prepare("UPDATE device_commands SET status = 'sent', sent_at = NOW() WHERE id IN ({$placeholders})");
        $stmt->execute($ids);
    }

    // Payload'ları decode et
    foreach ($commands as &$cmd) {
        if ($cmd['payload'] !== null) {
            $cmd['payload'] = json_decode($cmd['payload'], true);
        }
    }

    sendResponse(['success' => true, 'commands' => $commands]);
}

// ============================================================
// HANDLER: Komut Sonucu Bildir (Device Auth)
// ============================================================
function handleCommandResult(): never
{
    $auth = authenticateDevice();
    if (isset($auth['error'])) {
        sendResponse($auth, $auth['code']);
    }

    $input = getJsonInput();
    $commandId = (int) ($input['command_id'] ?? 0);
    $success = (bool) ($input['success'] ?? false);
    $result = $input['result'] ?? null;

    if ($commandId <= 0) {
        sendResponse(['error' => 'command_id zorunludur'], 400);
    }

    $db = getDBConnection();

    // Komutun bu cihaza ait olduğunu doğrula
    $stmt = $db->prepare('SELECT id FROM device_commands WHERE id = ? AND device_id = ?');
    $stmt->execute([$commandId, $auth['device_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['error' => 'Komut bulunamadı'], 404);
    }

    $newStatus = $success ? 'executed' : 'failed';
    $stmt = $db->prepare(
        'UPDATE device_commands SET status = ?, result = ?, executed_at = NOW() WHERE id = ?'
    );
    $stmt->execute([
        $newStatus,
        $result !== null ? json_encode($result, JSON_UNESCAPED_UNICODE) : null,
        $commandId,
    ]);

    sendResponse(['success' => true, 'message' => 'Komut sonucu kaydedildi']);
}

// ============================================================
// HANDLER: SMS Kuyruk İstatistikleri (Admin)
// ============================================================
function handleQueueStats(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    // Durum bazlı sayılar
    $stmt = $db->prepare(
        "SELECT status, COUNT(*) as count FROM sms_requests GROUP BY status"
    );
    $stmt->execute();
    $statusCounts = [];
    foreach ($stmt->fetchAll() as $row) {
        $statusCounts[$row['status']] = (int) $row['count'];
    }

    // Son 1 saatteki throughput (dakika bazlı)
    $stmt = $db->prepare(
        "SELECT
            DATE_FORMAT(created_at, '%H:%i') as minute,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending
         FROM sms_requests
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:%i')
         ORDER BY minute ASC"
    );
    $stmt->execute();
    $throughput = $stmt->fetchAll();

    // Cihaz bazlı kuyruk
    $stmt = $db->prepare(
        "SELECT sr.device_id, d.device_name,
                COUNT(CASE WHEN sr.status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN sr.status = 'processing' THEN 1 END) as processing,
                COUNT(CASE WHEN sr.status = 'sent' THEN 1 END) as sent,
                COUNT(CASE WHEN sr.status = 'failed' THEN 1 END) as failed
         FROM sms_requests sr
         LEFT JOIN devices d ON sr.device_id = d.device_id
         WHERE sr.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY sr.device_id"
    );
    $stmt->execute();
    $deviceQueues = $stmt->fetchAll();

    // Ortalama işlem süresi (pending -> sent)
    $stmt = $db->prepare(
        "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_process_seconds
         FROM sms_requests
         WHERE status = 'sent' AND sent_at IS NOT NULL
         AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)"
    );
    $stmt->execute();
    $avgTime = $stmt->fetch();

    // Kuyruk derinliği trendi (son 24 saat, saatlik)
    $stmt = $db->prepare(
        "SELECT DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_at_hour,
                COUNT(*) as total_at_hour
         FROM sms_requests
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
         GROUP BY DATE_FORMAT(created_at, '%Y-%m-%d %H:00')
         ORDER BY hour ASC"
    );
    $stmt->execute();
    $queueTrend = $stmt->fetchAll();

    sendResponse([
        'success' => true,
        'status_counts' => $statusCounts,
        'throughput' => $throughput,
        'device_queues' => $deviceQueues,
        'avg_process_seconds' => round((float) ($avgTime['avg_process_seconds'] ?? 0), 1),
        'queue_trend' => $queueTrend,
    ]);
}

// ============================================================
// HANDLER: Kuyruk Canlı SSE (Admin)
// ============================================================
function handleQueueLive(): never
{
    authenticateAdmin();

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    header('X-Accel-Buffering: no');

    $db = getDBConnection();
    $maxDuration = 180; // 3 dakika
    $start = time();

    while ((time() - $start) < $maxDuration) {
        $stmt = $db->prepare(
            "SELECT status, COUNT(*) as count FROM sms_requests
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
             GROUP BY status"
        );
        $stmt->execute();
        $counts = [];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        // Son 5 SMS
        $stmt = $db->prepare(
            "SELECT id, device_id, phone_number, status, created_at, sent_at
             FROM sms_requests ORDER BY created_at DESC LIMIT 5"
        );
        $stmt->execute();
        $recent = $stmt->fetchAll();

        $data = json_encode([
            'counts' => $counts,
            'recent' => $recent,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE);

        echo "data: {$data}\n\n";
        @ob_flush();
        @flush();

        if (connection_aborted()) break;
        sleep(3);
    }

    echo "event: close\ndata: done\n\n";
    exit;
}

// ============================================================
// HANDLER: Otomatik Ölçeklendirme Kuralı Oluştur (Admin)
// ============================================================
function handleCreateAutoscaleRule(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $ruleName = trim($input['rule_name'] ?? '');
    $metric = trim($input['metric'] ?? '');
    $scaleUpThreshold = (int) ($input['scale_up_threshold'] ?? 0);
    $scaleDownThreshold = (int) ($input['scale_down_threshold'] ?? 0);
    $minDevices = (int) ($input['min_devices'] ?? 1);
    $maxDevices = (int) ($input['max_devices'] ?? 10);
    $cooldownMinutes = (int) ($input['cooldown_minutes'] ?? 5);

    if (empty($ruleName) || empty($metric)) {
        sendResponse(['error' => 'rule_name ve metric zorunludur'], 400);
    }

    $validMetrics = ['queue_size', 'fail_rate', 'response_time', 'throughput'];
    if (!in_array($metric, $validMetrics, true)) {
        sendResponse(['error' => 'Geçersiz metric. Geçerli: ' . implode(', ', $validMetrics)], 400);
    }

    if ($scaleUpThreshold <= $scaleDownThreshold) {
        sendResponse(['error' => 'scale_up_threshold, scale_down_threshold dan büyük olmalı'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare(
        'INSERT INTO autoscale_rules (rule_name, metric, scale_up_threshold, scale_down_threshold, min_devices, max_devices, cooldown_minutes) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$ruleName, $metric, $scaleUpThreshold, $scaleDownThreshold, $minDevices, $maxDevices, $cooldownMinutes]);

    auditLog('autoscale_rule.created', 'admin', ['rule_name' => $ruleName, 'metric' => $metric]);

    sendResponse([
        'success' => true,
        'rule_id' => (int) $db->lastInsertId(),
        'message' => 'Ölçeklendirme kuralı oluşturuldu',
    ], 201);
}

// ============================================================
// HANDLER: Otomatik Ölçeklendirme Kurallarını Listele (Admin)
// ============================================================
function handleListAutoscaleRules(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $stmt = $db->prepare('SELECT * FROM autoscale_rules ORDER BY created_at DESC');
    $stmt->execute();

    sendResponse(['success' => true, 'rules' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Otomatik Ölçeklendirme Kuralı Sil (Admin)
// ============================================================
function handleDeleteAutoscaleRule(): never
{
    authenticateAdmin();
    $ruleId = (int) ($_GET['id'] ?? 0);

    if ($ruleId <= 0) {
        sendResponse(['error' => 'id zorunludur'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare('DELETE FROM autoscale_rules WHERE id = ?');
    $stmt->execute([$ruleId]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Kural bulunamadı'], 404);
    }

    auditLog('autoscale_rule.deleted', 'admin', ['rule_id' => $ruleId]);
    sendResponse(['success' => true, 'message' => 'Kural silindi']);
}

// ============================================================
// HANDLER: Otomatik Ölçeklendirme Geçmişi (Admin)
// ============================================================
function handleAutoscaleHistory(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $limit = min((int) ($_GET['limit'] ?? 50), 200);

    $stmt = $db->prepare(
        'SELECT ah.*, ar.rule_name, ar.metric
         FROM autoscale_history ah
         LEFT JOIN autoscale_rules ar ON ah.rule_id = ar.id
         ORDER BY ah.created_at DESC LIMIT ?'
    );
    $stmt->execute([$limit]);

    sendResponse(['success' => true, 'history' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: Otomatik Ölçeklendirme Kuralını Aç/Kapat (Admin)
// ============================================================
function handleAutoscaleToggle(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $ruleId = (int) ($input['id'] ?? 0);
    if ($ruleId <= 0) {
        sendResponse(['error' => 'id zorunludur'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare('SELECT is_active FROM autoscale_rules WHERE id = ?');
    $stmt->execute([$ruleId]);
    $rule = $stmt->fetch();

    if (!$rule) {
        sendResponse(['error' => 'Kural bulunamadı'], 404);
    }

    $newStatus = $rule['is_active'] ? 0 : 1;
    $stmt = $db->prepare('UPDATE autoscale_rules SET is_active = ? WHERE id = ?');
    $stmt->execute([$newStatus, $ruleId]);

    auditLog('autoscale_rule.toggled', 'admin', ['rule_id' => $ruleId, 'is_active' => $newStatus]);

    sendResponse([
        'success' => true,
        'is_active' => (bool) $newStatus,
        'message' => $newStatus ? 'Kural aktifleştirildi' : 'Kural devre dışı bırakıldı',
    ]);
}

// ============================================================
// HANDLER: Public Durum Sayfası (Auth Yok)
// ============================================================
function handlePublicStatus(): never
{
    // Auth gerektirmez - public endpoint
    $db = getDBConnection();

    // Bileşen durumları
    $stmt = $db->prepare('SELECT component, status, message, updated_at FROM system_status WHERE is_public = 1 ORDER BY component');
    $stmt->execute();
    $components = $stmt->fetchAll();

    // Son olaylar (son 7 gün)
    $stmt = $db->prepare(
        'SELECT id, title, description, severity, status, affected_components, resolved_at, created_at, updated_at
         FROM status_incidents
         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
         ORDER BY created_at DESC LIMIT 10'
    );
    $stmt->execute();
    $incidents = $stmt->fetchAll();
    foreach ($incidents as &$inc) {
        if ($inc['affected_components'] !== null) {
            $inc['affected_components'] = json_decode($inc['affected_components'], true);
        }
    }

    // Genel uptime (son 30 gün)
    $stmt = $db->prepare(
        "SELECT COUNT(*) as total, SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_count
         FROM uptime_checks WHERE check_type = 'api' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
    $stmt->execute();
    $uptimeData = $stmt->fetch();
    $uptimePct = (int) $uptimeData['total'] > 0
        ? round(((int) $uptimeData['up_count'] / (int) $uptimeData['total']) * 100, 2)
        : 100.0;

    // Genel durum hesapla
    $overallStatus = 'operational';
    foreach ($components as $c) {
        if ($c['status'] === 'major_outage') { $overallStatus = 'major_outage'; break; }
        if ($c['status'] === 'partial_outage') { $overallStatus = 'partial_outage'; }
        if ($c['status'] === 'degraded' && $overallStatus === 'operational') { $overallStatus = 'degraded'; }
    }

    sendResponse([
        'success' => true,
        'overall_status' => $overallStatus,
        'uptime_30d' => $uptimePct,
        'components' => $components,
        'recent_incidents' => $incidents,
    ]);
}

// ============================================================
// HANDLER: Sistem Durumu (Admin - tüm detaylar)
// ============================================================
function handleSystemStatus(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $stmt = $db->prepare('SELECT * FROM system_status ORDER BY component');
    $stmt->execute();
    $components = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT * FROM status_incidents ORDER BY created_at DESC LIMIT 20');
    $stmt->execute();
    $incidents = $stmt->fetchAll();
    foreach ($incidents as &$inc) {
        if ($inc['affected_components'] !== null) {
            $inc['affected_components'] = json_decode($inc['affected_components'], true);
        }
    }

    sendResponse(['success' => true, 'components' => $components, 'incidents' => $incidents]);
}

// ============================================================
// HANDLER: Bileşen Durumunu Güncelle (Admin)
// ============================================================
function handleUpdateComponentStatus(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $component = trim($input['component'] ?? '');
    $status = trim($input['status'] ?? '');
    $message = trim($input['message'] ?? '');

    if (empty($component) || empty($status)) {
        sendResponse(['error' => 'component ve status zorunludur'], 400);
    }

    $validStatuses = ['operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance'];
    if (!in_array($status, $validStatuses, true)) {
        sendResponse(['error' => 'Geçersiz status'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare(
        'UPDATE system_status SET status = ?, message = ?, updated_by = ?, updated_at = NOW() WHERE component = ?'
    );
    $stmt->execute([$status, $message, 'admin', $component]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['error' => 'Bileşen bulunamadı'], 404);
    }

    auditLog('system_status.updated', 'admin', ['component' => $component, 'status' => $status]);
    sendResponse(['success' => true, 'message' => "Durum güncellendi: {$component} -> {$status}"]);
}

// ============================================================
// HANDLER: Olay Oluştur (Admin)
// ============================================================
function handleCreateIncident(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $severity = trim($input['severity'] ?? 'minor');
    $affectedComponents = $input['affected_components'] ?? [];

    if (empty($title)) {
        sendResponse(['error' => 'title zorunludur'], 400);
    }

    $validSeverities = ['minor', 'major', 'critical'];
    if (!in_array($severity, $validSeverities, true)) {
        sendResponse(['error' => 'Geçersiz severity'], 400);
    }

    $db = getDBConnection();
    $stmt = $db->prepare(
        'INSERT INTO status_incidents (title, description, severity, affected_components) VALUES (?, ?, ?, ?)'
    );
    $stmt->execute([
        $title,
        $description,
        $severity,
        !empty($affectedComponents) ? json_encode($affectedComponents) : null,
    ]);

    // Etkilenen bileşenlerin durumunu güncelle
    if (!empty($affectedComponents)) {
        $newStatus = $severity === 'critical' ? 'major_outage' : ($severity === 'major' ? 'partial_outage' : 'degraded');
        foreach ($affectedComponents as $comp) {
            $stmt2 = $db->prepare('UPDATE system_status SET status = ?, updated_by = ? WHERE component = ?');
            $stmt2->execute([$newStatus, 'system', $comp]);
        }
    }

    auditLog('incident.created', 'admin', ['title' => $title, 'severity' => $severity]);

    sendResponse([
        'success' => true,
        'incident_id' => (int) $db->lastInsertId(),
        'message' => 'Olay oluşturuldu',
    ], 201);
}

// ============================================================
// HANDLER: Olay Güncelle (Admin)
// ============================================================
function handleUpdateIncident(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $incidentId = (int) ($input['id'] ?? 0);
    $status = trim($input['status'] ?? '');

    if ($incidentId <= 0 || empty($status)) {
        sendResponse(['error' => 'id ve status zorunludur'], 400);
    }

    $validStatuses = ['investigating', 'identified', 'monitoring', 'resolved'];
    if (!in_array($status, $validStatuses, true)) {
        sendResponse(['error' => 'Geçersiz status'], 400);
    }

    $db = getDBConnection();

    $updates = ['status = ?'];
    $params = [$status];

    if ($status === 'resolved') {
        $updates[] = 'resolved_at = NOW()';

        // Etkilenen bileşenleri operational'a çevir
        $stmt = $db->prepare('SELECT affected_components FROM status_incidents WHERE id = ?');
        $stmt->execute([$incidentId]);
        $incident = $stmt->fetch();
        if ($incident && $incident['affected_components']) {
            $components = json_decode($incident['affected_components'], true);
            if (is_array($components)) {
                foreach ($components as $comp) {
                    $stmt2 = $db->prepare("UPDATE system_status SET status = 'operational', updated_by = 'system' WHERE component = ?");
                    $stmt2->execute([$comp]);
                }
            }
        }
    }

    if (!empty($input['description'])) {
        $updates[] = 'description = ?';
        $params[] = trim($input['description']);
    }

    $params[] = $incidentId;
    $stmt = $db->prepare('UPDATE status_incidents SET ' . implode(', ', $updates) . ' WHERE id = ?');
    $stmt->execute($params);

    auditLog('incident.updated', 'admin', ['incident_id' => $incidentId, 'status' => $status]);

    sendResponse(['success' => true, 'message' => 'Olay güncellendi']);
}

// ============================================================
// HANDLER: Olayları Listele (Admin)
// ============================================================
function handleListIncidents(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $status = trim($_GET['status'] ?? '');
    $limit = min((int) ($_GET['limit'] ?? 50), 200);

    $where = '';
    $params = [];
    if (!empty($status)) {
        $where = 'WHERE status = ?';
        $params[] = $status;
    }
    $params[] = $limit;

    $stmt = $db->prepare("SELECT * FROM status_incidents {$where} ORDER BY created_at DESC LIMIT ?");
    $stmt->execute($params);

    $incidents = $stmt->fetchAll();
    foreach ($incidents as &$inc) {
        if ($inc['affected_components'] !== null) {
            $inc['affected_components'] = json_decode($inc['affected_components'], true);
        }
    }

    sendResponse(['success' => true, 'incidents' => $incidents]);
}

// ============================================================
// HANDLER: API Versiyonları Listele (Admin)
// ============================================================
function handleListApiVersions(): never
{
    authenticateAdmin();
    $db = getDBConnection();

    $stmt = $db->prepare('SELECT * FROM api_versions ORDER BY version ASC');
    $stmt->execute();

    sendResponse(['success' => true, 'versions' => $stmt->fetchAll()]);
}

// ============================================================
// HANDLER: API Versiyon Güncelle (Admin)
// ============================================================
function handleUpdateApiVersion(): never
{
    authenticateAdmin();
    $input = getJsonInput();

    $version = trim($input['version'] ?? '');
    $isActive = isset($input['is_active']) ? (bool) $input['is_active'] : null;
    $isDefault = isset($input['is_default']) ? (bool) $input['is_default'] : null;
    $changelog = trim($input['changelog'] ?? '');
    $deprecatedAt = $input['deprecated_at'] ?? null;
    $sunsetAt = $input['sunset_at'] ?? null;

    if (empty($version)) {
        sendResponse(['error' => 'version zorunludur'], 400);
    }

    $db = getDBConnection();

    // Versiyon var mı kontrol
    $stmt = $db->prepare('SELECT id FROM api_versions WHERE version = ?');
    $stmt->execute([$version]);

    if (!$stmt->fetch()) {
        // Yeni versiyon oluştur
        $stmt = $db->prepare(
            'INSERT INTO api_versions (version, is_active, is_default, changelog, deprecated_at, sunset_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $version,
            $isActive !== null ? (int) $isActive : 1,
            $isDefault !== null ? (int) $isDefault : 0,
            $changelog ?: null,
            $deprecatedAt,
            $sunsetAt,
        ]);
    } else {
        // Güncelle
        $updates = [];
        $params = [];

        if ($isActive !== null) { $updates[] = 'is_active = ?'; $params[] = (int) $isActive; }
        if ($isDefault !== null) {
            if ($isDefault) {
                // Diğer tüm versiyonların default'ını kaldır
                $db->prepare('UPDATE api_versions SET is_default = 0')->execute();
            }
            $updates[] = 'is_default = ?';
            $params[] = (int) $isDefault;
        }
        if (!empty($changelog)) { $updates[] = 'changelog = ?'; $params[] = $changelog; }
        if ($deprecatedAt !== null) { $updates[] = 'deprecated_at = ?'; $params[] = $deprecatedAt; }
        if ($sunsetAt !== null) { $updates[] = 'sunset_at = ?'; $params[] = $sunsetAt; }

        if (!empty($updates)) {
            $params[] = $version;
            $stmt = $db->prepare('UPDATE api_versions SET ' . implode(', ', $updates) . ' WHERE version = ?');
            $stmt->execute($params);
        }
    }

    auditLog('api_version.updated', 'admin', ['version' => $version]);

    sendResponse(['success' => true, 'message' => "API versiyon güncellendi: {$version}"]);
}

// ============================================================
// Yardımcı: Webhook tetikleme (async - fire and forget)
// ============================================================
function triggerWebhook(string $event, array $payload): void
{
    try {
        $db   = getDBConnection();
        $stmt = $db->prepare(
            'SELECT id, url, secret FROM webhooks WHERE is_active = 1 AND JSON_CONTAINS(events, ?)'
        );
        $stmt->execute([json_encode($event)]);
        $webhooks = $stmt->fetchAll();

        foreach ($webhooks as $wh) {
            $body      = json_encode(['event' => $event, 'data' => $payload, 'timestamp' => date('c')], JSON_UNESCAPED_UNICODE);
            $signature = $wh['secret'] ? hash_hmac('sha256', $body, $wh['secret']) : '';

            // Non-blocking HTTP POST (fire and forget)
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/json\r\nX-Webhook-Signature: {$signature}\r\n",
                    'content' => $body,
                    'timeout' => 5,
                ],
            ]);

            @file_get_contents($wh['url'], false, $context);

            $stmt2 = $db->prepare('UPDATE webhooks SET last_triggered_at = NOW() WHERE id = ?');
            $stmt2->execute([$wh['id']]);
        }
    } catch (\Throwable $e) {
        error_log('Webhook error: ' . $e->getMessage());
    }
}
