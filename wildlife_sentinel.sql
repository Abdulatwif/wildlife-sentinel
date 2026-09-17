-- ============================================
-- WILDLIFE SENTINEL DATABASE
-- Complete Schema — XAMPP / MariaDB / MySQL edition
-- Includes AI Engine v2 upgrade
-- ------------------------------------------------------------
-- Verified working on:
--   • XAMPP 8.x (MariaDB 10.4+)
--   • XAMPP 7.x (MariaDB 10.2+)
--   • MySQL 5.7+ / 8.x
--   • WAMP, Laragon, MAMP
--
-- Import: phpMyAdmin → Import → choose this file → Go
--
-- Notes:
--   • NO DEFAULT USER ACCOUNTS — create admin via index.php
--   • SPATIAL INDEX on incidents.location_geojson is enabled
--   • All 9 views are real VIEWs
--   • Triggers + stored procedures included
--   • AI Engine v2 tables included
-- ============================================

CREATE DATABASE IF NOT EXISTS wildlife_sentinel
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE wildlife_sentinel;

SET FOREIGN_KEY_CHECKS = 0;

-- Drop views
DROP VIEW  IF EXISTS ai_recent_detections;
DROP VIEW  IF EXISTS ai_active_threats;
DROP VIEW  IF EXISTS supervisor_dashboard_summary;
DROP VIEW  IF EXISTS scout_status;
DROP VIEW  IF EXISTS zone_map_data;
DROP VIEW  IF EXISTS park_registration_status;
DROP VIEW  IF EXISTS unacknowledged_incidents;
DROP VIEW  IF EXISTS ranger_status;
DROP VIEW  IF EXISTS incident_summary;

