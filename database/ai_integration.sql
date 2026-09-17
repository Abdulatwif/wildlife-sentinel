-- ============================================
-- WILDLIFE SENTINEL — AI & CCTV INTEGRATION
-- Additive migration for XAMPP / MariaDB / MySQL
-- ------------------------------------------------------------
-- Safe to run on top of the master schema:
--   database/wildlife_sentinel.sql
--
-- Design principles:
--   • NEVER overwrites existing tables
--   • NEVER drops data
--   • Uses ALTER TABLE ... ADD COLUMN only when column is missing
--   • Column names / types match what the PHP code writes
--   • Re-runnable (idempotent)
-- ============================================

USE wildlife_sentinel;

-- ------------------------------------------------------------
-- Helper: add a column only if it doesn't exist
-- (MariaDB 10.2+ supports IF NOT EXISTS natively)
-- ------------------------------------------------------------
DELIMITER $$

DROP PROCEDURE IF EXISTS ws_add_column $$
CREATE PROCEDURE ws_add_column(
    IN p_table VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO v_exists
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND COLUMN_NAME  = p_column;

    IF v_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD COLUMN `', p_column, '` ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DROP PROCEDURE IF EXISTS ws_add_index $$
CREATE PROCEDURE ws_add_index(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_definition TEXT
)
BEGIN
    DECLARE v_exists INT DEFAULT 0;

    SELECT COUNT(*) INTO v_exists
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = p_table
      AND INDEX_NAME   = p_index;

    IF v_exists = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table, '` ADD ', p_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END $$

DELIMITER ;

-- ============================================
-- 1. CCTV CAMERAS
-- Ensures the columns the PHP code writes are present.
-- ============================================
CREATE TABLE IF NOT EXISTS cctv_cameras (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    camera_name VARCHAR(255) NOT NULL,
    camera_code VARCHAR(50) NULL,
    stream_url VARCHAR(500) NULL,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    camera_type ENUM('fixed','ptz','thermal','drone') DEFAULT 'fixed',
    resolution VARCHAR(20) DEFAULT '1080p',
    is_active TINYINT(1) DEFAULT 1,
    is_recording TINYINT(1) DEFAULT 0,
    last_seen DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add columns that admin/cctv-cameras.php or the AI engine expect
CALL ws_add_column('cctv_cameras','ip_address',      'VARCHAR(45) NULL');
CALL ws_add_column('cctv_cameras','port',            'INT(11) DEFAULT 554');
CALL ws_add_column('cctv_cameras','username',        'VARCHAR(100) NULL');
CALL ws_add_column('cctv_cameras','password',        'VARCHAR(255) NULL');
CALL ws_add_column('cctv_cameras','recording_storage_days','INT DEFAULT 30');
CALL ws_add_column('cctv_cameras','last_ping',       'DATETIME NULL');
CALL ws_add_column('cctv_cameras','status',          "ENUM('online','offline','maintenance','error') DEFAULT 'offline'");
CALL ws_add_column('cctv_cameras','ai_enabled',      'TINYINT(1) DEFAULT 1');
CALL ws_add_column('cctv_cameras','motion_detection','TINYINT(1) DEFAULT 1');
CALL ws_add_column('cctv_cameras','human_detection', 'TINYINT(1) DEFAULT 1');
CALL ws_add_column('cctv_cameras','animal_detection','TINYINT(1) DEFAULT 1');
CALL ws_add_column('cctv_cameras','vehicle_detection','TINYINT(1) DEFAULT 0');
CALL ws_add_column('cctv_cameras','detection_sensitivity','INT DEFAULT 50');
CALL ws_add_column('cctv_cameras','created_by',      'INT(11) NULL');
CALL ws_add_column('cctv_cameras','updated_at',      'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- ============================================
-- 2. AI DETECTIONS (legacy table)
-- The v2 table (ai_detections_v2) is created by the master schema.
-- This keeps the legacy table compatible with old code paths.
-- ============================================
CREATE TABLE IF NOT EXISTS ai_detections (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    camera_id INT(11) NULL,
    zone_id INT(11) NOT NULL,
    detection_type ENUM('human','animal','vehicle','fire','gunshot','unknown') NOT NULL,
    confidence DECIMAL(4,3) DEFAULT 0.700,
    is_threat TINYINT(1) DEFAULT 0,
    threat_level ENUM('low','medium','high','critical') DEFAULT 'low',
    snapshot_url VARCHAR(500) NULL,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_camera (camera_id),
    INDEX idx_detected (detected_at),
    INDEX idx_threat (is_threat)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ws_add_column('ai_detections','bounding_box','JSON NULL');
CALL ws_add_column('ai_detections','image_url',   'VARCHAR(500) NULL');
CALL ws_add_column('ai_detections','video_clip_url','VARCHAR(500) NULL');
CALL ws_add_column('ai_detections','is_resolved', 'TINYINT(1) DEFAULT 0');
CALL ws_add_column('ai_detections','resolved_by', 'INT(11) NULL');
CALL ws_add_column('ai_detections','resolved_at', 'DATETIME NULL');
CALL ws_add_column('ai_detections','incident_id', 'INT(11) NULL');
CALL ws_add_column('ai_detections','notes',       'TEXT NULL');
CALL ws_add_column('ai_detections','ai_model_version','VARCHAR(50) NULL');
CALL ws_add_column('ai_detections','processing_time_ms','INT NULL');

-- ============================================
-- 3. AI ALERTS
-- ============================================
CREATE TABLE IF NOT EXISTS ai_alerts (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    detection_id INT(11) NULL,
    zone_id INT(11) NOT NULL,
    alert_type ENUM('poaching_suspect','intruder','fire','gunshot','animal_distress','other') NOT NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT(11) NULL,
    acknowledged_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_ack (is_acknowledged)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ws_add_column('ai_alerts','is_resolved',      'TINYINT(1) DEFAULT 0');
CALL ws_add_column('ai_alerts','resolved_at',      'DATETIME NULL');
CALL ws_add_column('ai_alerts','escalated',        'TINYINT(1) DEFAULT 0');
CALL ws_add_column('ai_alerts','escalated_at',     'DATETIME NULL');
CALL ws_add_column('ai_alerts','notification_sent','TINYINT(1) DEFAULT 0');
CALL ws_add_column('ai_alerts','sms_sent',         'TINYINT(1) DEFAULT 0');
CALL ws_add_column('ai_alerts','alarm_triggered',  'TINYINT(1) DEFAULT 0');

-- ============================================
-- 4. ALARM SYSTEMS
-- ============================================
CREATE TABLE IF NOT EXISTS alarm_systems (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    alarm_name VARCHAR(255) NOT NULL,
    alarm_code VARCHAR(60) NULL,
    alarm_type ENUM('siren','bell','strobe','speaker','combined') DEFAULT 'siren',
    sound_url VARCHAR(500) NULL,
    sound_volume INT DEFAULT 80,
    siren_duration INT DEFAULT 180,
    trigger_delay_seconds INT DEFAULT 120,
    auto_sound_on_incident TINYINT(1) DEFAULT 1,
    api_endpoint VARCHAR(500) NULL,
    api_key VARCHAR(255) NULL,
    trigger_duration INT DEFAULT 60,
    auto_trigger TINYINT(1) DEFAULT 1,
    max_acknowledge_time_seconds INT DEFAULT 120,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_triggered DATETIME NULL,
    trigger_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ws_add_column('alarm_systems','ip_address','VARCHAR(45) NULL');
CALL ws_add_column('alarm_systems','port',      'INT(11) NULL');
CALL ws_add_column('alarm_systems','status',    "ENUM('online','offline','error') DEFAULT 'offline'");
CALL ws_add_column('alarm_systems','created_by','INT(11) NULL');

-- ============================================
-- 5. ALARM TRIGGERS
-- ============================================
CREATE TABLE IF NOT EXISTS alarm_triggers (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    alarm_id INT(11) NULL,
    alert_id INT(11) NULL,
    incident_id INT(11) NULL,
    zone_id INT(11) NOT NULL,
    triggered_by ENUM('ai_detection','manual','schedule','auto_incident','unacknowledged_incident','scheduled_test') DEFAULT 'manual',
    trigger_reason VARCHAR(255) NULL,
    triggered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    stopped_at DATETIME NULL,
    duration_seconds INT NULL,
    was_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT(11) NULL,
    acknowledged_at DATETIME NULL,
    INDEX idx_zone (zone_id),
    INDEX idx_alarm (alarm_id),
    INDEX idx_active (stopped_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CALL ws_add_column('alarm_triggers','notes','TEXT NULL');

-- ============================================
-- 6. SMS LOGS
-- IMPORTANT: column names must match what ws_log_sms() writes:
--   user_id, phone, message, message_type, incident_id, alert_id,
--   status, gateway_response, sent_at, created_at
-- ============================================
CREATE TABLE IF NOT EXISTS sms_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    message_type ENUM('general','incident','ai_alert','manpower','system','test','broadcast') DEFAULT 'general',
    incident_id INT(11) NULL,
    alert_id INT(11) NULL,
    status ENUM('pending','sent','failed','delivered') DEFAULT 'pending',
    provider VARCHAR(50) NULL,
    provider_ref VARCHAR(120) NULL,
    gateway_response TEXT NULL,
    segments TINYINT DEFAULT 1,
    sent_at DATETIME NULL,
    delivered_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_phone (phone),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If an older schema exists with different column names, add the ones we need
CALL ws_add_column('sms_logs','phone_number','VARCHAR(20) NULL');
CALL ws_add_column('sms_logs','related_incident_id','INT(11) NULL');
CALL ws_add_column('sms_logs','related_alert_id','INT(11) NULL');
CALL ws_add_column('sms_logs','provider_message_id','VARCHAR(255) NULL');
CALL ws_add_column('sms_logs','error_message','TEXT NULL');

-- ============================================
-- 7. SMS GATEWAY (provider config)
-- ============================================
CREATE TABLE IF NOT EXISTS sms_gateway (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NULL,
    provider_name VARCHAR(100) NOT NULL,
    provider_type ENUM('mtn','airtel','esms','twilio','africastalking','vonage','custom') DEFAULT 'custom',
    api_key VARCHAR(255) NULL,
    api_secret VARCHAR(255) NULL,
    sender_id VARCHAR(50) NULL,
    account_sid VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    sms_per_minute INT DEFAULT 30,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. SIMULATION CONTROLS
-- ============================================
CREATE TABLE IF NOT EXISTS simulation_controls (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    simulation_type ENUM('incident','ai_detection','alarm','sms','ranger_movement') NOT NULL,
    is_enabled TINYINT(1) DEFAULT 0,
    parameters JSON NULL,
    started_at DATETIME NULL,
    stopped_at DATETIME NULL,
    created_by INT(11) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_enabled (is_enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. SIMULATION EVENTS (per-page activity feed)
-- ============================================
CREATE TABLE IF NOT EXISTS simulation_events (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    actor_id INT(11) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    description TEXT NULL,
    payload TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_actor (actor_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. RANGER SIMULATION TRACKS
-- ============================================
CREATE TABLE IF NOT EXISTS ranger_simulation_tracks (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    ranger_id INT(11) NOT NULL,
    zone_id INT(11) NOT NULL,
    track_name VARCHAR(255) NULL,
    track_points JSON NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    loop_track TINYINT(1) DEFAULT 1,
    speed_multiplier DECIMAL(3,2) DEFAULT 1.00,
    started_at DATETIME NULL,
    last_position_index INT DEFAULT 0,
    created_by INT(11) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ranger (ranger_id),
    INDEX idx_zone (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. ZONE NOTIFICATION SETTINGS
-- Keyed on zone_id (matches zone-settings.php)
-- ============================================
CREATE TABLE IF NOT EXISTS zone_notification_settings (
    zone_id INT(11) PRIMARY KEY,
    sms_enabled TINYINT(1) DEFAULT 1,
    sms_incident_alerts TINYINT(1) DEFAULT 1,
    sms_acknowledgment TINYINT(1) DEFAULT 1,
    sms_manpower_requests TINYINT(1) DEFAULT 1,
    sms_alarm_triggers TINYINT(1) DEFAULT 1,
    email_enabled TINYINT(1) DEFAULT 0,
    push_enabled TINYINT(1) DEFAULT 1,
    alarm_enabled TINYINT(1) DEFAULT 1,
    alarm_delay_seconds INT DEFAULT 120,
    ai_detection_enabled TINYINT(1) DEFAULT 1,
    ai_confidence_threshold INT DEFAULT 70,
    auto_create_incidents TINYINT(1) DEFAULT 1,
    notify_rangers TINYINT(1) DEFAULT 1,
    notify_supervisors TINYINT(1) DEFAULT 1,
    notify_admin TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If an older schema exists with `id` PK and `zone_id` UNIQUE,
-- add the additional columns (existing ones are preserved)
CALL ws_add_column('zone_notification_settings','sms_incident_alerts','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','sms_acknowledgment','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','sms_manpower_requests','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','sms_alarm_triggers','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','email_enabled','TINYINT(1) DEFAULT 0');
CALL ws_add_column('zone_notification_settings','push_enabled','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','notify_rangers','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','notify_supervisors','TINYINT(1) DEFAULT 1');
CALL ws_add_column('zone_notification_settings','notify_admin','TINYINT(1) DEFAULT 1');

-- ============================================
-- 12. ZONE SYSTEM SETTINGS (per-zone config)
-- ============================================
CREATE TABLE IF NOT EXISTS zone_system_settings (
    zone_id INT(11) PRIMARY KEY,
    ai_enabled TINYINT(1) DEFAULT 1,
    ai_confidence_min INT DEFAULT 70,
    ai_auto_create_alert TINYINT(1) DEFAULT 1,
    ai_auto_trigger_alarm TINYINT(1) DEFAULT 0,
    ai_detection_types VARCHAR(255) DEFAULT 'human,animal,vehicle,fire,gunshot',
    cctv_retention_days INT DEFAULT 30,
    cctv_default_quality VARCHAR(20) DEFAULT '1080p',
    cctv_auto_record TINYINT(1) DEFAULT 1,
    cctv_snapshot_dir VARCHAR(255) DEFAULT 'uploads/cctv/',
    alarm_default_type VARCHAR(20) DEFAULT 'siren',
    alarm_siren_duration INT DEFAULT 180,
    alarm_auto_stop TINYINT(1) DEFAULT 1,
    alarm_sms_blast TINYINT(1) DEFAULT 1,
    notif_sms TINYINT(1) DEFAULT 1,
    notif_email TINYINT(1) DEFAULT 0,
    notif_push TINYINT(1) DEFAULT 1,
    notif_on_incident TINYINT(1) DEFAULT 1,
    notif_on_ai_alert TINYINT(1) DEFAULT 1,
    notif_on_alarm TINYINT(1) DEFAULT 1,
    notif_on_manpower TINYINT(1) DEFAULT 1,
    notif_offline_reminder TINYINT(1) DEFAULT 1,
    perm_rangers_ack TINYINT(1) DEFAULT 1,
    perm_rangers_trigger_alarm TINYINT(1) DEFAULT 0,
    perm_rangers_request_manpower TINYINT(1) DEFAULT 1,
    perm_scouts_report TINYINT(1) DEFAULT 1,
    perm_scouts_see_sensitive TINYINT(1) DEFAULT 0,
    perm_tourism_see_risk TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. ZONE AI SETTINGS (per-supervisor prefs)
-- ============================================
CREATE TABLE IF NOT EXISTS zone_ai_settings (
    zone_id INT(11) PRIMARY KEY,
    sound_alerts_enabled TINYINT(1) DEFAULT 1,
    auto_ack_low_risk TINYINT(1) DEFAULT 0,
    auto_ack_minutes INT DEFAULT 10,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. ADMIN AI SETTINGS
-- ============================================
CREATE TABLE IF NOT EXISTS admin_ai_settings (
    admin_id INT(11) PRIMARY KEY,
    sound_alerts_enabled TINYINT(1) DEFAULT 1,
    auto_ack_low_risk TINYINT(1) DEFAULT 0,
    auto_ack_minutes INT DEFAULT 10,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15. SMS MESSAGES (supervisor gateway outbox)
-- ============================================
CREATE TABLE IF NOT EXISTS sms_messages (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    zone_id         INT NOT NULL,
    sender_id       INT NOT NULL,
    recipient_id    INT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name  VARCHAR(100) NULL,
    message         TEXT NOT NULL,
    template_key    VARCHAR(50) NULL,
    status          ENUM('pending','sent','delivered','failed') DEFAULT 'pending',
    provider        VARCHAR(50) NULL,
    provider_ref    VARCHAR(120) NULL,
    error_message   TEXT NULL,
    segments        TINYINT DEFAULT 1,
    sent_at         DATETIME NULL,
    delivered_at    DATETIME NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PERFORMANCE INDEXES (idempotent)
-- ============================================
CALL ws_add_index('ai_detections','idx_threat_time', 'INDEX idx_threat_time (is_threat, detected_at)');
CALL ws_add_index('ai_alerts',    'idx_zone_time',   'INDEX idx_zone_time (zone_id, created_at)');
CALL ws_add_index('sms_logs',     'idx_status_time', 'INDEX idx_status_time (status, created_at)');

-- ============================================
-- SEED: ensure every active zone has a settings row
-- ============================================
INSERT INTO zone_notification_settings (zone_id)
SELECT id FROM zones WHERE is_active = 1
ON DUPLICATE KEY UPDATE zone_id = zone_id;

INSERT INTO zone_system_settings (zone_id)
SELECT id FROM zones WHERE is_active = 1
ON DUPLICATE KEY UPDATE zone_id = zone_id;

INSERT INTO zone_ai_settings (zone_id)
SELECT id FROM zones WHERE is_active = 1
ON DUPLICATE KEY UPDATE zone_id = zone_id;

-- ============================================
-- CLEANUP — drop the helper procedures
-- ============================================
DROP PROCEDURE IF EXISTS ws_add_column;
DROP PROCEDURE IF EXISTS ws_add_index;

-- ============================================
-- VERIFY INSTALLATION
-- ============================================
SELECT '✅ AI & CCTV Integration Migration applied' AS Status;

SELECT '📊 Tables present:' AS Info;
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
  AND TABLE_NAME IN (
    'cctv_cameras','ai_detections','ai_detections_v2','ai_alerts',
    'alarm_systems','alarm_triggers','sms_gateway','sms_logs','sms_messages',
    'simulation_controls','simulation_events','ranger_simulation_tracks',
    'zone_notification_settings','zone_system_settings','zone_ai_settings',
    'admin_ai_settings'
  )
ORDER BY TABLE_NAME;

SELECT '=========================================' AS '';
SELECT 'ZONE SETTINGS ROWS:' AS '';
SELECT
    (SELECT COUNT(*) FROM zone_notification_settings) AS notification_settings,
    (SELECT COUNT(*) FROM zone_system_settings)       AS system_settings,
    (SELECT COUNT(*) FROM zone_ai_settings)           AS ai_settings;
SELECT '=========================================' AS '';