<?php
declare(strict_types=1);

/**
 * Cron Jobs - Zamanlanmış görevler
 *
 * Crontab'a ekleyin:
 * * * * * * php /path/to/web/cron.php >> /var/log/smsgateway-cron.log 2>&1
 */

require_once __DIR__ . '/config.php';

$db = getDBConnection();

// ============================================================
// 1. Eski rate limit kayıtlarını temizle (her çalışmada)
// ============================================================
$stmt = $db->prepare('DELETE FROM rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL 5 MINUTE)');
$stmt->execute();
$cleaned = $stmt->rowCount();
if ($cleaned > 0) {
    echo date('Y-m-d H:i:s') . " - Rate limit temizlendi: {$cleaned} kayıt\n";
}

// ============================================================
// 2. Başarısız SMS'leri yeniden dene (retry mekanizması)
// ============================================================
$stmt = $db->prepare(
    'UPDATE sms_requests
     SET status = \'pending\'
     WHERE status = \'failed\'
     AND retry_count < max_retries
     AND next_retry_at IS NOT NULL
     AND next_retry_at <= NOW()'
);
$stmt->execute();
$retried = $stmt->rowCount();
if ($retried > 0) {
    echo date('Y-m-d H:i:s') . " - Yeniden deneme kuyruğuna eklendi: {$retried} SMS\n";
}

// ============================================================
// 3. "processing" durumunda takılı kalan SMS'leri serbest bırak (5dk+)
// ============================================================
$stmt = $db->prepare(
    'UPDATE sms_requests
     SET status = \'pending\'
     WHERE status = \'processing\'
     AND updated_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)'
);
$stmt->execute();
$unstuck = $stmt->rowCount();
if ($unstuck > 0) {
    echo date('Y-m-d H:i:s') . " - Takılı SMS serbest bırakıldı: {$unstuck}\n";
}

// ============================================================
// 4. Offline cihazları tespit et ve uyar
// ============================================================
$threshold = defined('DEVICE_OFFLINE_THRESHOLD') ? DEVICE_OFFLINE_THRESHOLD : 300;
$stmt = $db->prepare(
    'SELECT device_id, device_name, last_seen,
            TIMESTAMPDIFF(SECOND, last_seen, NOW()) as seconds_offline
     FROM devices
     WHERE deleted_at IS NULL AND is_active = 1
     AND last_seen IS NOT NULL
     AND TIMESTAMPDIFF(SECOND, last_seen, NOW()) > ?'
);
$stmt->execute([$threshold]);
$offlineDevices = $stmt->fetchAll();

foreach ($offlineDevices as $device) {
    echo date('Y-m-d H:i:s') . " - Offline cihaz: {$device['device_id']} ({$device['seconds_offline']}s)\n";
}

// ============================================================
// 5. Eski SMS kayıtlarını arşivle/sil (90 gün+)
// ============================================================
$stmt = $db->prepare(
    'DELETE FROM sms_requests
     WHERE status IN (\'sent\', \'delivered\', \'cancelled\', \'failed\')
     AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)
     AND retry_count >= max_retries'
);
$stmt->execute();
$archived = $stmt->rowCount();
if ($archived > 0) {
    echo date('Y-m-d H:i:s') . " - Arşivlendi/silindi: {$archived} eski SMS\n";
}

// ============================================================
// 6. Eski audit log kayıtlarını temizle (180 gün+)
// ============================================================
$stmt = $db->prepare('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 180 DAY)');
$stmt->execute();
$logsCleaned = $stmt->rowCount();
if ($logsCleaned > 0) {
    echo date('Y-m-d H:i:s') . " - Audit log temizlendi: {$logsCleaned} kayıt\n";
}