-- Drop tables (children first)
DROP TABLE IF EXISTS ai_queue;
DROP TABLE IF EXISTS ai_telemetry;
DROP TABLE IF EXISTS ai_feedback;
DROP TABLE IF EXISTS ai_rules;
DROP TABLE IF EXISTS ai_thresholds;
DROP TABLE IF EXISTS ai_tracks;
DROP TABLE IF EXISTS ai_detections_v2;
DROP TABLE IF EXISTS ai_models;
DROP TABLE IF EXISTS sms_messages;
DROP TABLE IF EXISTS simulation_events;
DROP TABLE IF EXISTS simulation_controls;
DROP TABLE IF EXISTS zone_ai_settings;
DROP TABLE IF EXISTS admin_ai_settings;
DROP TABLE IF EXISTS sms_logs;
DROP TABLE IF EXISTS alarm_triggers;
DROP TABLE IF EXISTS alarm_systems;
DROP TABLE IF EXISTS ai_alerts;
DROP TABLE IF EXISTS ai_detections;
DROP TABLE IF EXISTS cctv_cameras;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS message_attachments;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS incident_assignments;
DROP TABLE IF EXISTS incident_responses;
DROP TABLE IF EXISTS incidents;
DROP TABLE IF EXISTS ranger_patrol_routes;
DROP TABLE IF EXISTS ranger_location_history;
DROP TABLE IF EXISTS ranger_live_tracking;
DROP TABLE IF EXISTS ranger_availability;
DROP TABLE IF EXISTS scout_location_history;
DROP TABLE IF EXISTS scout_live_tracking;
DROP TABLE IF EXISTS ai_anomalies;
DROP TABLE IF EXISTS zone_notification_settings;
DROP TABLE IF EXISTS zone_system_settings;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS lodges;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS zones;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- 1. ZONES
-- ============================================
CREATE TABLE zones (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    center_lat DECIMAL(10, 8) NULL,
    center_lng DECIMAL(11, 8) NULL,
    boundary_geojson JSON NULL,
    created_by INT(11) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    park_type ENUM('national_park','gma','other') DEFAULT 'other',
    park_code VARCHAR(50) UNIQUE NULL,
    is_registered TINYINT(1) DEFAULT 0,
    buffer_radius INT DEFAULT 500,
    boundary_center_lat DECIMAL(10, 8) NULL,
    boundary_center_lng DECIMAL(11, 8) NULL,
    area_km2 DECIMAL(12, 2) NULL,
    INDEX idx_active (is_active),
    INDEX idx_park_type (park_type),
    INDEX idx_is_registered (is_registered)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. USERS
-- ============================================
CREATE TABLE users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    phone VARCHAR(20) NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('scout','tourism','ranger','zone_supervisor','admin') NOT NULL,
    zone_id INT(11) NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_online TINYINT(1) DEFAULT 0,
    last_seen DATETIME NULL,
    last_online DATETIME NULL,
    is_on_duty TINYINT(1) DEFAULT 0,
    badge_number VARCHAR(50) NULL,
    fcm_token VARCHAR(255) NULL,
    profile_image VARCHAR(255) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    created_by INT(11) NULL,
    INDEX idx_role (role),
    INDEX idx_zone (zone_id),
    INDEX idx_active (is_active),
    INDEX idx_online (is_online),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. RANGER AVAILABILITY
-- ============================================
CREATE TABLE ranger_availability (
    ranger_id INT(11) PRIMARY KEY,
    is_available TINYINT(1) DEFAULT 1,
    current_incident_id INT(11) NULL,
    last_status_update DATETIME DEFAULT CURRENT_TIMESTAMP,
    shift_start TIME NULL,
    shift_end TIME NULL,
    days_available JSON NULL,
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. RANGER LIVE TRACKING
-- ============================================
CREATE TABLE ranger_live_tracking (
    ranger_id INT(11) PRIMARY KEY,
    current_lat DECIMAL(10, 8) NULL,
    current_lng DECIMAL(11, 8) NULL,
    heading DECIMAL(5, 2) NULL,
    speed DECIMAL(6, 2) NULL,
    last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_offline TINYINT(1) DEFAULT 0,
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4b. RANGER LOCATION HISTORY
-- ============================================
CREATE TABLE ranger_location_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ranger_id INT(11) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    heading DECIMAL(5, 2) DEFAULT 0,
    speed DECIMAL(6, 2) DEFAULT 0,
    incident_id INT(11) NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ranger_time (ranger_id, timestamp),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4c. SCOUT LIVE TRACKING
-- ============================================
CREATE TABLE scout_live_tracking (
    scout_id INT(11) PRIMARY KEY,
    current_lat DECIMAL(10, 8) NULL,
    current_lng DECIMAL(11, 8) NULL,
    last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_offline TINYINT(1) DEFAULT 0,
    FOREIGN KEY (scout_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4d. SCOUT LOCATION HISTORY
-- ============================================
CREATE TABLE scout_location_history (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    scout_id INT(11) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_scout_time (scout_id, timestamp),
    INDEX idx_timestamp (timestamp),
    FOREIGN KEY (scout_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4e. RANGER PATROL ROUTES
-- ============================================
CREATE TABLE ranger_patrol_routes (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    ranger_id INT(11) NOT NULL,
    zone_id INT(11) NOT NULL,
    route_name VARCHAR(255) NOT NULL,
    start_lat DECIMAL(10, 8) NOT NULL,
    start_lng DECIMAL(11, 8) NOT NULL,
    end_lat DECIMAL(10, 8) NULL,
    end_lng DECIMAL(11, 8) NULL,
    route_geojson JSON NULL,
    notes TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT(11) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ranger (ranger_id),
    INDEX idx_zone (zone_id),
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. INCIDENTS
-- ============================================
CREATE TABLE incidents (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    reporter_id INT(11) NOT NULL,
    reporter_type ENUM('scout','tourism') NOT NULL,
    zone_id INT(11) NULL,
    category ENUM('poaching','distressed_animal','human_wildlife_conflict','environmental_risk','other') NOT NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    description TEXT NULL,
    location_lat DECIMAL(10, 8) NOT NULL,
    location_lng DECIMAL(11, 8) NOT NULL,
    location_geojson POINT NOT NULL,
    media_urls JSON NULL,
    status ENUM('reported','acknowledged','in_progress','resolved','closed') DEFAULT 'reported',
    reported_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by INT(11) NULL,
    acknowledged_at DATETIME NULL,
    resolved_at DATETIME NULL,
    is_simulated TINYINT(1) DEFAULT 0,
    INDEX idx_zone (zone_id),
    INDEX idx_status (status),
    INDEX idx_severity (severity),
    INDEX idx_reported_at (reported_at),
    SPATIAL INDEX idx_location (location_geojson),
    FOREIGN KEY (reporter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. INCIDENT RESPONSES
-- ============================================
CREATE TABLE incident_responses (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    incident_id INT(11) NOT NULL,
    ranger_id INT(11) NOT NULL,
    status_update ENUM('arrived','investigating','resolved','escalated') NOT NULL,
    notes TEXT NULL,
    location_lat DECIMAL(10, 8) NULL,
    location_lng DECIMAL(11, 8) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_incident (incident_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. INCIDENT ASSIGNMENTS
-- ============================================
CREATE TABLE incident_assignments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    incident_id INT(11) NOT NULL,
    ranger_id INT(11) NOT NULL,
    assigned_by INT(11) NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','accepted','declined','completed') DEFAULT 'pending',
    notes TEXT NULL,
    INDEX idx_incident (incident_id),
    INDEX idx_ranger (ranger_id),
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE CASCADE,
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. MESSAGES
-- ============================================
CREATE TABLE messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(11) NOT NULL,
    recipient_id INT(11) NULL,
    incident_id INT(11) NULL,
    zone_id INT(11) NULL,
    message_type ENUM('manpower_request','status_update','general','emergency') DEFAULT 'general',
    subject VARCHAR(255) NULL,
    content TEXT NOT NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    is_broadcast TINYINT(1) DEFAULT 0,
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME NULL,
    requires_acknowledgment TINYINT(1) DEFAULT 0,
    acknowledged_at DATETIME NULL,
    parent_message_id INT(11) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_id),
    INDEX idx_recipient (recipient_id),
    INDEX idx_incident (incident_id),
    INDEX idx_zone (zone_id),
    INDEX idx_type (message_type),
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. MESSAGE ATTACHMENTS
-- ============================================
CREATE TABLE message_attachments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    message_id INT(11) NOT NULL,
    file_url VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. NOTIFICATIONS
-- ============================================
CREATE TABLE notifications (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    incident_id INT(11) NULL,
    message_id INT(11) NULL,
    type ENUM('new_incident','acknowledged','status_update','system_alert','new_message','manpower_request') NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT NULL,
    is_read TINYINT(1) DEFAULT 0,
    is_delivered TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    read_at DATETIME NULL,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. AUDIT LOGS
-- ============================================
CREATE TABLE audit_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NULL,
    action VARCHAR(100) NOT NULL,
    details JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. RATE LIMITS
-- ============================================
CREATE TABLE rate_limits (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    attempt_count INT(11) DEFAULT 1,
    first_attempt DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_key (ip_address, key_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. AI ANOMALIES
-- ============================================
CREATE TABLE ai_anomalies (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    type ENUM('ranger_out_of_bounds','ranger_stationary','patrol_deviation','scout_out_of_bounds','unusual_movement','unusual_cluster','other') NOT NULL,
    severity ENUM('low','medium','high','critical') DEFAULT 'medium',
    description TEXT NULL,
    confidence DECIMAL(4, 3) DEFAULT 0.700,
    location_lat DECIMAL(10, 8) NOT NULL,
    location_lng DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 200,
    ranger_id INT(11) NULL,
    scout_id INT(11) NULL,
    is_acknowledged TINYINT(1) DEFAULT 0,
    acknowledged_by INT(11) NULL,
    acknowledged_at DATETIME NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_time (zone_id, detected_at),
    INDEX idx_severity (severity),
    INDEX idx_type (type),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (ranger_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (scout_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 14. ZONE NOTIFICATION SETTINGS
-- ============================================
CREATE TABLE zone_notification_settings (
    zone_id INT(11) PRIMARY KEY,
    sms_enabled TINYINT(1) DEFAULT 1,
    alarm_enabled TINYINT(1) DEFAULT 1,
    ai_detection_enabled TINYINT(1) DEFAULT 1,
    alarm_delay_seconds INT DEFAULT 120,
    ai_confidence_threshold INT DEFAULT 70,
    auto_create_incidents TINYINT(1) DEFAULT 1,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 15. ZONE SYSTEM SETTINGS
-- ============================================
CREATE TABLE zone_system_settings (
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
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 16. USER PREFERENCES
-- ============================================
CREATE TABLE user_preferences (
    user_id INT(11) PRIMARY KEY,
    theme VARCHAR(20) DEFAULT 'light',
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Africa/Lusaka',
    date_format VARCHAR(50) DEFAULT 'M j, Y H:i',
    items_per_page INT DEFAULT 25,
    email_notifications TINYINT(1) DEFAULT 1,
    sms_notifications TINYINT(1) DEFAULT 1,
    push_notifications TINYINT(1) DEFAULT 1,
    notify_incident_updates TINYINT(1) DEFAULT 1,
    notify_ranger_responses TINYINT(1) DEFAULT 1,
    notify_safety_alerts TINYINT(1) DEFAULT 1,
    notify_nearby_incidents TINYINT(1) DEFAULT 1,
    notify_manpower TINYINT(1) DEFAULT 1,
    notify_alarms TINYINT(1) DEFAULT 1,
    quiet_hours_start TIME DEFAULT NULL,
    quiet_hours_end TIME DEFAULT NULL,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 17. LODGES / CAMPS
-- ============================================
CREATE TABLE lodges (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    location_lat DECIMAL(10, 8) NULL,
    location_lng DECIMAL(11, 8) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 18. SETTINGS (global admin settings)
-- ============================================
CREATE TABLE settings (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT(11) NULL,
    INDEX idx_group (setting_group),
    INDEX idx_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 19. CCTV CAMERAS
-- ============================================
CREATE TABLE cctv_cameras (
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
    INDEX idx_active (is_active),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 20. AI DETECTIONS (legacy)
-- ============================================
CREATE TABLE ai_detections (
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
    INDEX idx_threat (is_threat),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 21. AI ALERTS
-- ============================================
CREATE TABLE ai_alerts (
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
    INDEX idx_ack (is_acknowledged),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (detection_id) REFERENCES ai_detections(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 22. ALARM SYSTEMS
-- ============================================
CREATE TABLE alarm_systems (
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
    INDEX idx_active (is_active),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 23. ALARM TRIGGERS
-- ============================================
CREATE TABLE alarm_triggers (
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
    INDEX idx_alert (alert_id),
    INDEX idx_incident (incident_id),
    INDEX idx_active (stopped_at),
    FOREIGN KEY (alarm_id) REFERENCES alarm_systems(id) ON DELETE SET NULL,
    FOREIGN KEY (alert_id) REFERENCES ai_alerts(id) ON DELETE SET NULL,
    FOREIGN KEY (incident_id) REFERENCES incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 24. SMS LOGS
-- ============================================
CREATE TABLE sms_logs (
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
    INDEX idx_created (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 25. ADMIN AI SETTINGS
-- ============================================
CREATE TABLE admin_ai_settings (
    admin_id INT(11) PRIMARY KEY,
    sound_alerts_enabled TINYINT(1) DEFAULT 1,
    auto_ack_low_risk TINYINT(1) DEFAULT 0,
    auto_ack_minutes INT DEFAULT 10,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 26. ZONE AI SETTINGS
-- ============================================
CREATE TABLE zone_ai_settings (
    zone_id INT(11) PRIMARY KEY,
    sound_alerts_enabled TINYINT(1) DEFAULT 1,
    auto_ack_low_risk TINYINT(1) DEFAULT 0,
    auto_ack_minutes INT DEFAULT 10,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 27. SIMULATION CONTROLS
-- ============================================
CREATE TABLE simulation_controls (
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
    INDEX idx_enabled (is_enabled),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 28. SIMULATION EVENTS
-- ============================================
CREATE TABLE simulation_events (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NOT NULL,
    actor_id INT(11) NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    description TEXT NULL,
    payload TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_actor (actor_id),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 29. SMS MESSAGES (supervisor outbox)
-- ============================================
CREATE TABLE sms_messages (
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
    INDEX idx_created (created_at),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (recipient_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- AI ENGINE v2
-- ============================================

-- 30. AI MODELS
CREATE TABLE ai_models (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    version VARCHAR(30) NOT NULL,
    model_type ENUM('detector','classifier','tracker','behavior','fusion') NOT NULL,
    framework VARCHAR(50) NULL,
    endpoint VARCHAR(500) NULL,
    weights_path VARCHAR(500) NULL,
    input_size VARCHAR(20) DEFAULT '640x640',
    precision_level ENUM('fp32','fp16','int8') DEFAULT 'fp16',
    avg_latency_ms INT DEFAULT 0,
    accuracy DECIMAL(5,4) NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_default TINYINT(1) DEFAULT 0,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_model_version (name, version),
    INDEX idx_active_default (is_active, is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 31. AI DETECTIONS V2
CREATE TABLE ai_detections_v2 (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    base_detection_id INT(11) NULL,
    camera_id INT(11) NULL,
    zone_id INT(11) NOT NULL,
    detection_type ENUM('human','animal','vehicle','fire','gunshot','unknown') NOT NULL,
    subclass VARCHAR(60) NULL,
    confidence DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    ensemble_confidence DECIMAL(5,4) NULL,
    bbox_x DECIMAL(7,4) NULL,
    bbox_y DECIMAL(7,4) NULL,
    bbox_w DECIMAL(7,4) NULL,
    bbox_h DECIMAL(7,4) NULL,
    track_id VARCHAR(60) NULL,
    frame_count INT DEFAULT 1,
    first_seen DATETIME NULL,
    last_seen DATETIME NULL,
    behaviour VARCHAR(60) NULL,
    behaviour_score DECIMAL(5,4) NULL,
    heading_degrees DECIMAL(6,2) NULL,
    speed_mps DECIMAL(6,2) NULL,
    is_threat TINYINT(1) DEFAULT 0,
    threat_level ENUM('low','medium','high','critical') DEFAULT 'low',
    threat_score DECIMAL(6,3) DEFAULT 0.000,
    inside_zone TINYINT(1) DEFAULT 1,
    near_boundary TINYINT(1) DEFAULT 0,
    distance_to_boundary_m INT NULL,
    is_night TINYINT(1) DEFAULT 0,
    weather VARCHAR(30) NULL,
    model_id INT(11) NULL,
    ensemble_models JSON NULL,
    snapshot_url VARCHAR(500) NULL,
    clip_url VARCHAR(500) NULL,
    location_lat DECIMAL(10,8) NULL,
    location_lng DECIMAL(11,8) NULL,
    detected_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_zone_time (zone_id, detected_at),
    INDEX idx_type_time (detection_type, detected_at),
    INDEX idx_track (track_id),
    INDEX idx_threat_score (threat_score),
    INDEX idx_threat_level (threat_level),
    INDEX idx_camera_time (camera_id, detected_at),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    FOREIGN KEY (model_id) REFERENCES ai_models(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 32. AI TRACKS
CREATE TABLE ai_tracks (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    track_uid VARCHAR(60) NOT NULL,
    zone_id INT(11) NOT NULL,
    camera_id INT(11) NULL,
    primary_type ENUM('human','animal','vehicle','fire','gunshot','unknown') NOT NULL,
    subclass VARCHAR(60) NULL,
    start_time DATETIME NOT NULL,
    end_time DATETIME NULL,
    duration_seconds INT NULL,
    distance_meters DECIMAL(10,2) NULL,
    avg_speed_mps DECIMAL(6,2) NULL,
    max_speed_mps DECIMAL(6,2) NULL,
    start_lat DECIMAL(10,8) NULL,
    start_lng DECIMAL(11,8) NULL,
    end_lat DECIMAL(10,8) NULL,
    end_lng DECIMAL(11,8) NULL,
    path_geojson JSON NULL,
    frame_count INT DEFAULT 1,
    peak_confidence DECIMAL(5,4) NULL,
    peak_threat_score DECIMAL(6,3) NULL,
    is_active TINYINT(1) DEFAULT 1,
    is_confirmed TINYINT(1) DEFAULT 0,
    last_detected_at DATETIME NULL,
    INDEX idx_zone_time (zone_id, start_time),
    INDEX idx_active (is_active),
    INDEX idx_type (primary_type),
    UNIQUE KEY uq_track_uid (track_uid),
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (camera_id) REFERENCES cctv_cameras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 33. AI THRESHOLDS
CREATE TABLE ai_thresholds (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    zone_id INT(11) NULL,
    camera_id INT(11) NULL,
    detection_type ENUM('human','animal','vehicle','fire','gunshot','unknown') NULL,
    min_confidence DECIMAL(5,4) DEFAULT 0.7000,
    min_ensemble_confidence DECIMAL(5,4) DEFAULT 0.7500,
    min_threat_score DECIMAL(6,3) DEFAULT 40.000,
    min_track_frames INT DEFAULT 2,
    cooldown_seconds INT DEFAULT 45,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone (zone_id),
    INDEX idx_camera (camera_id),
    INDEX idx_type (detection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 34. AI RULES
CREATE TABLE ai_rules (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    rule_key VARCHAR(80) NOT NULL,
    description TEXT NULL,
    detection_type ENUM('human','animal','vehicle','fire','gunshot','unknown') NULL,
    condition_json JSON NOT NULL,
    threat_level ENUM('low','medium','high','critical') DEFAULT 'medium',
    weight DECIMAL(5,2) DEFAULT 10.00,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rule_key (rule_key),
    INDEX idx_active (is_active),
    INDEX idx_type (detection_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 35. AI FEEDBACK
CREATE TABLE ai_feedback (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    detection_v2_id BIGINT NULL,
    alert_id INT(11) NULL,
    user_id INT(11) NOT NULL,
    verdict ENUM('true_positive','false_positive','uncertain') NOT NULL,
    notes TEXT NULL,
    original_type VARCHAR(60) NULL,
    original_confidence DECIMAL(5,4) NULL,
    corrected_type VARCHAR(60) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_detection (detection_v2_id),
    INDEX idx_alert (alert_id),
    INDEX idx_verdict (verdict),
    FOREIGN KEY (detection_v2_id) REFERENCES ai_detections_v2(id) ON DELETE SET NULL,
    FOREIGN KEY (alert_id) REFERENCES ai_alerts(id) ON DELETE SET NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 36. AI TELEMETRY
CREATE TABLE ai_telemetry (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    model_id INT(11) NULL,
    camera_id INT(11) NULL,
    zone_id INT(11) NULL,
    window_start DATETIME NOT NULL,
    window_seconds INT DEFAULT 60,
    frames_processed INT DEFAULT 0,
    detections_emitted INT DEFAULT 0,
    avg_latency_ms INT DEFAULT 0,
    p95_latency_ms INT DEFAULT 0,
    gpu_util_pct DECIMAL(5,2) NULL,
    cpu_util_pct DECIMAL(5,2) NULL,
    errors INT DEFAULT 0,
    INDEX idx_model_time (model_id, window_start),
    INDEX idx_camera_time (camera_id, window_start),
    INDEX idx_zone_time (zone_id, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 37. AI QUEUE
CREATE TABLE ai_queue (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    job_type ENUM('detect','track','behaviour','threat','alert','recalibrate') NOT NULL,
    priority TINYINT DEFAULT 5,
    zone_id INT(11) NULL,
    camera_id INT(11) NULL,
    payload JSON NOT NULL,
    status ENUM('pending','processing','done','failed') DEFAULT 'pending',
    attempts TINYINT DEFAULT 0,
    max_attempts TINYINT DEFAULT 3,
    result JSON NULL,
    error TEXT NULL,
    scheduled_for DATETIME DEFAULT CURRENT_TIMESTAMP,
    started_at DATETIME NULL,
    finished_at DATETIME NULL,
    INDEX idx_status_priority (status, priority, scheduled_for),
    INDEX idx_zone (zone_id),
    INDEX idx_camera (camera_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT ZONE (Headquarters)
-- ============================================
INSERT INTO zones (name, description, center_lat, center_lng, is_active)
VALUES ('Headquarters', 'Main Administrative Zone - Lusaka, Zambia', -15.3875, 28.3228, 1);

-- ============================================
-- ZAMBIAN NATIONAL PARKS (10)
-- ============================================
INSERT INTO zones (name, description, park_type, park_code, is_registered, center_lat, center_lng, boundary_center_lat, boundary_center_lng, boundary_geojson, is_active, buffer_radius) VALUES
('South Luangwa National Park', 'One of Africa''s greatest wildlife sanctuaries, home to elephants, hippos, and the famous Luangwa lions.', 'national_park', 'SLNP', 0, -13.0000, 31.5000, -13.0000, 31.5000, '{"type":"Polygon","coordinates":[[[31.0,-13.5],[32.0,-13.5],[32.0,-12.5],[31.0,-12.5],[31.0,-13.5]]]}', 1, 500),
('Kafue National Park', 'Zambia''s largest national park, covering over 22,000 km² of pristine wilderness.', 'national_park', 'KNP', 0, -14.5000, 26.0000, -14.5000, 26.0000, '{"type":"Polygon","coordinates":[[[25.5,-15.0],[26.5,-15.0],[26.5,-14.0],[25.5,-14.0],[25.5,-15.0]]]}', 1, 500),
('Lower Zambezi National Park', 'Located along the Zambezi River, famous for canoeing safaris and elephant herds.', 'national_park', 'LZNP', 0, -15.5000, 29.5000, -15.5000, 29.5000, '{"type":"Polygon","coordinates":[[[29.0,-16.0],[30.0,-16.0],[30.0,-15.0],[29.0,-15.0],[29.0,-16.0]]]}', 1, 500),
('North Luangwa National Park', 'Remote wilderness known for rhino conservation and untouched landscapes.', 'national_park', 'NLNP', 0, -12.0000, 32.0000, -12.0000, 32.0000, '{"type":"Polygon","coordinates":[[[31.5,-12.5],[32.5,-12.5],[32.5,-11.5],[31.5,-11.5],[31.5,-12.5]]]}', 1, 500),
('Liuwa Plain National Park', 'Home to the famous Liuwa wildebeest migration and spectacular open plains.', 'national_park', 'LPNP', 0, -14.5000, 23.0000, -14.5000, 23.0000, '{"type":"Polygon","coordinates":[[[22.5,-15.0],[23.5,-15.0],[23.5,-14.0],[22.5,-14.0],[22.5,-15.0]]]}', 1, 500),
('Mosi-oa-Tunya National Park', 'Victoria Falls area, home to white rhino and spectacular views of the falls.', 'national_park', 'MOTNP', 0, -17.9000, 25.8000, -17.9000, 25.8000, '{"type":"Polygon","coordinates":[[[25.5,-18.2],[26.1,-18.2],[26.1,-17.7],[25.5,-17.7],[25.5,-18.2]]]}', 1, 500),
('Kasanka National Park', 'Famous for the annual bat migration and diverse bird species.', 'national_park', 'KSNP', 0, -12.5000, 30.0000, -12.5000, 30.0000, '{"type":"Polygon","coordinates":[[[29.5,-13.0],[30.5,-13.0],[30.5,-12.0],[29.5,-12.0],[29.5,-13.0]]]}', 1, 500),
('Sumbu National Park', 'Located on Lake Tanganyika, known for its pristine beaches and marine life.', 'national_park', 'SNP', 0, -8.5000, 30.5000, -8.5000, 30.5000, '{"type":"Polygon","coordinates":[[[30.0,-9.0],[31.0,-9.0],[31.0,-8.0],[30.0,-8.0],[30.0,-9.0]]]}', 1, 500),
('Lochinvar National Park', 'Important wetland area with diverse bird species and Kafue lechwe.', 'national_park', 'LNP', 0, -15.5000, 27.5000, -15.5000, 27.5000, '{"type":"Polygon","coordinates":[[[27.0,-16.0],[28.0,-16.0],[28.0,-15.0],[27.0,-15.0],[27.0,-16.0]]]}', 1, 500),
('Blue Lagoon National Park', 'Wetland paradise on the Kafue Flats, famous for bird watching.', 'national_park', 'BLNP', 0, -15.5000, 27.0000, -15.5000, 27.0000, '{"type":"Polygon","coordinates":[[[26.5,-16.0],[27.5,-16.0],[27.5,-15.0],[26.5,-15.0],[26.5,-16.0]]]}', 1, 500);

-- ============================================
-- ZAMBIAN GMAs (6)
-- ============================================
INSERT INTO zones (name, description, park_type, park_code, is_registered, center_lat, center_lng, boundary_center_lat, boundary_center_lng, boundary_geojson, is_active, buffer_radius) VALUES
('Luangwa Game Management Area', 'Critical buffer zone surrounding South and North Luangwa National Parks.', 'gma', 'LGMA', 0, -12.5000, 31.5000, -12.5000, 31.5000, '{"type":"Polygon","coordinates":[[[30.5,-13.0],[32.5,-13.0],[32.5,-11.5],[30.5,-11.5],[30.5,-13.0]]]}', 1, 500),
('Kafue Game Management Area', 'Buffer zone around Kafue National Park, important for wildlife corridors.', 'gma', 'KGMA', 0, -15.0000, 26.0000, -15.0000, 26.0000, '{"type":"Polygon","coordinates":[[[25.0,-15.5],[27.0,-15.5],[27.0,-14.5],[25.0,-14.5],[25.0,-15.5]]]}', 1, 500),
('Zambezi Game Management Area', 'Buffer zone along the Zambezi River corridor.', 'gma', 'ZGMA', 0, -15.5000, 29.0000, -15.5000, 29.0000, '{"type":"Polygon","coordinates":[[[28.5,-16.0],[30.0,-16.0],[30.0,-15.0],[28.5,-15.0],[28.5,-16.0]]]}', 1, 500),
('Liuwa Game Management Area', 'Buffer zone around Liuwa Plain National Park.', 'gma', 'LGMA2', 0, -15.0000, 23.0000, -15.0000, 23.0000, '{"type":"Polygon","coordinates":[[[22.0,-15.5],[24.0,-15.5],[24.0,-14.5],[22.0,-14.5],[22.0,-15.5]]]}', 1, 500),
('Bangweulu Game Management Area', 'Protects the Bangweulu Wetlands ecosystem.', 'gma', 'BGMA', 0, -11.5000, 30.0000, -11.5000, 30.0000, '{"type":"Polygon","coordinates":[[[29.5,-12.0],[30.5,-12.0],[30.5,-11.0],[29.5,-11.0],[29.5,-12.0]]]}', 1, 500),
('West Lunga Game Management Area', 'Buffer zone for the West Lunga National Park area.', 'gma', 'WLGMA', 0, -12.5000, 25.0000, -12.5000, 25.0000, '{"type":"Polygon","coordinates":[[[24.5,-13.0],[25.5,-13.0],[25.5,-12.0],[24.5,-12.0],[24.5,-13.0]]]}', 1, 500);

-- ============================================
-- SAMPLE LODGES
-- ============================================
INSERT INTO lodges (zone_id, name, description, location_lat, location_lng, is_active) VALUES
((SELECT id FROM zones WHERE park_code = 'SLNP' LIMIT 1), 'Mfuwe Lodge', 'Riverside lodge near South Luangwa', -13.0833, 31.5000, 1),
((SELECT id FROM zones WHERE park_code = 'SLNP' LIMIT 1), 'Bilimungwe Camp', 'Bushcamp in the heart of the park', -13.1000, 31.5500, 1),
((SELECT id FROM zones WHERE park_code = 'KNP'  LIMIT 1), 'Kafue River Lodge', 'Lodge on the Kafue River', -14.5000, 26.0000, 1);

-- ============================================
-- DEFAULT GLOBAL SETTINGS
-- ============================================
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name',               'Wildlife Sentinel',                'general'),
('site_tagline',            'Protecting Zambia''s Wildlife',    'general'),
('timezone',                'Africa/Lusaka',                    'general'),
('language',                'en',                               'general'),
('date_format',             'M j, Y H:i',                       'general'),
('items_per_page',          '25',                               'general'),
('sms_enabled',             '1',                                'notifications'),
('email_enabled',           '0',                                'notifications'),
('push_enabled',            '1',                                'notifications'),
('notify_on_incident',      '1',                                'notifications'),
('notify_on_ai_alert',      '1',                                'notifications'),
('notify_on_alarm',         '1',                                'notifications'),
('notify_on_manpower',      '1',                                'notifications'),
('notify_offline_users',    '1',                                'notifications'),
('ai_enabled',              '1',                                'ai'),
('ai_confidence_min',       '70',                               'ai'),
('ai_auto_create_alert',    '1',                                'ai'),
('ai_auto_trigger_alarm',   '0',                                'ai'),
('cctv_retention_days',     '30',                               'ai'),
('cctv_snapshot_dir',       'uploads/cctv/',                    'ai'),
('ai_ingest_key',           '',                                 'ai'),
('session_timeout_min',     '60',                               'security'),
('password_min_length',     '8',                                'security'),
('password_require_upper',  '1',                                'security'),
('password_require_lower',  '1',                                'security'),
('password_require_num',    '1',                                'security'),
('password_require_sym',    '0',                                'security'),
('login_max_attempts',      '5',                                'security'),
('login_lockout_min',       '5',                                'security'),
('maintenance_mode',        '0',                                'maintenance'),
('maintenance_message',     'System under maintenance. Please check back soon.', 'maintenance');

-- ============================================
-- SEED: AI MODELS
-- ============================================
INSERT INTO ai_models (name, version, model_type, framework, input_size, precision_level, avg_latency_ms, accuracy, is_default, notes) VALUES
('yolov8n-detector',   '1.0', 'detector',   'onnx',   '640x640', 'int8', 25,  0.7200, 1, 'Fast general-purpose detector (default)'),
('yolov8s-detector',   '1.0', 'detector',   'onnx',   '640x640', 'fp16', 55,  0.7800, 0, 'Balanced accuracy detector'),
('yolov8m-detector',   '1.0', 'detector',   'onnx',   '640x640', 'fp16', 110, 0.8200, 0, 'Higher accuracy detector (slower)'),
('thermal-detector',   '1.0', 'detector',   'onnx',   '512x512', 'fp16', 60,  0.7400, 0, 'Thermal-specific detector'),
('gunshot-classifier', '1.0', 'classifier', 'tflite', '224x224', 'int8', 12,  0.8900, 0, 'Audio-to-gunshot classifier'),
('fire-classifier',    '1.0', 'classifier', 'tflite', '224x224', 'int8', 14,  0.9100, 0, 'Fire/smoke classifier'),
('behaviour-lstm',     '1.0', 'behavior',   'onnx',   '30x128',  'fp16', 35,  0.8300, 0, 'Behaviour model (loitering, approach, flee)'),
('fusion-scorer',      '1.0', 'fusion',     'custom', 'n/a',     'fp32', 2,   0.9500, 0, 'Weighted ensemble + threat scoring');

-- ============================================
-- SEED: AI THRESHOLDS (global defaults)
-- ============================================
INSERT INTO ai_thresholds (zone_id, camera_id, detection_type, min_confidence, min_ensemble_confidence, min_threat_score, min_track_frames, cooldown_seconds) VALUES
(NULL, NULL, 'human',   0.7500, 0.8000, 55.000, 2, 30),
(NULL, NULL, 'vehicle', 0.7000, 0.7500, 45.000, 2, 45),
(NULL, NULL, 'animal',  0.6500, 0.7000, 25.000, 2, 60),
(NULL, NULL, 'fire',    0.7000, 0.7500, 70.000, 1, 15),
(NULL, NULL, 'gunshot', 0.8000, 0.8500, 85.000, 1, 10),
(NULL, NULL, 'unknown', 0.8500, 0.9000, 60.000, 2, 60);

-- ============================================
-- SEED: AI RULES
-- ============================================
INSERT INTO ai_rules (name, rule_key, description, detection_type, condition_json, threat_level, weight) VALUES
('Human near boundary at night',        'human_boundary_night',    'Human detected near zone boundary during night hours', 'human',   '{"near_boundary":true,"is_night":true}',                          'high',     25.00),
('Human loitering in sensitive area',   'human_loitering',         'Human remaining stationary inside a sensitive area for over 2 min', 'human', '{"behaviour":"loitering","min_duration_seconds":120}',           'high',     22.00),
('Vehicle intrusion into core zone',    'vehicle_intrusion',       'Vehicle detected inside a restricted core zone',        'vehicle', '{"inside_zone":true,"subclass_in":["pickup","truck","suv"]}',   'high',     30.00),
('High-confidence gunshot',             'gunshot_high_conf',       'Gunshot classifier above 0.85 confidence',              'gunshot', '{"min_confidence":0.85}',                                          'critical', 45.00),
('Fire in dry season',                  'fire_dry_season',         'Fire or smoke detected during dry season',              'fire',    '{"min_confidence":0.70,"month_in":[6,7,8,9,10]}',                   'critical', 40.00),
('Animal distress',                     'animal_distress',         'Animal detected with high distress behaviour score',    'animal',  '{"behaviour_score_min":0.75}',                                      'medium',   18.00),
('Rapid approach towards village',      'human_approach_village',  'Human track heading towards nearest village at over 1 m/s', 'human', '{"behaviour":"approaching","min_speed_mps":1.0,"target":"village"}', 'high',   28.00);

-- ============================================
-- VIEWS
-- ============================================
CREATE OR REPLACE VIEW incident_summary AS
SELECT i.id, i.category, i.severity, i.status, i.description,
       i.location_lat, i.location_lng, i.reported_at,
       u.full_name AS reporter_name, u.phone AS reporter_phone,
       z.name AS zone_name, i.media_urls
FROM incidents i
LEFT JOIN users u ON i.reporter_id = u.id
LEFT JOIN zones z ON i.zone_id = z.id;

CREATE OR REPLACE VIEW ranger_status AS
SELECT u.id, u.full_name, u.phone, u.zone_id, z.name AS zone_name,
       ra.is_available, ra.current_incident_id,
       i.category AS current_incident_category,
       rlt.current_lat, rlt.current_lng,
       rlt.last_update AS last_location_update
FROM users u
LEFT JOIN ranger_availability ra ON u.id = ra.ranger_id
LEFT JOIN incidents i ON ra.current_incident_id = i.id
LEFT JOIN ranger_live_tracking rlt ON u.id = rlt.ranger_id
LEFT JOIN zones z ON u.zone_id = z.id
WHERE u.role = 'ranger';

CREATE OR REPLACE VIEW unacknowledged_incidents AS
SELECT zone_id, COUNT(*) AS count, GROUP_CONCAT(id) AS incident_ids
FROM incidents
WHERE status = 'reported'
GROUP BY zone_id;

CREATE OR REPLACE VIEW park_registration_status AS
SELECT id, name, park_type, park_code, is_registered,
       CASE WHEN is_registered = 1 THEN '✅ Registered' ELSE '🔴 Not Registered' END AS registration_status,
       CASE WHEN is_registered = 1 THEN '#28a745' ELSE '#dc3545' END AS status_color,
       buffer_radius
FROM zones
WHERE park_type IN ('national_park','gma')
ORDER BY park_type, name;

CREATE OR REPLACE VIEW zone_map_data AS
SELECT id, name, park_type, park_code, is_registered,
       boundary_center_lat AS lat, boundary_center_lng AS lng,
       boundary_geojson, buffer_radius
FROM zones
WHERE park_type IN ('national_park','gma');

CREATE OR REPLACE VIEW scout_status AS
SELECT u.id, u.full_name, u.phone, u.email, u.zone_id, z.name AS zone_name,
       u.is_online, u.last_seen,
       slt.current_lat, slt.current_lng,
       slt.last_update AS last_location_update,
       slt.is_offline
FROM users u
LEFT JOIN scout_live_tracking slt ON u.id = slt.scout_id
LEFT JOIN zones z ON u.zone_id = z.id
WHERE u.role = 'scout';

CREATE OR REPLACE VIEW supervisor_dashboard_summary AS
SELECT
    z.id AS zone_id,
    z.name AS zone_name,
    (SELECT COUNT(*) FROM incidents i WHERE i.zone_id = z.id AND i.status NOT IN ('resolved','closed')) AS active_incidents,
    (SELECT COUNT(*) FROM incidents i WHERE i.zone_id = z.id AND i.status = 'reported') AS unacknowledged,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'ranger' AND u.is_on_duty = 1) AS rangers_on_duty,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'ranger' AND u.is_active = 1) AS total_rangers,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'scout' AND u.is_online = 1) AS scouts_online,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'scout' AND u.is_active = 1) AS total_scouts,
    (SELECT COUNT(*) FROM ai_anomalies a WHERE a.zone_id = z.id AND a.detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) AS ai_anomalies_24h
FROM zones z
WHERE z.is_active = 1;

CREATE OR REPLACE VIEW ai_active_threats AS
SELECT d.id, d.zone_id, z.name AS zone_name, d.camera_id, c.camera_name,
       d.detection_type, d.subclass, d.confidence, d.ensemble_confidence,
       d.threat_level, d.threat_score, d.behaviour, d.behaviour_score,
       d.detected_at, d.snapshot_url, d.location_lat, d.location_lng
FROM ai_detections_v2 d
LEFT JOIN zones z ON d.zone_id = z.id
LEFT JOIN cctv_cameras c ON d.camera_id = c.id
WHERE d.is_threat = 1
  AND d.threat_score >= 50
  AND d.detected_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
ORDER BY d.threat_score DESC, d.detected_at DESC;

CREATE OR REPLACE VIEW ai_recent_detections AS
SELECT d.id, d.zone_id, z.name AS zone_name,
       d.camera_id, c.camera_name,
       d.detection_type, d.subclass, d.confidence, d.ensemble_confidence,
       d.is_threat, d.threat_level, d.threat_score,
       d.track_id, d.behaviour, d.is_night, d.inside_zone,
       m.name AS model_name, m.version AS model_version,
       d.detected_at, d.snapshot_url
FROM ai_detections_v2 d
LEFT JOIN zones z ON d.zone_id = z.id
LEFT JOIN cctv_cameras c ON d.camera_id = c.id
LEFT JOIN ai_models m ON d.model_id = m.id
ORDER BY d.detected_at DESC;

-- ============================================
-- TRIGGERS
-- ============================================
DELIMITER $$

DROP TRIGGER IF EXISTS update_ranger_availability_on_assignment $$
CREATE TRIGGER update_ranger_availability_on_assignment
AFTER INSERT ON incident_assignments
FOR EACH ROW
BEGIN
    IF NEW.status = 'accepted' THEN
        UPDATE ranger_availability
        SET is_available = 0, current_incident_id = NEW.incident_id
        WHERE ranger_id = NEW.ranger_id;
    END IF;
END $$

DROP TRIGGER IF EXISTS update_ranger_availability_on_resolution $$
CREATE TRIGGER update_ranger_availability_on_resolution
AFTER UPDATE ON incidents
FOR EACH ROW
BEGIN
    IF NEW.status = 'resolved' AND OLD.status <> 'resolved' THEN
        UPDATE ranger_availability
        SET is_available = 1, current_incident_id = NULL
        WHERE current_incident_id = NEW.id;
    END IF;
END $$

DROP TRIGGER IF EXISTS set_incident_location_geojson $$
CREATE TRIGGER set_incident_location_geojson
BEFORE INSERT ON incidents
FOR EACH ROW
BEGIN
    IF NEW.location_geojson IS NULL OR ST_AsText(NEW.location_geojson) = 'POINT(0 0)' THEN
        SET NEW.location_geojson = ST_GeomFromText(
            CONCAT('POINT(', NEW.location_lng, ' ', NEW.location_lat, ')')
        );
    END IF;
END $$

DROP TRIGGER IF EXISTS update_incident_location_geojson $$
CREATE TRIGGER update_incident_location_geojson
BEFORE UPDATE ON incidents
FOR EACH ROW
BEGIN
    IF NEW.location_lat <> OLD.location_lat OR NEW.location_lng <> OLD.location_lng THEN
        SET NEW.location_geojson = ST_GeomFromText(
            CONCAT('POINT(', NEW.location_lng, ' ', NEW.location_lat, ')')
        );
    END IF;
END $$

DROP TRIGGER IF EXISTS create_zone_notification_settings $$
CREATE TRIGGER create_zone_notification_settings
AFTER INSERT ON zones
FOR EACH ROW
BEGIN
    INSERT IGNORE INTO zone_notification_settings (zone_id, sms_enabled, alarm_enabled, ai_detection_enabled)
    VALUES (NEW.id, 1, 1, 1);

    INSERT IGNORE INTO zone_system_settings (zone_id)
    VALUES (NEW.id);

    INSERT IGNORE INTO zone_ai_settings (zone_id)
    VALUES (NEW.id);
END $$

DROP TRIGGER IF EXISTS mark_scout_online $$
CREATE TRIGGER mark_scout_online
AFTER INSERT ON scout_live_tracking
FOR EACH ROW
BEGIN
    UPDATE users SET is_online = 1, last_seen = NOW() WHERE id = NEW.scout_id;
END $$

DELIMITER ;

-- ============================================
-- STORED PROCEDURES
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS GetNearbyIncidents $$
CREATE PROCEDURE GetNearbyIncidents(
    IN p_lat DECIMAL(10,8),
    IN p_lng DECIMAL(11,8),
    IN p_radius INT,
    IN p_limit INT
)
BEGIN
    SELECT *,
           (6371 * ACOS(COS(RADIANS(p_lat)) * COS(RADIANS(location_lat)) *
            COS(RADIANS(location_lng) - RADIANS(p_lng)) +
            SIN(RADIANS(p_lat)) * SIN(RADIANS(location_lat)))) AS distance
    FROM incidents
    WHERE status NOT IN ('resolved','closed')
    HAVING distance < p_radius
    ORDER BY distance ASC, severity DESC
    LIMIT p_limit;
END $$

DROP PROCEDURE IF EXISTS GetZoneStatistics $$
CREATE PROCEDURE GetZoneStatistics(IN p_zone_id INT)
BEGIN
    SELECT
        (SELECT COUNT(*) FROM incidents WHERE zone_id = p_zone_id AND status NOT IN ('resolved','closed')) AS active_incidents,
        (SELECT COUNT(*) FROM incidents WHERE zone_id = p_zone_id AND status = 'reported') AS unacknowledged,
        (SELECT COUNT(*) FROM incidents WHERE zone_id = p_zone_id AND DATE(reported_at) = CURDATE()) AS today_reports,
        (SELECT COUNT(*) FROM ranger_availability ra JOIN users u ON ra.ranger_id = u.id WHERE u.zone_id = p_zone_id AND ra.is_available = 1) AS available_rangers,
        (SELECT COUNT(*) FROM users WHERE zone_id = p_zone_id AND role = 'ranger' AND is_active = 1) AS total_rangers,
        (SELECT COUNT(*) FROM incidents WHERE zone_id = p_zone_id AND media_urls IS NOT NULL) AS incidents_with_photos;
END $$

DROP PROCEDURE IF EXISTS GetSystemStatistics $$
CREATE PROCEDURE GetSystemStatistics()
BEGIN
    SELECT
        (SELECT COUNT(*) FROM users) AS total_users,
        (SELECT COUNT(*) FROM users WHERE role = 'ranger') AS total_rangers,
        (SELECT COUNT(*) FROM incidents) AS total_incidents,
        (SELECT COUNT(*) FROM incidents WHERE status NOT IN ('resolved','closed')) AS active_incidents,
        (SELECT COUNT(*) FROM incidents WHERE status = 'reported') AS unacknowledged,
        (SELECT COUNT(*) FROM zones WHERE is_active = 1) AS total_zones,
        (SELECT COUNT(*) FROM messages) AS total_messages,
        (SELECT COUNT(*) FROM notifications WHERE is_read = 0) AS unread_notifications,
        (SELECT COUNT(*) FROM incidents WHERE media_urls IS NOT NULL) AS incidents_with_photos,
        (SELECT COUNT(*) FROM zones WHERE park_type IN ('national_park','gma') AND is_registered = 1) AS registered_parks,
        (SELECT COUNT(*) FROM zones WHERE park_type IN ('national_park','gma') AND is_registered = 0) AS available_parks;
END $$

DROP PROCEDURE IF EXISTS GetZoneRangersLive $$
CREATE PROCEDURE GetZoneRangersLive(IN p_zone_id INT)
BEGIN
    SELECT u.id, u.full_name, u.phone, u.badge_number, u.is_on_duty,
           rlt.current_lat, rlt.current_lng, rlt.heading, rlt.speed,
           rlt.last_update AS location_updated, rlt.is_offline
    FROM users u
    LEFT JOIN ranger_live_tracking rlt ON u.id = rlt.ranger_id
    WHERE u.zone_id = p_zone_id AND u.role = 'ranger' AND u.is_active = 1
    ORDER BY u.is_on_duty DESC, u.full_name ASC;
END $$

DROP PROCEDURE IF EXISTS GetZoneScoutsLive $$
CREATE PROCEDURE GetZoneScoutsLive(IN p_zone_id INT)
BEGIN
    SELECT u.id, u.full_name, u.phone, u.email, u.is_online, u.last_seen,
           slt.current_lat, slt.current_lng,
           slt.last_update AS location_updated, slt.is_offline
    FROM users u
    LEFT JOIN scout_live_tracking slt ON u.id = slt.scout_id
    WHERE u.zone_id = p_zone_id AND u.role = 'scout' AND u.is_active = 1
    ORDER BY u.is_online DESC, u.last_seen DESC;
END $$

DROP PROCEDURE IF EXISTS GetZoneAIAnomalies $$
CREATE PROCEDURE GetZoneAIAnomalies(IN p_zone_id INT, IN p_limit INT)
BEGIN
    SELECT a.*, u.full_name AS subject_name, u.role AS subject_role
    FROM ai_anomalies a
    LEFT JOIN users u ON (a.ranger_id = u.id OR a.scout_id = u.id)
    WHERE a.zone_id = p_zone_id
      AND a.detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ORDER BY a.detected_at DESC
    LIMIT p_limit;
END $$

DELIMITER ;

-- ============================================
-- VERIFY INSTALLATION
-- ============================================
SELECT '✅ Database setup completed successfully!' AS Status;

SELECT '📊 Tables created:' AS Info;
SELECT TABLE_NAME
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_TYPE = 'BASE TABLE'
ORDER BY TABLE_NAME;

SELECT '👁️ Views created:' AS Info;
SELECT TABLE_NAME AS view_name
FROM information_schema.VIEWS
WHERE TABLE_SCHEMA = DATABASE()
ORDER BY TABLE_NAME;

SELECT '🗺️ Zones created:' AS Info;
SELECT id, name, park_type, park_code, is_registered, buffer_radius, is_active
FROM zones
ORDER BY park_type, name;

SELECT '=========================================' AS '';
SELECT 'WILDLIFE SENTINEL DATABASE READY!' AS '✅ STATUS';
SELECT '=========================================' AS '';
SELECT (SELECT COUNT(*) FROM zones WHERE park_type = 'national_park') AS 'National Parks',
       (SELECT COUNT(*) FROM zones WHERE park_type = 'gma') AS 'GMAs',
       (SELECT COUNT(*) FROM zones WHERE park_type IN ('national_park','gma') AND is_registered = 1) AS 'Registered Parks',
       (SELECT COUNT(*) FROM zones WHERE park_type IN ('national_park','gma') AND is_registered = 0) AS 'Available Parks';
SELECT '=========================================' AS '';
SELECT '🔑 NO DEFAULT USER ACCOUNTS!' AS '';
SELECT 'First time setup: Go to index.php to create admin account' AS '';
SELECT '=========================================' AS '';