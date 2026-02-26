-- SMS Gateway v3.0 - Veritabanı Şeması
-- Tüm tablolar, indeksler, kısıtlamalar

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Kullanıcılar (Multi-Tenant + Admin)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user', 'viewer') NOT NULL DEFAULT 'user',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    api_token_hash VARCHAR(64) NULL,
    api_token_prefix VARCHAR(8) NULL,
    max_devices INT NOT NULL DEFAULT 5,
    max_sms_per_day INT NOT NULL DEFAULT 1000,
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    totp_secret VARCHAR(64) NULL COMMENT '2FA TOTP secret key',
    totp_enabled TINYINT(1) NOT NULL DEFAULT 0,
    totp_recovery_codes JSON NULL,
    INDEX idx_role (role),
    INDEX idx_token_hash (api_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan admin kullanıcısı (şifre: admin123 - İLK GİRİŞTE DEĞİŞTİRİN)
INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$12$LJ3m4ys3Gqf.BP7Gn0lEJOyMR5hMdPCxkGJ7dB8mKOL0kqjFfG6e', 'admin');

-- ============================================================
-- Cihazlar
-- ============================================================
CREATE TABLE IF NOT EXISTS devices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    device_id VARCHAR(255) UNIQUE NOT NULL,
    device_name VARCHAR(255) NULL,
    phone_number VARCHAR(20) NULL,
    api_key_hash VARCHAR(64) NOT NULL,
    api_key_prefix VARCHAR(8) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    max_sms_per_minute INT NOT NULL DEFAULT 10,
    sim_slot TINYINT NOT NULL DEFAULT 0 COMMENT '0=varsayilan, 1=SIM1, 2=SIM2',
    last_seen TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_device_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_api_key_hash (api_key_hash),
    INDEX idx_api_key_prefix (api_key_prefix),
    INDEX idx_last_seen (last_seen),
    performance_score TINYINT UNSIGNED NOT NULL DEFAULT 100 COMMENT 'Cihaz performans skoru 0-100',
    total_sent INT NOT NULL DEFAULT 0,
    total_failed INT NOT NULL DEFAULT 0,
    weight INT NOT NULL DEFAULT 100 COMMENT 'Yük dengeleme ağırlığı 1-100',
    cost_per_sms DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    imei VARCHAR(20) NULL COMMENT 'IMEI numarası',
    imei2 VARCHAR(20) NULL COMMENT 'İkinci SIM IMEI',
    android_version VARCHAR(20) NULL COMMENT 'Android sürümü',
    sdk_version INT NULL COMMENT 'Android SDK versiyonu',
    device_model VARCHAR(100) NULL COMMENT 'Cihaz modeli',
    device_brand VARCHAR(50) NULL COMMENT 'Marka',
    device_manufacturer VARCHAR(50) NULL COMMENT 'Üretici',
    hardware_id VARCHAR(64) NULL COMMENT 'Hardware ID',
    board VARCHAR(50) NULL COMMENT 'Board adı',
    fingerprint VARCHAR(255) NULL COMMENT 'Build fingerprint',
    display_resolution VARCHAR(20) NULL COMMENT 'Ekran çözünürlüğü',
    app_version VARCHAR(20) NULL COMMENT 'Uygulama versiyonu',
    INDEX idx_active_deleted (is_active, deleted_at),
    INDEX idx_weight (weight)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SMS İstekleri
-- ============================================================
CREATE TABLE IF NOT EXISTS sms_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('pending', 'processing', 'sent', 'delivered', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    priority TINYINT UNSIGNED NOT NULL DEFAULT 5,
    batch_id VARCHAR(36) NULL COMMENT 'UUID toplu gönderim grubu',
    template_id INT NULL,
    sim_slot TINYINT NOT NULL DEFAULT 0,
    scheduled_at TIMESTAMP NULL,
    sent_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,
    retry_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
    max_retries TINYINT UNSIGNED NOT NULL DEFAULT 3,
    next_retry_at TIMESTAMP NULL,
    webhook_url VARCHAR(500) NULL,
    cost DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    message_parts TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT 'Multipart SMS sayısı',
    encoding VARCHAR(10) NOT NULL DEFAULT 'gsm7' COMMENT 'gsm7 veya ucs2',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sms_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    CONSTRAINT chk_priority CHECK (priority BETWEEN 1 AND 10),
    INDEX idx_device_status (device_id, status),
    INDEX idx_status_priority (status, priority DESC, created_at ASC),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_retry (status, next_retry_at),
    INDEX idx_batch (batch_id),
    INDEX idx_created (created_at),
    INDEX idx_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SMS Şablonları
-- ============================================================
CREATE TABLE IF NOT EXISTS sms_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    variables JSON NULL COMMENT '["isim", "tarih", "kod"]',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Denetim Kayıtları (Audit Log)
-- ============================================================
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    actor_type ENUM('device', 'admin', 'system', 'anonymous') NOT NULL DEFAULT 'anonymous',
    actor_id VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    request_method VARCHAR(10) NULL,
    request_path VARCHAR(255) NULL,
    response_code SMALLINT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_created (created_at),
    INDEX idx_ip (ip_address)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Rate Limiting
-- ============================================================
CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(255) NOT NULL,
    endpoint VARCHAR(100) NOT NULL DEFAULT 'global',
    request_count INT NOT NULL DEFAULT 1,
    window_start TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_identifier_endpoint (identifier, endpoint),
    INDEX idx_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Webhook'lar
-- ============================================================
CREATE TABLE IF NOT EXISTS webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    url VARCHAR(500) NOT NULL,
    events JSON NOT NULL COMMENT '["sms.sent", "sms.failed", "device.offline"]',
    secret VARCHAR(64) NULL COMMENT 'HMAC imzalama anahtarı',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_triggered_at TIMESTAMP NULL,
    failure_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Kişiler
-- ============================================================
CREATE TABLE IF NOT EXISTS contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    group_name VARCHAR(100) NULL,
    metadata JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_phone (phone_number),
    INDEX idx_group (group_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Kara Liste
-- ============================================================
CREATE TABLE IF NOT EXISTS phone_blacklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    reason VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_phone (phone_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Günlük İstatistikler
-- ============================================================
CREATE TABLE IF NOT EXISTS daily_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    device_id VARCHAR(255) NULL,
    total_sent INT NOT NULL DEFAULT 0,
    total_failed INT NOT NULL DEFAULT 0,
    total_delivered INT NOT NULL DEFAULT 0,
    total_pending INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_date_device (stat_date, device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Gelen SMS'ler (Incoming SMS)
-- ============================================================
CREATE TABLE IF NOT EXISTS incoming_sms (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    sender_number VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    received_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    auto_reply_sent TINYINT(1) NOT NULL DEFAULT 0,
    related_sms_id INT NULL COMMENT 'Yanıtlanan giden SMS',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_incoming_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_device_received (device_id, received_at DESC),
    INDEX idx_sender (sender_number),
    INDEX idx_unread (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Otomatik Yanıt Kuralları
-- ============================================================
CREATE TABLE IF NOT EXISTS auto_reply_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    keyword VARCHAR(100) NOT NULL COMMENT 'Eşleşecek anahtar kelime',
    match_type ENUM('exact', 'contains', 'starts_with', 'regex') NOT NULL DEFAULT 'contains',
    reply_message TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    usage_count INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_keyword (keyword),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- OTP / Doğrulama Kodları
-- ============================================================
CREATE TABLE IF NOT EXISTS otp_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL,
    code VARCHAR(10) NOT NULL,
    purpose VARCHAR(50) NOT NULL DEFAULT 'verification',
    is_used TINYINT(1) NOT NULL DEFAULT 0,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 3,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    sms_request_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_phone_purpose (phone_number, purpose),
    INDEX idx_code (code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Kısa Linkler (URL Shortener)
-- ============================================================
CREATE TABLE IF NOT EXISTS short_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    short_code VARCHAR(10) UNIQUE NOT NULL,
    original_url VARCHAR(2000) NOT NULL,
    click_count INT NOT NULL DEFAULT 0,
    expires_at TIMESTAMP NULL,
    sms_request_id INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_short_code (short_code),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API Key Geçmişi (Key Rotation)
-- ============================================================
CREATE TABLE IF NOT EXISTS api_key_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    api_key_hash VARCHAR(64) NOT NULL,
    api_key_prefix VARCHAR(8) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    expires_at TIMESTAMP NULL,
    rotated_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device (device_id),
    INDEX idx_hash (api_key_hash),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Anomali Tespiti Kuralları
-- ============================================================
CREATE TABLE IF NOT EXISTS anomaly_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    rule_type ENUM('spike', 'flood', 'night_activity', 'high_fail_rate', 'same_number') NOT NULL,
    threshold_value INT NOT NULL COMMENT 'Eşik değeri',
    time_window_minutes INT NOT NULL DEFAULT 5 COMMENT 'Zaman penceresi (dk)',
    action_type ENUM('alert', 'block_device', 'block_number', 'throttle') NOT NULL DEFAULT 'alert',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan anomali kuralları
INSERT INTO anomaly_rules (rule_name, rule_type, threshold_value, time_window_minutes, action_type) VALUES
('Ani SMS Artışı', 'spike', 100, 5, 'alert'),
('Aynı Numaraya Flood', 'same_number', 5, 60, 'block_number'),
('Gece Aktivitesi', 'night_activity', 10, 60, 'alert'),
('Yüksek Başarısızlık', 'high_fail_rate', 50, 10, 'block_device');

-- ============================================================
-- Anomali Olayları
-- ============================================================
CREATE TABLE IF NOT EXISTS anomaly_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    device_id VARCHAR(255) NULL,
    phone_number VARCHAR(20) NULL,
    details JSON NULL,
    action_taken VARCHAR(50) NULL,
    is_resolved TINYINT(1) NOT NULL DEFAULT 0,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_anomaly_rule FOREIGN KEY (rule_id) REFERENCES anomaly_rules(id) ON DELETE CASCADE,
    INDEX idx_rule (rule_id),
    INDEX idx_created (created_at),
    INDEX idx_unresolved (is_resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Alert Yapılandırması
-- ============================================================
CREATE TABLE IF NOT EXISTS alert_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    channel_type ENUM('email', 'sms', 'telegram', 'slack', 'webhook') NOT NULL,
    config JSON NOT NULL COMMENT '{"email":"x@y.com"} veya {"chat_id":"123","bot_token":"xxx"}',
    events JSON NOT NULL COMMENT '["device.offline","anomaly.detected","sms.failed_batch"]',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Maliyet Kayıtları
-- ============================================================
CREATE TABLE IF NOT EXISTS cost_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    record_date DATE NOT NULL,
    device_id VARCHAR(255) NULL,
    sms_count INT NOT NULL DEFAULT 0,
    total_cost DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    currency VARCHAR(3) NOT NULL DEFAULT 'TRY',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_date_device (record_date, device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Saatlik İstatistikler (Heatmap için)
-- ============================================================
CREATE TABLE IF NOT EXISTS hourly_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    stat_hour TINYINT UNSIGNED NOT NULL,
    device_id VARCHAR(255) NULL,
    sent_count INT NOT NULL DEFAULT 0,
    failed_count INT NOT NULL DEFAULT 0,
    avg_send_time_ms INT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_date_hour_device (stat_date, stat_hour, device_id),
    INDEX idx_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Uptime Kayıtları
-- ============================================================
CREATE TABLE IF NOT EXISTS uptime_checks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    check_type ENUM('api', 'database', 'device') NOT NULL,
    target_id VARCHAR(255) NULL,
    is_up TINYINT(1) NOT NULL DEFAULT 1,
    response_time_ms INT NULL,
    error_message VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_type_target (check_type, target_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Cihaz Performans Geçmişi
-- ============================================================
CREATE TABLE IF NOT EXISTS device_score_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    score TINYINT UNSIGNED NOT NULL,
    success_rate DECIMAL(5,2) NOT NULL,
    avg_response_ms INT NULL,
    uptime_pct DECIMAL(5,2) NOT NULL DEFAULT 100.00,
    recorded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_device_date (device_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Uygulama Profilleri (Çoklu sunucu)
-- ============================================================
CREATE TABLE IF NOT EXISTS app_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    profile_name VARCHAR(100) NOT NULL,
    api_url VARCHAR(500) NOT NULL,
    device_id VARCHAR(255) NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Cihaz Uzaktan Komutları (Remote Commands)
-- ============================================================
CREATE TABLE IF NOT EXISTS device_commands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(255) NOT NULL,
    command_type ENUM('restart_service', 'sync_config', 'update_app', 'clear_queue', 'send_log', 'custom') NOT NULL,
    payload JSON NULL COMMENT 'Komut parametreleri',
    status ENUM('pending', 'sent', 'executed', 'failed', 'expired') NOT NULL DEFAULT 'pending',
    result JSON NULL COMMENT 'Komut sonucu',
    created_by VARCHAR(100) NOT NULL DEFAULT 'admin',
    sent_at TIMESTAMP NULL,
    executed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_command_device FOREIGN KEY (device_id) REFERENCES devices(device_id) ON DELETE CASCADE,
    INDEX idx_device_status (device_id, status),
    INDEX idx_status (status),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Otomatik Ölçeklendirme Kuralları (Auto-Scaling)
-- ============================================================
CREATE TABLE IF NOT EXISTS autoscale_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_name VARCHAR(100) NOT NULL,
    metric ENUM('queue_size', 'fail_rate', 'response_time', 'throughput') NOT NULL,
    scale_up_threshold INT NOT NULL COMMENT 'Bu değerin üstünde cihaz ekle',
    scale_down_threshold INT NOT NULL COMMENT 'Bu değerin altında cihaz çıkar',
    min_devices INT NOT NULL DEFAULT 1 COMMENT 'Minimum aktif cihaz sayısı',
    max_devices INT NOT NULL DEFAULT 10 COMMENT 'Maksimum aktif cihaz sayısı',
    cooldown_minutes INT NOT NULL DEFAULT 5 COMMENT 'Ölçekleme arası bekleme',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_action_at TIMESTAMP NULL,
    last_action VARCHAR(20) NULL COMMENT 'scale_up veya scale_down',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Otomatik Ölçeklendirme Geçmişi
-- ============================================================
CREATE TABLE IF NOT EXISTS autoscale_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_id INT NOT NULL,
    action ENUM('scale_up', 'scale_down') NOT NULL,
    device_id VARCHAR(255) NULL,
    metric_value DECIMAL(10,2) NOT NULL,
    threshold_value INT NOT NULL,
    active_device_count INT NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_autoscale_rule FOREIGN KEY (rule_id) REFERENCES autoscale_rules(id) ON DELETE CASCADE,
    INDEX idx_rule (rule_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Sistem Sağlığı Durumu (Public Status Page)
-- ============================================================
CREATE TABLE IF NOT EXISTS system_status (
    id INT AUTO_INCREMENT PRIMARY KEY,
    component VARCHAR(100) NOT NULL COMMENT 'api, database, sms_sending, device_network',
    status ENUM('operational', 'degraded', 'partial_outage', 'major_outage', 'maintenance') NOT NULL DEFAULT 'operational',
    message VARCHAR(500) NULL,
    is_public TINYINT(1) NOT NULL DEFAULT 1,
    updated_by VARCHAR(100) NOT NULL DEFAULT 'system',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_component (component)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan sistem bileşenleri
INSERT INTO system_status (component, status, message) VALUES
('api', 'operational', 'API servisi calisiyor'),
('database', 'operational', 'Veritabani baglantisi aktif'),
('sms_sending', 'operational', 'SMS gonderim servisi aktif'),
('device_network', 'operational', 'Cihaz agi aktif');

-- ============================================================
-- Sistem Durum Geçmişi (Status Incidents)
-- ============================================================
CREATE TABLE IF NOT EXISTS status_incidents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    severity ENUM('minor', 'major', 'critical') NOT NULL DEFAULT 'minor',
    status ENUM('investigating', 'identified', 'monitoring', 'resolved') NOT NULL DEFAULT 'investigating',
    affected_components JSON NULL COMMENT '["api","sms_sending"]',
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- API Versiyon Yapılandırması
-- ============================================================
CREATE TABLE IF NOT EXISTS api_versions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    version VARCHAR(10) NOT NULL COMMENT 'v1, v2, v3',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    deprecated_at TIMESTAMP NULL,
    sunset_at TIMESTAMP NULL COMMENT 'Bu tarihten sonra devre dışı',
    changelog TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_version (version),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan API versiyonları
INSERT INTO api_versions (version, is_active, is_default, changelog) VALUES
('v1', 1, 1, 'Ilk versiyon - temel SMS gonderim ve cihaz yonetimi'),
('v2', 1, 0, 'Gelismis ozellikler - OTP, anomali, auto-scaling, remote commands');

SET FOREIGN_KEY_CHECKS = 1;