// ============================================================
// 7. Günlük istatistikleri güncelle
// ============================================================
$stmt = $db->prepare(
    'INSERT INTO daily_stats (stat_date, device_id, total_sent, total_failed, total_delivered, total_pending)
     SELECT CURDATE(), device_id,
            SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END),
            SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END),
            SUM(CASE WHEN status = \'delivered\' THEN 1 ELSE 0 END),
            SUM(CASE WHEN status = \'pending\' THEN 1 ELSE 0 END)
     FROM sms_requests
     WHERE DATE(created_at) = CURDATE()
     GROUP BY device_id
     ON DUPLICATE KEY UPDATE
        total_sent = VALUES(total_sent),
        total_failed = VALUES(total_failed),
        total_delivered = VALUES(total_delivered),
        total_pending = VALUES(total_pending)'
);
$stmt->execute();

// ============================================================
// 8. Saatlik istatistikleri güncelle (Heatmap için)
// ============================================================
$stmt = $db->prepare(
    'INSERT INTO hourly_stats (stat_date, stat_hour, device_id, sent_count, failed_count)
     SELECT DATE(created_at), HOUR(created_at), device_id,
            SUM(CASE WHEN status = \'sent\' THEN 1 ELSE 0 END),
            SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END)
     FROM sms_requests
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
     GROUP BY DATE(created_at), HOUR(created_at), device_id
     ON DUPLICATE KEY UPDATE
        sent_count = VALUES(sent_count),
        failed_count = VALUES(failed_count)'
);
$stmt->execute();

// ============================================================
// 9. Anomali tespiti
// ============================================================
$stmt = $db->prepare('SELECT * FROM anomaly_rules WHERE is_active = 1');
$stmt->execute();
$anomalyRules = $stmt->fetchAll();

foreach ($anomalyRules as $rule) {
    $detected = false;
    $details  = [];

    switch ($rule['rule_type']) {
        case 'spike':
            $stmt2 = $db->prepare(
                'SELECT COUNT(*) as cnt FROM sms_requests
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
            );
            $stmt2->execute([$rule['time_window_minutes']]);
            $cnt = (int) $stmt2->fetch()['cnt'];
            if ($cnt >= (int) $rule['threshold_value']) {
                $detected = true;
                $details  = ['count' => $cnt, 'window_minutes' => $rule['time_window_minutes']];
            }
            break;

        case 'same_number':
            $stmt2 = $db->prepare(
                'SELECT phone_number, COUNT(*) as cnt FROM sms_requests
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)
                 GROUP BY phone_number HAVING cnt >= ?'
            );
            $stmt2->execute([$rule['time_window_minutes'], $rule['threshold_value']]);
            $floods = $stmt2->fetchAll();
            if (!empty($floods)) {
                $detected = true;
                $details  = ['numbers' => $floods];
            }
            break;

        case 'high_fail_rate':
            $stmt2 = $db->prepare(
                'SELECT device_id,
                        COUNT(*) as total,
                        SUM(CASE WHEN status = \'failed\' THEN 1 ELSE 0 END) as failed
                 FROM sms_requests
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE) AND status IN (\'sent\', \'failed\')
                 GROUP BY device_id
                 HAVING total > 0 AND (failed * 100 / total) >= ?'
            );
            $stmt2->execute([$rule['time_window_minutes'], $rule['threshold_value']]);
            $highFail = $stmt2->fetchAll();
            if (!empty($highFail)) {
                $detected = true;
                $details  = ['devices' => $highFail];
            }
            break;

        case 'night_activity':
            $hour = (int) date('H');
            if ($hour >= 23 || $hour < 6) {
                $stmt2 = $db->prepare(
                    'SELECT COUNT(*) as cnt FROM sms_requests
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)'
                );
                $stmt2->execute([$rule['time_window_minutes']]);
                $cnt = (int) $stmt2->fetch()['cnt'];
                if ($cnt >= (int) $rule['threshold_value']) {
                    $detected = true;
                    $details  = ['count' => $cnt, 'hour' => $hour];
                }
            }
            break;
    }

    if ($detected) {
        // Aynı kural için son 15dk'da olay varsa tekrar oluşturma
        $stmt2 = $db->prepare(
            'SELECT 1 FROM anomaly_events WHERE rule_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE) LIMIT 1'
        );
        $stmt2->execute([$rule['id']]);
        if (!$stmt2->fetch()) {
            $stmt2 = $db->prepare(
                'INSERT INTO anomaly_events (rule_id, details, action_taken) VALUES (?, ?, ?)'
            );
            $stmt2->execute([$rule['id'], json_encode($details, JSON_UNESCAPED_UNICODE), $rule['action_type']]);
            echo date('Y-m-d H:i:s') . " - Anomali tespit: {$rule['rule_name']} [{$rule['action_type']}]\n";

            // Aksiyon uygula
            if ($rule['action_type'] === 'block_device' && isset($details['devices'])) {
                foreach ($details['devices'] as $d) {
                    $stmt2 = $db->prepare('UPDATE devices SET is_active = 0 WHERE device_id = ?');
                    $stmt2->execute([$d['device_id']]);
                }
            }
        }
    }
}

// ============================================================
// 10. Uptime kontrolleri
// ============================================================
// API uptime
$apiStart = microtime(true);
$apiUp    = true;
try {
    $stmt = $db->prepare('SELECT 1');
    $stmt->execute();
} catch (\Throwable $e) {
    $apiUp = false;
}
$apiMs = (int) ((microtime(true) - $apiStart) * 1000);

$stmt = $db->prepare('INSERT INTO uptime_checks (check_type, target_id, is_up, response_time_ms) VALUES (?, ?, ?, ?)');
$stmt->execute(['api', 'main', $apiUp ? 1 : 0, $apiMs]);

// Cihaz uptime
$stmt = $db->prepare('SELECT device_id, last_seen FROM devices WHERE deleted_at IS NULL AND is_active = 1');
$stmt->execute();
$activeDevices = $stmt->fetchAll();

$stmtInsert = $db->prepare('INSERT INTO uptime_checks (check_type, target_id, is_up, response_time_ms) VALUES (?, ?, ?, ?)');
foreach ($activeDevices as $dev) {
    $isUp = $dev['last_seen'] !== null && (time() - strtotime($dev['last_seen'])) < $threshold;
    $stmtInsert->execute(['device', $dev['device_id'], $isUp ? 1 : 0, null]);
}

// ============================================================
// 11. Cihaz performans skoru hesaplama
// ============================================================
$stmt = $db->prepare(
    'SELECT d.device_id,
            COALESCE(d.total_sent, 0) as total_sent,
            COALESCE(d.total_failed, 0) as total_failed,
            d.last_seen, d.is_active
     FROM devices d WHERE d.deleted_at IS NULL'
);
$stmt->execute();
$allDevices = $stmt->fetchAll();

$stmtUpdateScore = $db->prepare('UPDATE devices SET performance_score = ?, total_sent = ?, total_failed = ? WHERE device_id = ?');
$stmtHistory     = $db->prepare('INSERT INTO device_score_history (device_id, score, success_rate, uptime_pct) VALUES (?, ?, ?, ?)');

foreach ($allDevices as $dev) {
    // Son 24 saatlik istatistikler
    $stmt2 = $db->prepare(
        'SELECT COUNT(CASE WHEN status = \'sent\' THEN 1 END) as sent,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as failed
         FROM sms_requests WHERE device_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $stmt2->execute([$dev['device_id']]);
    $stats24h = $stmt2->fetch();

    $sent24   = (int) $stats24h['sent'];
    $failed24 = (int) $stats24h['failed'];
    $total24  = $sent24 + $failed24;

    $successRate = $total24 > 0 ? ($sent24 / $total24) * 100 : 100;

    // Uptime yüzdesi
    $stmt2 = $db->prepare(
        'SELECT COUNT(*) as total, SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_count
         FROM uptime_checks WHERE check_type = \'device\' AND target_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)'
    );
    $stmt2->execute([$dev['device_id']]);
    $uptimeData = $stmt2->fetch();
    $uptimePct  = (int) $uptimeData['total'] > 0
        ? ((int) $uptimeData['up_count'] / (int) $uptimeData['total']) * 100
        : 100;

    // Skor hesaplama (weighted)
    $score = (int) round(($successRate * 0.6) + ($uptimePct * 0.4));
    $score = max(0, min(100, $score));

    // Toplam istatistikleri güncelle
    $stmt2 = $db->prepare(
        'SELECT COUNT(CASE WHEN status = \'sent\' THEN 1 END) as ts,
                COUNT(CASE WHEN status = \'failed\' THEN 1 END) as tf
         FROM sms_requests WHERE device_id = ?'
    );
    $stmt2->execute([$dev['device_id']]);
    $allStats = $stmt2->fetch();

    $stmtUpdateScore->execute([$score, (int) $allStats['ts'], (int) $allStats['tf'], $dev['device_id']]);
    $stmtHistory->execute([$dev['device_id'], $score, round($successRate, 2), round($uptimePct, 2)]);
}

// ============================================================
// 12. Maliyet kayıtlarını güncelle
// ============================================================
$stmt = $db->prepare(
    'INSERT INTO cost_records (record_date, device_id, sms_count, total_cost)
     SELECT CURDATE(), sr.device_id, COUNT(*), COUNT(*) * COALESCE(d.cost_per_sms, 0)
     FROM sms_requests sr
     JOIN devices d ON sr.device_id = d.device_id
     WHERE DATE(sr.sent_at) = CURDATE() AND sr.status = \'sent\'
     GROUP BY sr.device_id
     ON DUPLICATE KEY UPDATE
        sms_count = VALUES(sms_count),
        total_cost = VALUES(total_cost)'
);
$stmt->execute();

// ============================================================
// 13. Süresi dolmuş OTP kodlarını temizle (24 saat+)
// ============================================================
$stmt = $db->prepare('DELETE FROM otp_codes WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)');
$stmt->execute();

// ============================================================
// 14. Süresi dolmuş kısa linkleri temizle (30 gün+)
// ============================================================
$stmt = $db->prepare('DELETE FROM short_links WHERE expires_at IS NOT NULL AND expires_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
$stmt->execute();

// ============================================================
// 15. Eski uptime kayıtlarını temizle (30 gün+)
// ============================================================
$stmt = $db->prepare('DELETE FROM uptime_checks WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
$stmt->execute();

// ============================================================
// 16. Eski skor geçmişini temizle (90 gün+)
// ============================================================
$stmt = $db->prepare('DELETE FROM device_score_history WHERE recorded_at < DATE_SUB(NOW(), INTERVAL 90 DAY)');
$stmt->execute();

// ============================================================
// 17. Süresi dolmuş API key'leri deaktif et
// ============================================================
$stmt = $db->prepare('UPDATE api_key_history SET is_active = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW() AND is_active = 1');
$stmt->execute();
$expiredKeys = $stmt->rowCount();
if ($expiredKeys > 0) {
    echo date('Y-m-d H:i:s') . " - Süresi dolmuş API key: {$expiredKeys}\n";
}

// ============================================================
// 18. Otomatik ölçeklendirme (Auto-Scaling)
// ============================================================
$stmt = $db->prepare('SELECT * FROM autoscale_rules WHERE is_active = 1');
$stmt->execute();
$autoscaleRules = $stmt->fetchAll();

foreach ($autoscaleRules as $rule) {
    // Cooldown kontrolü
    if ($rule['last_action_at'] !== null) {
        $lastAction = strtotime($rule['last_action_at']);
        if ((time() - $lastAction) < ($rule['cooldown_minutes'] * 60)) {
            continue;
        }
    }

    $metricValue = 0;

    switch ($rule['metric']) {
        case 'queue_size':
            $stmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM sms_requests WHERE status = 'pending'");
            $stmt2->execute();
            $metricValue = (int) $stmt2->fetch()['cnt'];
            break;

        case 'fail_rate':
            $stmt2 = $db->prepare(
                "SELECT COUNT(*) as total,
                        SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
                 FROM sms_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE) AND status IN ('sent','failed')"
            );
            $stmt2->execute();
            $row = $stmt2->fetch();
            $metricValue = (int) $row['total'] > 0 ? round(((int) $row['failed'] / (int) $row['total']) * 100) : 0;
            break;

        case 'throughput':
            $stmt2 = $db->prepare(
                "SELECT COUNT(*) as cnt FROM sms_requests WHERE status = 'sent' AND sent_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)"
            );
            $stmt2->execute();
            $metricValue = (int) $stmt2->fetch()['cnt'];
            break;

        case 'response_time':
            $stmt2 = $db->prepare(
                "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, sent_at)) as avg_sec
                 FROM sms_requests WHERE status = 'sent' AND sent_at IS NOT NULL AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
            );
            $stmt2->execute();
            $metricValue = (int) round((float) ($stmt2->fetch()['avg_sec'] ?? 0));
            break;
    }

    // Aktif cihaz sayısı
    $stmt2 = $db->prepare("SELECT COUNT(*) as cnt FROM devices WHERE is_active = 1 AND deleted_at IS NULL");
    $stmt2->execute();
    $activeCount = (int) $stmt2->fetch()['cnt'];

    $action = null;
    $targetDevice = null;

    // Scale Up kontrolü
    if ($metricValue >= $rule['scale_up_threshold'] && $activeCount < $rule['max_devices']) {
        // En yüksek skorlu pasif cihazı aktifleştir
        $stmt2 = $db->prepare(
            "SELECT device_id FROM devices WHERE is_active = 0 AND deleted_at IS NULL ORDER BY performance_score DESC LIMIT 1"
        );
        $stmt2->execute();
        $device = $stmt2->fetch();

        if ($device) {
            $stmt2 = $db->prepare('UPDATE devices SET is_active = 1 WHERE device_id = ?');
            $stmt2->execute([$device['device_id']]);
            $action = 'scale_up';
            $targetDevice = $device['device_id'];
            echo date('Y-m-d H:i:s') . " - Auto-Scale UP: {$device['device_id']} aktifleştirildi (metric: {$metricValue})\n";
        }
    }

    // Scale Down kontrolü
    if ($metricValue <= $rule['scale_down_threshold'] && $activeCount > $rule['min_devices']) {
        // En düşük skorlu aktif cihazı devre dışı bırak
        $stmt2 = $db->prepare(
            "SELECT device_id FROM devices WHERE is_active = 1 AND deleted_at IS NULL ORDER BY performance_score ASC LIMIT 1"
        );
        $stmt2->execute();
        $device = $stmt2->fetch();

        if ($device) {
            $stmt2 = $db->prepare('UPDATE devices SET is_active = 0 WHERE device_id = ?');
            $stmt2->execute([$device['device_id']]);
            $action = 'scale_down';
            $targetDevice = $device['device_id'];
            echo date('Y-m-d H:i:s') . " - Auto-Scale DOWN: {$device['device_id']} devre dışı bırakıldı (metric: {$metricValue})\n";
        }
    }

    if ($action !== null) {
        // Geçmişe kaydet
        $stmt2 = $db->prepare(
            'INSERT INTO autoscale_history (rule_id, action, device_id, metric_value, threshold_value, active_device_count, details)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $thresholdUsed = $action === 'scale_up' ? $rule['scale_up_threshold'] : $rule['scale_down_threshold'];
        $stmt2->execute([
            $rule['id'], $action, $targetDevice, $metricValue, $thresholdUsed, $activeCount,
            json_encode(['metric' => $rule['metric'], 'rule_name' => $rule['rule_name']])
        ]);

        // Kuralı güncelle
        $stmt2 = $db->prepare('UPDATE autoscale_rules SET last_action_at = NOW(), last_action = ? WHERE id = ?');
        $stmt2->execute([$action, $rule['id']]);
    }
}

// ============================================================
// 19. Süresi dolmuş cihaz komutlarını temizle
// ============================================================
$stmt = $db->prepare("UPDATE device_commands SET status = 'expired' WHERE status = 'pending' AND expires_at IS NOT NULL AND expires_at < NOW()");
$stmt->execute();
$expiredCmds = $stmt->rowCount();
if ($expiredCmds > 0) {
    echo date('Y-m-d H:i:s') . " - Süresi dolmuş komut: {$expiredCmds}\n";
}

// ============================================================
// 20. Sistem durumu otomatik güncelleme
// ============================================================
// API durumu
$apiCheckStmt = $db->prepare(
    "SELECT COUNT(*) as total, SUM(CASE WHEN is_up = 1 THEN 1 ELSE 0 END) as up_cnt
     FROM uptime_checks WHERE check_type = 'api' AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
);
$apiCheckStmt->execute();
$apiCheck = $apiCheckStmt->fetch();
$apiUpPct = (int) $apiCheck['total'] > 0 ? ((int) $apiCheck['up_cnt'] / (int) $apiCheck['total']) * 100 : 100;

$apiStatus = 'operational';
if ($apiUpPct < 50) $apiStatus = 'major_outage';
elseif ($apiUpPct < 80) $apiStatus = 'partial_outage';
elseif ($apiUpPct < 95) $apiStatus = 'degraded';

$stmt = $db->prepare("UPDATE system_status SET status = ?, updated_by = 'system' WHERE component = 'api'");
$stmt->execute([$apiStatus]);

// Cihaz ağı durumu
$deviceCheckStmt = $db->prepare(
    "SELECT COUNT(*) as total, SUM(CASE WHEN is_active = 1 AND last_seen >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END) as online
     FROM devices WHERE deleted_at IS NULL"
);
$deviceCheckStmt->execute();
$deviceCheck = $deviceCheckStmt->fetch();
$deviceOnlinePct = (int) $deviceCheck['total'] > 0 ? ((int) $deviceCheck['online'] / (int) $deviceCheck['total']) * 100 : 100;

$deviceStatus = 'operational';
if ($deviceOnlinePct < 30) $deviceStatus = 'major_outage';
elseif ($deviceOnlinePct < 60) $deviceStatus = 'partial_outage';
elseif ($deviceOnlinePct < 80) $deviceStatus = 'degraded';

$stmt = $db->prepare("UPDATE system_status SET status = ?, updated_by = 'system' WHERE component = 'device_network'");
$stmt->execute([$deviceStatus]);

// SMS gönderim durumu
$smsCheckStmt = $db->prepare(
    "SELECT COUNT(*) as total,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
     FROM sms_requests WHERE created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)"
);
$smsCheckStmt->execute();
$smsCheck = $smsCheckStmt->fetch();
$smsFailPct = (int) $smsCheck['total'] > 0 ? ((int) $smsCheck['failed'] / (int) $smsCheck['total']) * 100 : 0;

$smsStatus = 'operational';
if ($smsFailPct > 50) $smsStatus = 'major_outage';
elseif ($smsFailPct > 30) $smsStatus = 'partial_outage';
elseif ($smsFailPct > 15) $smsStatus = 'degraded';

$stmt = $db->prepare("UPDATE system_status SET status = ?, updated_by = 'system' WHERE component = 'sms_sending'");
$stmt->execute([$smsStatus]);

echo date('Y-m-d H:i:s') . " - Cron tamamlandı\n";
