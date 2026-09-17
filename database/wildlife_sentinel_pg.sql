-- ============================================================
-- WILDLIFE SENTINEL DATABASE — PostgreSQL / Supabase Edition
-- Converted from MySQL/MariaDB schema
-- ============================================================

-- Drop views
DROP VIEW IF EXISTS ai_recent_detections CASCADE;
DROP VIEW IF EXISTS ai_active_threats CASCADE;
DROP VIEW IF EXISTS supervisor_dashboard_summary CASCADE;
DROP VIEW IF EXISTS scout_status CASCADE;
DROP VIEW IF EXISTS zone_map_data CASCADE;
DROP VIEW IF EXISTS park_registration_status CASCADE;
DROP VIEW IF EXISTS unacknowledged_incidents CASCADE;
DROP VIEW IF EXISTS ranger_status CASCADE;
DROP VIEW IF EXISTS incident_summary CASCADE;

-- Drop tables (children first)
DROP TABLE IF EXISTS ai_queue CASCADE;
DROP TABLE IF EXISTS ai_telemetry CASCADE;
DROP TABLE IF EXISTS ai_feedback CASCADE;
DROP TABLE IF EXISTS ai_rules CASCADE;
DROP TABLE IF EXISTS ai_thresholds CASCADE;
DROP TABLE IF EXISTS ai_tracks CASCADE;
DROP TABLE IF EXISTS ai_detections_v2 CASCADE;
DROP TABLE IF EXISTS ai_models CASCADE;
DROP TABLE IF EXISTS sms_messages CASCADE;
DROP TABLE IF EXISTS simulation_events CASCADE;
DROP TABLE IF EXISTS simulation_controls CASCADE;
DROP TABLE IF EXISTS zone_ai_settings CASCADE;
DROP TABLE IF EXISTS admin_ai_settings CASCADE;
DROP TABLE IF EXISTS sms_logs CASCADE;
DROP TABLE IF EXISTS alarm_triggers CASCADE;
DROP TABLE IF EXISTS alarm_systems CASCADE;
DROP TABLE IF EXISTS ai_alerts CASCADE;
DROP TABLE IF EXISTS ai_detections CASCADE;
DROP TABLE IF EXISTS cctv_cameras CASCADE;
DROP TABLE IF EXISTS settings CASCADE;
DROP TABLE IF EXISTS rate_limits CASCADE;
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS notifications CASCADE;
DROP TABLE IF EXISTS message_attachments CASCADE;
DROP TABLE IF EXISTS messages CASCADE;
DROP TABLE IF EXISTS incident_assignments CASCADE;
DROP TABLE IF EXISTS incident_responses CASCADE;
DROP TABLE IF EXISTS incidents CASCADE;
DROP TABLE IF EXISTS ranger_patrol_routes CASCADE;
DROP TABLE IF EXISTS ranger_location_history CASCADE;
DROP TABLE IF EXISTS ranger_live_tracking CASCADE;
DROP TABLE IF EXISTS ranger_availability CASCADE;
DROP TABLE IF EXISTS scout_location_history CASCADE;
DROP TABLE IF EXISTS scout_live_tracking CASCADE;
DROP TABLE IF EXISTS ai_anomalies CASCADE;
DROP TABLE IF EXISTS zone_notification_settings CASCADE;
DROP TABLE IF EXISTS zone_system_settings CASCADE;
DROP TABLE IF EXISTS user_preferences CASCADE;
DROP TABLE IF EXISTS lodges CASCADE;
DROP TABLE IF EXISTS password_resets CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS zones CASCADE;

-- Drop custom types
DROP TYPE IF EXISTS park_type_enum CASCADE;
DROP TYPE IF EXISTS user_role_enum CASCADE;
DROP TYPE IF EXISTS reporter_type_enum CASCADE;
DROP TYPE IF EXISTS incident_category_enum CASCADE;
DROP TYPE IF EXISTS severity_enum CASCADE;
DROP TYPE IF EXISTS incident_status_enum CASCADE;
DROP TYPE IF EXISTS response_status_enum CASCADE;
DROP TYPE IF EXISTS assignment_status_enum CASCADE;
DROP TYPE IF EXISTS message_type_enum CASCADE;
DROP TYPE IF EXISTS notification_type_enum CASCADE;
DROP TYPE IF EXISTS anomaly_type_enum CASCADE;
DROP TYPE IF EXISTS camera_type_enum CASCADE;
DROP TYPE IF EXISTS detection_type_enum CASCADE;
DROP TYPE IF EXISTS threat_level_enum CASCADE;
DROP TYPE IF EXISTS alert_type_enum CASCADE;
DROP TYPE IF EXISTS alarm_type_enum CASCADE;
DROP TYPE IF EXISTS alarm_trigger_by_enum CASCADE;
DROP TYPE IF EXISTS sms_message_type_enum CASCADE;
DROP TYPE IF EXISTS sms_status_enum CASCADE;
DROP TYPE IF EXISTS simulation_type_enum CASCADE;
DROP TYPE IF EXISTS model_type_enum CASCADE;
DROP TYPE IF EXISTS precision_level_enum CASCADE;
DROP TYPE IF EXISTS ai_job_type_enum CASCADE;
DROP TYPE IF EXISTS ai_job_status_enum CASCADE;
DROP TYPE IF EXISTS feedback_verdict_enum CASCADE;
DROP TYPE IF EXISTS password_reset_status_enum CASCADE;

-- ============================================================
-- CUSTOM TYPES (replacing MySQL ENUMs)
-- ============================================================
CREATE TYPE park_type_enum          AS ENUM ('national_park','gma','other');
CREATE TYPE user_role_enum          AS ENUM ('scout','tourism','ranger','zone_supervisor','admin');
CREATE TYPE reporter_type_enum      AS ENUM ('scout','tourism');
CREATE TYPE incident_category_enum  AS ENUM ('poaching','distressed_animal','human_wildlife_conflict','environmental_risk','other');
CREATE TYPE severity_enum           AS ENUM ('low','medium','high','critical');
CREATE TYPE incident_status_enum    AS ENUM ('reported','acknowledged','in_progress','resolved','closed');
CREATE TYPE response_status_enum    AS ENUM ('arrived','investigating','resolved','escalated');
CREATE TYPE assignment_status_enum  AS ENUM ('pending','accepted','declined','completed');
CREATE TYPE message_type_enum       AS ENUM ('manpower_request','status_update','general','emergency');
CREATE TYPE notification_type_enum  AS ENUM ('new_incident','acknowledged','status_update','system_alert','new_message','manpower_request');
CREATE TYPE anomaly_type_enum       AS ENUM ('ranger_out_of_bounds','ranger_stationary','patrol_deviation','scout_out_of_bounds','unusual_movement','unusual_cluster','other');
CREATE TYPE camera_type_enum        AS ENUM ('fixed','ptz','thermal','drone');
CREATE TYPE detection_type_enum     AS ENUM ('human','animal','vehicle','fire','gunshot','unknown');
CREATE TYPE threat_level_enum       AS ENUM ('low','medium','high','critical');
CREATE TYPE alert_type_enum         AS ENUM ('poaching_suspect','intruder','fire','gunshot','animal_distress','other');
CREATE TYPE alarm_type_enum         AS ENUM ('siren','bell','strobe','speaker','combined');
CREATE TYPE alarm_trigger_by_enum   AS ENUM ('ai_detection','manual','schedule','auto_incident','unacknowledged_incident','scheduled_test');
CREATE TYPE sms_message_type_enum   AS ENUM ('general','incident','ai_alert','manpower','system','test','broadcast');
CREATE TYPE sms_status_enum         AS ENUM ('pending','sent','failed','delivered');
CREATE TYPE simulation_type_enum    AS ENUM ('incident','ai_detection','alarm','sms','ranger_movement');
CREATE TYPE model_type_enum         AS ENUM ('detector','classifier','tracker','behavior','fusion');
CREATE TYPE precision_level_enum    AS ENUM ('fp32','fp16','int8');
CREATE TYPE ai_job_type_enum        AS ENUM ('detect','track','behaviour','threat','alert','recalibrate');
CREATE TYPE ai_job_status_enum      AS ENUM ('pending','processing','done','failed');
CREATE TYPE feedback_verdict_enum   AS ENUM ('true_positive','false_positive','uncertain');

-- ============================================================
-- 1. ZONES
-- ============================================================
CREATE TABLE zones (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    center_lat      DECIMAL(10,8),
    center_lng      DECIMAL(11,8),
    boundary_geojson JSONB,
    created_by      INT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active       BOOLEAN DEFAULT TRUE,
    park_type       park_type_enum DEFAULT 'other',
    park_code       VARCHAR(50) UNIQUE,
    is_registered   BOOLEAN DEFAULT FALSE,
    buffer_radius   INT DEFAULT 500,
    boundary_center_lat DECIMAL(10,8),
    boundary_center_lng DECIMAL(11,8),
    area_km2        DECIMAL(12,2)
);
CREATE INDEX idx_zones_active     ON zones(is_active);
CREATE INDEX idx_zones_park_type  ON zones(park_type);
CREATE INDEX idx_zones_registered ON zones(is_registered);

-- ============================================================
-- 2. USERS
-- ============================================================
CREATE TABLE users (
    id             SERIAL PRIMARY KEY,
    email          VARCHAR(255) UNIQUE NOT NULL,
    phone          VARCHAR(20),
    password_hash  VARCHAR(255) NOT NULL,
    full_name      VARCHAR(255) NOT NULL,
    role           user_role_enum NOT NULL,
    zone_id        INT REFERENCES zones(id) ON DELETE SET NULL,
    is_active      BOOLEAN DEFAULT TRUE,
    is_online      BOOLEAN DEFAULT FALSE,
    last_seen      TIMESTAMP,
    last_online    TIMESTAMP,
    is_on_duty     BOOLEAN DEFAULT FALSE,
    badge_number   VARCHAR(50),
    fcm_token      VARCHAR(255),
    profile_image  VARCHAR(255),
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by     INT
);
CREATE INDEX idx_users_role    ON users(role);
CREATE INDEX idx_users_zone    ON users(zone_id);
CREATE INDEX idx_users_active  ON users(is_active);
CREATE INDEX idx_users_online  ON users(is_online);

-- ============================================================
-- 3. RANGER AVAILABILITY
-- ============================================================
CREATE TABLE ranger_availability (
    ranger_id           INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    is_available        BOOLEAN DEFAULT TRUE,
    current_incident_id INT,
    last_status_update  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    shift_start         TIME,
    shift_end           TIME,
    days_available      JSONB
);

-- ============================================================
-- 4. RANGER LIVE TRACKING
-- ============================================================
CREATE TABLE ranger_live_tracking (
    ranger_id    INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    current_lat  DECIMAL(10,8),
    current_lng  DECIMAL(11,8),
    heading      DECIMAL(5,2),
    speed        DECIMAL(6,2),
    last_update  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_offline   BOOLEAN DEFAULT FALSE
);

-- 4b. RANGER LOCATION HISTORY
CREATE TABLE ranger_location_history (
    id          BIGSERIAL PRIMARY KEY,
    ranger_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    lat         DECIMAL(10,8) NOT NULL,
    lng         DECIMAL(11,8) NOT NULL,
    heading     DECIMAL(5,2) DEFAULT 0,
    speed       DECIMAL(6,2) DEFAULT 0,
    incident_id INT,
    timestamp   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_rlh_ranger_time ON ranger_location_history(ranger_id, timestamp);
CREATE INDEX idx_rlh_timestamp   ON ranger_location_history(timestamp);

-- 4c. SCOUT LIVE TRACKING
CREATE TABLE scout_live_tracking (
    scout_id    INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    current_lat DECIMAL(10,8),
    current_lng DECIMAL(11,8),
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_offline  BOOLEAN DEFAULT FALSE
);

-- 4d. SCOUT LOCATION HISTORY
CREATE TABLE scout_location_history (
    id        BIGSERIAL PRIMARY KEY,
    scout_id  INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    lat       DECIMAL(10,8) NOT NULL,
    lng       DECIMAL(11,8) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_slh_scout_time ON scout_location_history(scout_id, timestamp);
CREATE INDEX idx_slh_timestamp  ON scout_location_history(timestamp);

-- 4e. RANGER PATROL ROUTES
CREATE TABLE ranger_patrol_routes (
    id           SERIAL PRIMARY KEY,
    ranger_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    zone_id      INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    route_name   VARCHAR(255) NOT NULL,
    start_lat    DECIMAL(10,8) NOT NULL,
    start_lng    DECIMAL(11,8) NOT NULL,
    end_lat      DECIMAL(10,8),
    end_lng      DECIMAL(11,8),
    route_geojson JSONB,
    notes        TEXT,
    is_active    BOOLEAN DEFAULT TRUE,
    created_by   INT REFERENCES users(id) ON DELETE SET NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_rpr_ranger ON ranger_patrol_routes(ranger_id);
CREATE INDEX idx_rpr_zone   ON ranger_patrol_routes(zone_id);

-- ============================================================
-- 5. INCIDENTS
-- ============================================================
CREATE TABLE incidents (
    id             SERIAL PRIMARY KEY,
    reporter_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    reporter_type  reporter_type_enum NOT NULL,
    zone_id        INT REFERENCES zones(id) ON DELETE SET NULL,
    category       incident_category_enum NOT NULL,
    severity       severity_enum DEFAULT 'medium',
    description    TEXT,
    location_lat   DECIMAL(10,8) NOT NULL,
    location_lng   DECIMAL(11,8) NOT NULL,
    media_urls     JSONB,
    status         incident_status_enum DEFAULT 'reported',
    reported_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    acknowledged_by INT REFERENCES users(id) ON DELETE SET NULL,
    acknowledged_at TIMESTAMP,
    resolved_at    TIMESTAMP,
    is_simulated   BOOLEAN DEFAULT FALSE
);
CREATE INDEX idx_incidents_zone       ON incidents(zone_id);
CREATE INDEX idx_incidents_status     ON incidents(status);
CREATE INDEX idx_incidents_severity   ON incidents(severity);
CREATE INDEX idx_incidents_reported   ON incidents(reported_at);

-- ============================================================
-- 6. INCIDENT RESPONSES
-- ============================================================
CREATE TABLE incident_responses (
    id           SERIAL PRIMARY KEY,
    incident_id  INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    ranger_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    status_update response_status_enum NOT NULL,
    notes        TEXT,
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ir_incident ON incident_responses(incident_id);

-- ============================================================
-- 7. INCIDENT ASSIGNMENTS
-- ============================================================
CREATE TABLE incident_assignments (
    id          SERIAL PRIMARY KEY,
    incident_id INT NOT NULL REFERENCES incidents(id) ON DELETE CASCADE,
    ranger_id   INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    assigned_by INT REFERENCES users(id) ON DELETE SET NULL,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status      assignment_status_enum DEFAULT 'pending',
    notes       TEXT
);
CREATE INDEX idx_ia_incident ON incident_assignments(incident_id);
CREATE INDEX idx_ia_ranger   ON incident_assignments(ranger_id);

-- ============================================================
-- 8. MESSAGES
-- ============================================================
CREATE TABLE messages (
    id                      SERIAL PRIMARY KEY,
    sender_id               INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipient_id            INT REFERENCES users(id) ON DELETE CASCADE,
    incident_id             INT REFERENCES incidents(id) ON DELETE SET NULL,
    zone_id                 INT REFERENCES zones(id) ON DELETE SET NULL,
    message_type            message_type_enum DEFAULT 'general',
    subject                 VARCHAR(255),
    content                 TEXT NOT NULL,
    severity                severity_enum DEFAULT 'medium',
    is_broadcast            BOOLEAN DEFAULT FALSE,
    is_read                 BOOLEAN DEFAULT FALSE,
    read_at                 TIMESTAMP,
    requires_acknowledgment BOOLEAN DEFAULT FALSE,
    acknowledged_at         TIMESTAMP,
    parent_message_id       INT REFERENCES messages(id) ON DELETE CASCADE,
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_msg_sender    ON messages(sender_id);
CREATE INDEX idx_msg_recipient ON messages(recipient_id);
CREATE INDEX idx_msg_incident  ON messages(incident_id);
CREATE INDEX idx_msg_zone      ON messages(zone_id);
CREATE INDEX idx_msg_type      ON messages(message_type);

-- ============================================================
-- 9. MESSAGE ATTACHMENTS
-- ============================================================
CREATE TABLE message_attachments (
    id          SERIAL PRIMARY KEY,
    message_id  INT NOT NULL REFERENCES messages(id) ON DELETE CASCADE,
    file_url    VARCHAR(500) NOT NULL,
    file_type   VARCHAR(50),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 10. NOTIFICATIONS
-- ============================================================
CREATE TABLE notifications (
    id           SERIAL PRIMARY KEY,
    user_id      INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    incident_id  INT REFERENCES incidents(id) ON DELETE SET NULL,
    message_id   INT REFERENCES messages(id) ON DELETE SET NULL,
    type         notification_type_enum NOT NULL,
    title        VARCHAR(255) NOT NULL,
    body         TEXT,
    is_read      BOOLEAN DEFAULT FALSE,
    is_delivered BOOLEAN DEFAULT FALSE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at      TIMESTAMP
);
CREATE INDEX idx_notif_user ON notifications(user_id);
CREATE INDEX idx_notif_read ON notifications(is_read);

-- ============================================================
-- 11. AUDIT LOGS
-- ============================================================
CREATE TABLE audit_logs (
    id         SERIAL PRIMARY KEY,
    user_id    INT REFERENCES users(id) ON DELETE SET NULL,
    action     VARCHAR(100) NOT NULL,
    details    JSONB,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_al_user   ON audit_logs(user_id);
CREATE INDEX idx_al_action ON audit_logs(action);

-- ============================================================
-- 12. RATE LIMITS
-- ============================================================
CREATE TABLE rate_limits (
    id            SERIAL PRIMARY KEY,
    ip_address    VARCHAR(45) NOT NULL,
    key_name      VARCHAR(100) NOT NULL,
    attempt_count INT DEFAULT 1,
    first_attempt TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_rl_ip_key ON rate_limits(ip_address, key_name);

-- ============================================================
-- 13. AI ANOMALIES
-- ============================================================
CREATE TABLE ai_anomalies (
    id              SERIAL PRIMARY KEY,
    zone_id         INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    type            anomaly_type_enum NOT NULL,
    severity        severity_enum DEFAULT 'medium',
    description     TEXT,
    confidence      DECIMAL(4,3) DEFAULT 0.700,
    location_lat    DECIMAL(10,8) NOT NULL,
    location_lng    DECIMAL(11,8) NOT NULL,
    radius_meters   INT DEFAULT 200,
    ranger_id       INT REFERENCES users(id) ON DELETE SET NULL,
    scout_id        INT REFERENCES users(id) ON DELETE SET NULL,
    is_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT REFERENCES users(id) ON DELETE SET NULL,
    acknowledged_at TIMESTAMP,
    detected_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_aa_zone_time ON ai_anomalies(zone_id, detected_at);
CREATE INDEX idx_aa_severity  ON ai_anomalies(severity);
CREATE INDEX idx_aa_type      ON ai_anomalies(type);

-- ============================================================
-- 14. ZONE NOTIFICATION SETTINGS
-- ============================================================
CREATE TABLE zone_notification_settings (
    zone_id                  INT PRIMARY KEY REFERENCES zones(id) ON DELETE CASCADE,
    sms_enabled              BOOLEAN DEFAULT TRUE,
    sms_incident_alerts      BOOLEAN DEFAULT TRUE,
    sms_acknowledgment       BOOLEAN DEFAULT TRUE,
    sms_manpower_requests    BOOLEAN DEFAULT TRUE,
    sms_alarm_triggers       BOOLEAN DEFAULT TRUE,
    email_enabled            BOOLEAN DEFAULT FALSE,
    push_enabled             BOOLEAN DEFAULT TRUE,
    alarm_enabled            BOOLEAN DEFAULT TRUE,
    alarm_delay_seconds      INT DEFAULT 120,
    ai_detection_enabled     BOOLEAN DEFAULT TRUE,
    ai_confidence_threshold  INT DEFAULT 70,
    auto_create_incidents    BOOLEAN DEFAULT TRUE,
    notify_rangers           BOOLEAN DEFAULT TRUE,
    notify_supervisors       BOOLEAN DEFAULT TRUE,
    notify_admin             BOOLEAN DEFAULT TRUE,
    updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 15. ZONE SYSTEM SETTINGS
-- ============================================================
CREATE TABLE zone_system_settings (
    zone_id                     INT PRIMARY KEY REFERENCES zones(id) ON DELETE CASCADE,
    ai_enabled                  BOOLEAN DEFAULT TRUE,
    ai_confidence_min           INT DEFAULT 70,
    ai_auto_create_alert        BOOLEAN DEFAULT TRUE,
    ai_auto_trigger_alarm       BOOLEAN DEFAULT FALSE,
    ai_detection_types          VARCHAR(255) DEFAULT 'human,animal,vehicle,fire,gunshot',
    cctv_retention_days         INT DEFAULT 30,
    cctv_default_quality        VARCHAR(20) DEFAULT '1080p',
    cctv_auto_record            BOOLEAN DEFAULT TRUE,
    cctv_snapshot_dir           VARCHAR(255) DEFAULT 'uploads/cctv/',
    alarm_default_type          VARCHAR(20) DEFAULT 'siren',
    alarm_siren_duration        INT DEFAULT 180,
    alarm_auto_stop             BOOLEAN DEFAULT TRUE,
    alarm_sms_blast             BOOLEAN DEFAULT TRUE,
    notif_sms                   BOOLEAN DEFAULT TRUE,
    notif_email                 BOOLEAN DEFAULT FALSE,
    notif_push                  BOOLEAN DEFAULT TRUE,
    notif_on_incident           BOOLEAN DEFAULT TRUE,
    notif_on_ai_alert           BOOLEAN DEFAULT TRUE,
    notif_on_alarm              BOOLEAN DEFAULT TRUE,
    notif_on_manpower           BOOLEAN DEFAULT TRUE,
    notif_offline_reminder      BOOLEAN DEFAULT TRUE,
    perm_rangers_ack            BOOLEAN DEFAULT TRUE,
    perm_rangers_trigger_alarm  BOOLEAN DEFAULT FALSE,
    perm_rangers_request_manpower BOOLEAN DEFAULT TRUE,
    perm_scouts_report          BOOLEAN DEFAULT TRUE,
    perm_scouts_see_sensitive   BOOLEAN DEFAULT FALSE,
    perm_tourism_see_risk       BOOLEAN DEFAULT TRUE,
    updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 16. USER PREFERENCES
-- ============================================================
CREATE TABLE user_preferences (
    user_id                  INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    theme                    VARCHAR(20) DEFAULT 'light',
    language                 VARCHAR(10) DEFAULT 'en',
    timezone                 VARCHAR(50) DEFAULT 'Africa/Lusaka',
    date_format              VARCHAR(50) DEFAULT 'M j, Y H:i',
    items_per_page           INT DEFAULT 25,
    email_notifications      BOOLEAN DEFAULT TRUE,
    sms_notifications        BOOLEAN DEFAULT TRUE,
    push_notifications       BOOLEAN DEFAULT TRUE,
    notify_incident_updates  BOOLEAN DEFAULT TRUE,
    notify_ranger_responses  BOOLEAN DEFAULT TRUE,
    notify_safety_alerts     BOOLEAN DEFAULT TRUE,
    notify_nearby_incidents  BOOLEAN DEFAULT TRUE,
    notify_manpower          BOOLEAN DEFAULT TRUE,
    notify_alarms            BOOLEAN DEFAULT TRUE,
    quiet_hours_start        TIME,
    quiet_hours_end          TIME,
    updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 17. LODGES
-- ============================================================
CREATE TABLE lodges (
    id           SERIAL PRIMARY KEY,
    zone_id      INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    name         VARCHAR(255) NOT NULL,
    description  TEXT,
    location_lat DECIMAL(10,8),
    location_lng DECIMAL(11,8),
    is_active    BOOLEAN DEFAULT TRUE,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_lodges_zone ON lodges(zone_id);

-- ============================================================
-- 18. SETTINGS
-- ============================================================
CREATE TABLE settings (
    id            SERIAL PRIMARY KEY,
    setting_key   VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_by    INT
);
CREATE INDEX idx_settings_group ON settings(setting_group);
CREATE INDEX idx_settings_key   ON settings(setting_key);

-- ============================================================
-- 19. CCTV CAMERAS
-- ============================================================
CREATE TABLE cctv_cameras (
    id                    SERIAL PRIMARY KEY,
    zone_id               INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    camera_name           VARCHAR(255) NOT NULL,
    camera_code           VARCHAR(50),
    stream_url            VARCHAR(500),
    location_lat          DECIMAL(10,8),
    location_lng          DECIMAL(11,8),
    camera_type           camera_type_enum DEFAULT 'fixed',
    resolution            VARCHAR(20) DEFAULT '1080p',
    is_active             BOOLEAN DEFAULT TRUE,
    is_recording          BOOLEAN DEFAULT FALSE,
    last_seen             TIMESTAMP,
    ip_address            VARCHAR(45),
    port                  INT DEFAULT 554,
    username              VARCHAR(100),
    password              VARCHAR(255),
    recording_storage_days INT DEFAULT 30,
    last_ping             TIMESTAMP,
    status                VARCHAR(20) DEFAULT 'offline',
    ai_enabled            BOOLEAN DEFAULT TRUE,
    motion_detection      BOOLEAN DEFAULT TRUE,
    human_detection       BOOLEAN DEFAULT TRUE,
    animal_detection      BOOLEAN DEFAULT TRUE,
    vehicle_detection     BOOLEAN DEFAULT FALSE,
    detection_sensitivity INT DEFAULT 50,
    created_by            INT,
    created_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_cam_zone   ON cctv_cameras(zone_id);
CREATE INDEX idx_cam_active ON cctv_cameras(is_active);

-- ============================================================
-- 20. AI DETECTIONS (legacy)
-- ============================================================
CREATE TABLE ai_detections (
    id             SERIAL PRIMARY KEY,
    camera_id      INT REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    zone_id        INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    detection_type detection_type_enum NOT NULL,
    confidence     DECIMAL(4,3) DEFAULT 0.700,
    is_threat      BOOLEAN DEFAULT FALSE,
    threat_level   threat_level_enum DEFAULT 'low',
    snapshot_url   VARCHAR(500),
    image_url      VARCHAR(500),
    video_clip_url VARCHAR(500),
    bounding_box   JSONB,
    location_lat   DECIMAL(10,8),
    location_lng   DECIMAL(11,8),
    is_resolved    BOOLEAN DEFAULT FALSE,
    resolved_by    INT,
    resolved_at    TIMESTAMP,
    incident_id    INT,
    notes          TEXT,
    ai_model_version VARCHAR(50),
    processing_time_ms INT,
    detected_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ad_zone     ON ai_detections(zone_id);
CREATE INDEX idx_ad_camera   ON ai_detections(camera_id);
CREATE INDEX idx_ad_detected ON ai_detections(detected_at);
CREATE INDEX idx_ad_threat   ON ai_detections(is_threat);

-- ============================================================
-- 21. AI ALERTS
-- ============================================================
CREATE TABLE ai_alerts (
    id              SERIAL PRIMARY KEY,
    detection_id    INT REFERENCES ai_detections(id) ON DELETE SET NULL,
    zone_id         INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    alert_type      alert_type_enum NOT NULL,
    severity        severity_enum DEFAULT 'medium',
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    location_lat    DECIMAL(10,8),
    location_lng    DECIMAL(11,8),
    is_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT REFERENCES users(id) ON DELETE SET NULL,
    acknowledged_at TIMESTAMP,
    is_resolved     BOOLEAN DEFAULT FALSE,
    resolved_at     TIMESTAMP,
    escalated       BOOLEAN DEFAULT FALSE,
    escalated_at    TIMESTAMP,
    notification_sent BOOLEAN DEFAULT FALSE,
    sms_sent        BOOLEAN DEFAULT FALSE,
    alarm_triggered BOOLEAN DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_aia_zone ON ai_alerts(zone_id);
CREATE INDEX idx_aia_ack  ON ai_alerts(is_acknowledged);

-- ============================================================
-- 22. ALARM SYSTEMS
-- ============================================================
CREATE TABLE alarm_systems (
    id                          SERIAL PRIMARY KEY,
    zone_id                     INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    alarm_name                  VARCHAR(255) NOT NULL,
    alarm_code                  VARCHAR(60),
    alarm_type                  alarm_type_enum DEFAULT 'siren',
    sound_url                   VARCHAR(500),
    sound_volume                INT DEFAULT 80,
    siren_duration              INT DEFAULT 180,
    trigger_delay_seconds       INT DEFAULT 120,
    auto_sound_on_incident      BOOLEAN DEFAULT TRUE,
    api_endpoint                VARCHAR(500),
    api_key                     VARCHAR(255),
    trigger_duration            INT DEFAULT 60,
    auto_trigger                BOOLEAN DEFAULT TRUE,
    max_acknowledge_time_seconds INT DEFAULT 120,
    location_lat                DECIMAL(10,8),
    location_lng                DECIMAL(11,8),
    ip_address                  VARCHAR(45),
    port                        INT,
    status                      VARCHAR(20) DEFAULT 'offline',
    is_active                   BOOLEAN DEFAULT TRUE,
    last_triggered              TIMESTAMP,
    trigger_count               INT DEFAULT 0,
    created_by                  INT,
    created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_as_zone   ON alarm_systems(zone_id);
CREATE INDEX idx_as_active ON alarm_systems(is_active);

-- ============================================================
-- 23. ALARM TRIGGERS
-- ============================================================
CREATE TABLE alarm_triggers (
    id               SERIAL PRIMARY KEY,
    alarm_id         INT REFERENCES alarm_systems(id) ON DELETE SET NULL,
    alert_id         INT REFERENCES ai_alerts(id) ON DELETE SET NULL,
    incident_id      INT REFERENCES incidents(id) ON DELETE SET NULL,
    zone_id          INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    triggered_by     alarm_trigger_by_enum DEFAULT 'manual',
    trigger_reason   VARCHAR(255),
    triggered_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    stopped_at       TIMESTAMP,
    duration_seconds INT,
    was_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by  INT REFERENCES users(id) ON DELETE SET NULL,
    acknowledged_at  TIMESTAMP,
    notes            TEXT
);
CREATE INDEX idx_at_zone     ON alarm_triggers(zone_id);
CREATE INDEX idx_at_alarm    ON alarm_triggers(alarm_id);
CREATE INDEX idx_at_alert    ON alarm_triggers(alert_id);
CREATE INDEX idx_at_incident ON alarm_triggers(incident_id);
CREATE INDEX idx_at_active   ON alarm_triggers(stopped_at);

-- ============================================================
-- 24. SMS LOGS
-- ============================================================
CREATE TABLE sms_logs (
    id               SERIAL PRIMARY KEY,
    user_id          INT REFERENCES users(id) ON DELETE SET NULL,
    phone            VARCHAR(20) NOT NULL,
    message          TEXT NOT NULL,
    message_type     sms_message_type_enum DEFAULT 'general',
    incident_id      INT,
    alert_id         INT,
    status           sms_status_enum DEFAULT 'pending',
    provider         VARCHAR(50),
    provider_ref     VARCHAR(120),
    gateway_response TEXT,
    error_message    TEXT,
    segments         SMALLINT DEFAULT 1,
    sent_at          TIMESTAMP,
    delivered_at     TIMESTAMP,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sl_user    ON sms_logs(user_id);
CREATE INDEX idx_sl_phone   ON sms_logs(phone);
CREATE INDEX idx_sl_status  ON sms_logs(status);
CREATE INDEX idx_sl_created ON sms_logs(created_at);

-- ============================================================
-- 25. ADMIN AI SETTINGS
-- ============================================================
CREATE TABLE admin_ai_settings (
    admin_id             INT PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
    sound_alerts_enabled BOOLEAN DEFAULT TRUE,
    auto_ack_low_risk    BOOLEAN DEFAULT FALSE,
    auto_ack_minutes     INT DEFAULT 10,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 26. ZONE AI SETTINGS
-- ============================================================
CREATE TABLE zone_ai_settings (
    zone_id              INT PRIMARY KEY REFERENCES zones(id) ON DELETE CASCADE,
    sound_alerts_enabled BOOLEAN DEFAULT TRUE,
    auto_ack_low_risk    BOOLEAN DEFAULT FALSE,
    auto_ack_minutes     INT DEFAULT 10,
    updated_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 27. SIMULATION CONTROLS
-- ============================================================
CREATE TABLE simulation_controls (
    id              SERIAL PRIMARY KEY,
    zone_id         INT REFERENCES zones(id) ON DELETE CASCADE,
    name            VARCHAR(255) NOT NULL,
    description     TEXT,
    simulation_type simulation_type_enum NOT NULL,
    is_enabled      BOOLEAN DEFAULT FALSE,
    parameters      JSONB,
    started_at      TIMESTAMP,
    stopped_at      TIMESTAMP,
    created_by      INT REFERENCES users(id) ON DELETE SET NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sc_zone    ON simulation_controls(zone_id);
CREATE INDEX idx_sc_enabled ON simulation_controls(is_enabled);

-- ============================================================
-- 28. SIMULATION EVENTS
-- ============================================================
CREATE TABLE simulation_events (
    id          SERIAL PRIMARY KEY,
    zone_id     INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    actor_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    event_type  VARCHAR(60) NOT NULL,
    description TEXT,
    payload     TEXT,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_se_zone  ON simulation_events(zone_id);
CREATE INDEX idx_se_actor ON simulation_events(actor_id);

-- ============================================================
-- 29. SMS MESSAGES
-- ============================================================
CREATE TABLE sms_messages (
    id              SERIAL PRIMARY KEY,
    zone_id         INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    sender_id       INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    recipient_id    INT REFERENCES users(id) ON DELETE SET NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_name  VARCHAR(100),
    message         TEXT NOT NULL,
    template_key    VARCHAR(50),
    status          sms_status_enum DEFAULT 'pending',
    provider        VARCHAR(50),
    provider_ref    VARCHAR(120),
    error_message   TEXT,
    segments        SMALLINT DEFAULT 1,
    sent_at         TIMESTAMP,
    delivered_at    TIMESTAMP,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_sm_zone      ON sms_messages(zone_id);
CREATE INDEX idx_sm_sender    ON sms_messages(sender_id);
CREATE INDEX idx_sm_recipient ON sms_messages(recipient_id);
CREATE INDEX idx_sm_status    ON sms_messages(status);
CREATE INDEX idx_sm_created   ON sms_messages(created_at);

-- ============================================================
-- AI ENGINE v2
-- ============================================================

-- 30. AI MODELS
CREATE TABLE ai_models (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    version         VARCHAR(30) NOT NULL,
    model_type      model_type_enum NOT NULL,
    framework       VARCHAR(50),
    endpoint        VARCHAR(500),
    weights_path    VARCHAR(500),
    input_size      VARCHAR(20) DEFAULT '640x640',
    precision_level precision_level_enum DEFAULT 'fp16',
    avg_latency_ms  INT DEFAULT 0,
    accuracy        DECIMAL(5,4),
    is_active       BOOLEAN DEFAULT TRUE,
    is_default      BOOLEAN DEFAULT FALSE,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(name, version)
);
CREATE INDEX idx_am_active_default ON ai_models(is_active, is_default);

-- 31. AI DETECTIONS V2
CREATE TABLE ai_detections_v2 (
    id                      BIGSERIAL PRIMARY KEY,
    base_detection_id       INT,
    camera_id               INT REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    zone_id                 INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    detection_type          detection_type_enum NOT NULL,
    subclass                VARCHAR(60),
    confidence              DECIMAL(5,4) NOT NULL DEFAULT 0.0000,
    ensemble_confidence     DECIMAL(5,4),
    bbox_x                  DECIMAL(7,4),
    bbox_y                  DECIMAL(7,4),
    bbox_w                  DECIMAL(7,4),
    bbox_h                  DECIMAL(7,4),
    track_id                VARCHAR(60),
    frame_count             INT DEFAULT 1,
    first_seen              TIMESTAMP,
    last_seen               TIMESTAMP,
    behaviour               VARCHAR(60),
    behaviour_score         DECIMAL(5,4),
    heading_degrees         DECIMAL(6,2),
    speed_mps               DECIMAL(6,2),
    is_threat               BOOLEAN DEFAULT FALSE,
    threat_level            threat_level_enum DEFAULT 'low',
    threat_score            DECIMAL(6,3) DEFAULT 0.000,
    inside_zone             BOOLEAN DEFAULT TRUE,
    near_boundary           BOOLEAN DEFAULT FALSE,
    distance_to_boundary_m  INT,
    is_night                BOOLEAN DEFAULT FALSE,
    weather                 VARCHAR(30),
    model_id                INT REFERENCES ai_models(id) ON DELETE SET NULL,
    ensemble_models         JSONB,
    snapshot_url            VARCHAR(500),
    clip_url                VARCHAR(500),
    location_lat            DECIMAL(10,8),
    location_lng            DECIMAL(11,8),
    detected_at             TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at            TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_adv2_zone_time    ON ai_detections_v2(zone_id, detected_at);
CREATE INDEX idx_adv2_type_time    ON ai_detections_v2(detection_type, detected_at);
CREATE INDEX idx_adv2_track        ON ai_detections_v2(track_id);
CREATE INDEX idx_adv2_threat_score ON ai_detections_v2(threat_score);
CREATE INDEX idx_adv2_threat_level ON ai_detections_v2(threat_level);
CREATE INDEX idx_adv2_camera_time  ON ai_detections_v2(camera_id, detected_at);

-- 32. AI TRACKS
CREATE TABLE ai_tracks (
    id                BIGSERIAL PRIMARY KEY,
    track_uid         VARCHAR(60) NOT NULL UNIQUE,
    zone_id           INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    camera_id         INT REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    primary_type      detection_type_enum NOT NULL,
    subclass          VARCHAR(60),
    start_time        TIMESTAMP NOT NULL,
    end_time          TIMESTAMP,
    duration_seconds  INT,
    distance_meters   DECIMAL(10,2),
    avg_speed_mps     DECIMAL(6,2),
    max_speed_mps     DECIMAL(6,2),
    start_lat         DECIMAL(10,8),
    start_lng         DECIMAL(11,8),
    end_lat           DECIMAL(10,8),
    end_lng           DECIMAL(11,8),
    path_geojson      JSONB,
    frame_count       INT DEFAULT 1,
    peak_confidence   DECIMAL(5,4),
    peak_threat_score DECIMAL(6,3),
    is_active         BOOLEAN DEFAULT TRUE,
    is_confirmed      BOOLEAN DEFAULT FALSE,
    last_detected_at  TIMESTAMP
);
CREATE INDEX idx_ait_zone_time ON ai_tracks(zone_id, start_time);
CREATE INDEX idx_ait_active    ON ai_tracks(is_active);
CREATE INDEX idx_ait_type      ON ai_tracks(primary_type);

-- 33. AI THRESHOLDS
CREATE TABLE ai_thresholds (
    id                      SERIAL PRIMARY KEY,
    zone_id                 INT REFERENCES zones(id) ON DELETE CASCADE,
    camera_id               INT REFERENCES cctv_cameras(id) ON DELETE CASCADE,
    detection_type          detection_type_enum,
    min_confidence          DECIMAL(5,4) DEFAULT 0.7000,
    min_ensemble_confidence DECIMAL(5,4) DEFAULT 0.7500,
    min_threat_score        DECIMAL(6,3) DEFAULT 40.000,
    min_track_frames        INT DEFAULT 2,
    cooldown_seconds        INT DEFAULT 45,
    updated_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ath_zone   ON ai_thresholds(zone_id);
CREATE INDEX idx_ath_camera ON ai_thresholds(camera_id);
CREATE INDEX idx_ath_type   ON ai_thresholds(detection_type);

-- 34. AI RULES
CREATE TABLE ai_rules (
    id             SERIAL PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    rule_key       VARCHAR(80) NOT NULL UNIQUE,
    description    TEXT,
    detection_type detection_type_enum,
    condition_json JSONB NOT NULL,
    threat_level   threat_level_enum DEFAULT 'medium',
    weight         DECIMAL(5,2) DEFAULT 10.00,
    is_active      BOOLEAN DEFAULT TRUE,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_ar_active ON ai_rules(is_active);
CREATE INDEX idx_ar_type   ON ai_rules(detection_type);

-- 35. AI FEEDBACK
CREATE TABLE ai_feedback (
    id                   BIGSERIAL PRIMARY KEY,
    detection_v2_id      BIGINT REFERENCES ai_detections_v2(id) ON DELETE SET NULL,
    alert_id             INT REFERENCES ai_alerts(id) ON DELETE SET NULL,
    user_id              INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    verdict              feedback_verdict_enum NOT NULL,
    notes                TEXT,
    original_type        VARCHAR(60),
    original_confidence  DECIMAL(5,4),
    corrected_type       VARCHAR(60),
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_af_detection ON ai_feedback(detection_v2_id);
CREATE INDEX idx_af_alert     ON ai_feedback(alert_id);
CREATE INDEX idx_af_verdict   ON ai_feedback(verdict);

-- 36. AI TELEMETRY
CREATE TABLE ai_telemetry (
    id                 BIGSERIAL PRIMARY KEY,
    model_id           INT REFERENCES ai_models(id) ON DELETE SET NULL,
    camera_id          INT REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    zone_id            INT REFERENCES zones(id) ON DELETE SET NULL,
    window_start       TIMESTAMP NOT NULL,
    window_seconds     INT DEFAULT 60,
    frames_processed   INT DEFAULT 0,
    detections_emitted INT DEFAULT 0,
    avg_latency_ms     INT DEFAULT 0,
    p95_latency_ms     INT DEFAULT 0,
    gpu_util_pct       DECIMAL(5,2),
    cpu_util_pct       DECIMAL(5,2),
    errors             INT DEFAULT 0
);
CREATE INDEX idx_atel_model_time  ON ai_telemetry(model_id, window_start);
CREATE INDEX idx_atel_camera_time ON ai_telemetry(camera_id, window_start);
CREATE INDEX idx_atel_zone_time   ON ai_telemetry(zone_id, window_start);

-- 37. AI QUEUE
CREATE TABLE ai_queue (
    id            BIGSERIAL PRIMARY KEY,
    job_type      ai_job_type_enum NOT NULL,
    priority      SMALLINT DEFAULT 5,
    zone_id       INT REFERENCES zones(id) ON DELETE SET NULL,
    camera_id     INT REFERENCES cctv_cameras(id) ON DELETE SET NULL,
    payload       JSONB NOT NULL,
    status        ai_job_status_enum DEFAULT 'pending',
    attempts      SMALLINT DEFAULT 0,
    max_attempts  SMALLINT DEFAULT 3,
    result        JSONB,
    error         TEXT,
    scheduled_for TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    started_at    TIMESTAMP,
    finished_at   TIMESTAMP
);
CREATE INDEX idx_aq_status_priority ON ai_queue(status, priority, scheduled_for);
CREATE INDEX idx_aq_zone            ON ai_queue(zone_id);
CREATE INDEX idx_aq_camera          ON ai_queue(camera_id);

-- ============================================================
-- PASSWORD RESETS
-- ============================================================
CREATE TABLE password_resets (
    id         SERIAL PRIMARY KEY,
    user_id    INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    email      VARCHAR(150) NOT NULL,
    token_hash VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at    TIMESTAMP
);
CREATE INDEX idx_pr_token   ON password_resets(token_hash);
CREATE INDEX idx_pr_user    ON password_resets(user_id);
CREATE INDEX idx_pr_expires ON password_resets(expires_at);

-- ============================================================
-- TRIGGER FUNCTIONS (replacing MySQL triggers)
-- ============================================================

-- Auto-create zone settings when a new zone is inserted
CREATE OR REPLACE FUNCTION fn_create_zone_settings()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    INSERT INTO zone_notification_settings (zone_id) VALUES (NEW.id) ON CONFLICT DO NOTHING;
    INSERT INTO zone_system_settings (zone_id)       VALUES (NEW.id) ON CONFLICT DO NOTHING;
    INSERT INTO zone_ai_settings (zone_id)           VALUES (NEW.id) ON CONFLICT DO NOTHING;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_create_zone_settings
AFTER INSERT ON zones
FOR EACH ROW EXECUTE FUNCTION fn_create_zone_settings();

-- Mark scout online when location updated
CREATE OR REPLACE FUNCTION fn_mark_scout_online()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    UPDATE users SET is_online = TRUE, last_seen = NOW() WHERE id = NEW.scout_id;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_mark_scout_online
AFTER INSERT ON scout_live_tracking
FOR EACH ROW EXECUTE FUNCTION fn_mark_scout_online();

-- Update ranger availability when assigned to incident
CREATE OR REPLACE FUNCTION fn_update_ranger_on_assignment()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.status = 'accepted' THEN
        UPDATE ranger_availability
        SET is_available = FALSE, current_incident_id = NEW.incident_id
        WHERE ranger_id = NEW.ranger_id;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_ranger_assignment
AFTER INSERT ON incident_assignments
FOR EACH ROW EXECUTE FUNCTION fn_update_ranger_on_assignment();

-- Free ranger when incident resolved
CREATE OR REPLACE FUNCTION fn_free_ranger_on_resolve()
RETURNS TRIGGER LANGUAGE plpgsql AS $$
BEGIN
    IF NEW.status = 'resolved' AND OLD.status <> 'resolved' THEN
        UPDATE ranger_availability
        SET is_available = TRUE, current_incident_id = NULL
        WHERE current_incident_id = NEW.id;
    END IF;
    RETURN NEW;
END;
$$;

CREATE TRIGGER trg_ranger_resolve
AFTER UPDATE ON incidents
FOR EACH ROW EXECUTE FUNCTION fn_free_ranger_on_resolve();

-- ============================================================
-- SEED DATA
-- ============================================================

-- Default zone (Headquarters)
INSERT INTO zones (name, description, center_lat, center_lng, is_active)
VALUES ('Headquarters', 'Main Administrative Zone - Lusaka, Zambia', -15.3875, 28.3228, TRUE);

-- Zambian National Parks
INSERT INTO zones (name, description, park_type, park_code, is_registered, center_lat, center_lng, boundary_center_lat, boundary_center_lng, boundary_geojson, is_active, buffer_radius) VALUES
('South Luangwa National Park',  'One of Africa''s greatest wildlife sanctuaries.', 'national_park', 'SLNP',  FALSE, -13.0000, 31.5000, -13.0000, 31.5000, '{"type":"Polygon","coordinates":[[[31.0,-13.5],[32.0,-13.5],[32.0,-12.5],[31.0,-12.5],[31.0,-13.5]]]}', TRUE, 500),
('Kafue National Park',          'Zambia''s largest national park.', 'national_park', 'KNP',   FALSE, -14.5000, 26.0000, -14.5000, 26.0000, '{"type":"Polygon","coordinates":[[[25.5,-15.0],[26.5,-15.0],[26.5,-14.0],[25.5,-14.0],[25.5,-15.0]]]}', TRUE, 500),
('Lower Zambezi National Park',  'Famous for canoeing safaris and elephant herds.', 'national_park', 'LZNP',  FALSE, -15.5000, 29.5000, -15.5000, 29.5000, '{"type":"Polygon","coordinates":[[[29.0,-16.0],[30.0,-16.0],[30.0,-15.0],[29.0,-15.0],[29.0,-16.0]]]}', TRUE, 500),
('North Luangwa National Park',  'Remote wilderness known for rhino conservation.', 'national_park', 'NLNP',  FALSE, -12.0000, 32.0000, -12.0000, 32.0000, '{"type":"Polygon","coordinates":[[[31.5,-12.5],[32.5,-12.5],[32.5,-11.5],[31.5,-11.5],[31.5,-12.5]]]}', TRUE, 500),
('Liuwa Plain National Park',    'Home to the famous wildebeest migration.', 'national_park', 'LPNP',  FALSE, -14.5000, 23.0000, -14.5000, 23.0000, '{"type":"Polygon","coordinates":[[[22.5,-15.0],[23.5,-15.0],[23.5,-14.0],[22.5,-14.0],[22.5,-15.0]]]}', TRUE, 500),
('Mosi-oa-Tunya National Park',  'Victoria Falls area, home to white rhino.', 'national_park', 'MOTNP', FALSE, -17.9000, 25.8000, -17.9000, 25.8000, '{"type":"Polygon","coordinates":[[[25.5,-18.2],[26.1,-18.2],[26.1,-17.7],[25.5,-17.7],[25.5,-18.2]]]}', TRUE, 500),
('Kasanka National Park',        'Famous for the annual bat migration.', 'national_park', 'KSNP',  FALSE, -12.5000, 30.0000, -12.5000, 30.0000, '{"type":"Polygon","coordinates":[[[29.5,-13.0],[30.5,-13.0],[30.5,-12.0],[29.5,-12.0],[29.5,-13.0]]]}', TRUE, 500),
('Sumbu National Park',          'Located on Lake Tanganyika.', 'national_park', 'SNP',   FALSE, -8.5000,  30.5000, -8.5000,  30.5000, '{"type":"Polygon","coordinates":[[[30.0,-9.0],[31.0,-9.0],[31.0,-8.0],[30.0,-8.0],[30.0,-9.0]]]}', TRUE, 500),
('Lochinvar National Park',      'Important wetland area with diverse bird species.', 'national_park', 'LNP',   FALSE, -15.5000, 27.5000, -15.5000, 27.5000, '{"type":"Polygon","coordinates":[[[27.0,-16.0],[28.0,-16.0],[28.0,-15.0],[27.0,-15.0],[27.0,-16.0]]]}', TRUE, 500),
('Blue Lagoon National Park',    'Wetland paradise on the Kafue Flats.', 'national_park', 'BLNP',  FALSE, -15.5000, 27.0000, -15.5000, 27.0000, '{"type":"Polygon","coordinates":[[[26.5,-16.0],[27.5,-16.0],[27.5,-15.0],[26.5,-15.0],[26.5,-16.0]]]}', TRUE, 500);

-- GMAs
INSERT INTO zones (name, description, park_type, park_code, is_registered, center_lat, center_lng, boundary_center_lat, boundary_center_lng, boundary_geojson, is_active, buffer_radius) VALUES
('Luangwa Game Management Area', 'Critical buffer zone around Luangwa NPs.',    'gma', 'LGMA',  FALSE, -12.5000, 31.5000, -12.5000, 31.5000, '{"type":"Polygon","coordinates":[[[30.5,-13.0],[32.5,-13.0],[32.5,-11.5],[30.5,-11.5],[30.5,-13.0]]]}', TRUE, 500),
('Kafue Game Management Area',   'Buffer zone around Kafue National Park.',      'gma', 'KGMA',  FALSE, -15.0000, 26.0000, -15.0000, 26.0000, '{"type":"Polygon","coordinates":[[[25.0,-15.5],[27.0,-15.5],[27.0,-14.5],[25.0,-14.5],[25.0,-15.5]]]}', TRUE, 500),
('Zambezi Game Management Area', 'Buffer zone along the Zambezi River.',         'gma', 'ZGMA',  FALSE, -15.5000, 29.0000, -15.5000, 29.0000, '{"type":"Polygon","coordinates":[[[28.5,-16.0],[30.0,-16.0],[30.0,-15.0],[28.5,-15.0],[28.5,-16.0]]]}', TRUE, 500),
('Liuwa Game Management Area',   'Buffer zone around Liuwa Plain NP.',           'gma', 'LGMA2', FALSE, -15.0000, 23.0000, -15.0000, 23.0000, '{"type":"Polygon","coordinates":[[[22.0,-15.5],[24.0,-15.5],[24.0,-14.5],[22.0,-14.5],[22.0,-15.5]]]}', TRUE, 500),
('Bangweulu Game Management Area','Protects the Bangweulu Wetlands.',            'gma', 'BGMA',  FALSE, -11.5000, 30.0000, -11.5000, 30.0000, '{"type":"Polygon","coordinates":[[[29.5,-12.0],[30.5,-12.0],[30.5,-11.0],[29.5,-11.0],[29.5,-12.0]]]}', TRUE, 500),
('West Lunga Game Management Area','Buffer zone for West Lunga area.',           'gma', 'WLGMA', FALSE, -12.5000, 25.0000, -12.5000, 25.0000, '{"type":"Polygon","coordinates":[[[24.5,-13.0],[25.5,-13.0],[25.5,-12.0],[24.5,-12.0],[24.5,-13.0]]]}', TRUE, 500);

-- Sample lodges
INSERT INTO lodges (zone_id, name, description, location_lat, location_lng, is_active)
SELECT id, 'Mfuwe Lodge',       'Riverside lodge near South Luangwa', -13.0833, 31.5000, TRUE FROM zones WHERE park_code = 'SLNP' LIMIT 1;
INSERT INTO lodges (zone_id, name, description, location_lat, location_lng, is_active)
SELECT id, 'Bilimungwe Camp',   'Bushcamp in the heart of the park',  -13.1000, 31.5500, TRUE FROM zones WHERE park_code = 'SLNP' LIMIT 1;
INSERT INTO lodges (zone_id, name, description, location_lat, location_lng, is_active)
SELECT id, 'Kafue River Lodge', 'Lodge on the Kafue River',           -14.5000, 26.0000, TRUE FROM zones WHERE park_code = 'KNP'  LIMIT 1;

-- Default global settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('site_name',              'Wildlife Sentinel',                 'general'),
('site_tagline',           'Protecting Zambia''s Wildlife',     'general'),
('timezone',               'Africa/Lusaka',                     'general'),
('language',               'en',                                'general'),
('date_format',            'M j, Y H:i',                        'general'),
('items_per_page',         '25',                                'general'),
('sms_enabled',            '1',                                 'notifications'),
('email_enabled',          '0',                                 'notifications'),
('push_enabled',           '1',                                 'notifications'),
('notify_on_incident',     '1',                                 'notifications'),
('notify_on_ai_alert',     '1',                                 'notifications'),
('notify_on_alarm',        '1',                                 'notifications'),
('notify_on_manpower',     '1',                                 'notifications'),
('notify_offline_users',   '1',                                 'notifications'),
('ai_enabled',             '1',                                 'ai'),
('ai_confidence_min',      '70',                                'ai'),
('ai_auto_create_alert',   '1',                                 'ai'),
('ai_auto_trigger_alarm',  '0',                                 'ai'),
('cctv_retention_days',    '30',                                'ai'),
('cctv_snapshot_dir',      'uploads/cctv/',                     'ai'),
('ai_ingest_key',          '',                                  'ai'),
('session_timeout_min',    '60',                                'security'),
('password_min_length',    '8',                                 'security'),
('password_require_upper', '1',                                 'security'),
('password_require_lower', '1',                                 'security'),
('password_require_num',   '1',                                 'security'),
('password_require_sym',   '0',                                 'security'),
('login_max_attempts',     '5',                                 'security'),
('login_lockout_min',      '5',                                 'security'),
('maintenance_mode',       '0',                                 'maintenance'),
('maintenance_message',    'System under maintenance. Please check back soon.', 'maintenance');

-- AI Models
INSERT INTO ai_models (name, version, model_type, framework, input_size, precision_level, avg_latency_ms, accuracy, is_default, notes) VALUES
('yolov8n-detector',   '1.0', 'detector',   'onnx',   '640x640', 'int8', 25,  0.7200, TRUE,  'Fast general-purpose detector (default)'),
('yolov8s-detector',   '1.0', 'detector',   'onnx',   '640x640', 'fp16', 55,  0.7800, FALSE, 'Balanced accuracy detector'),
('yolov8m-detector',   '1.0', 'detector',   'onnx',   '640x640', 'fp16', 110, 0.8200, FALSE, 'Higher accuracy detector (slower)'),
('thermal-detector',   '1.0', 'detector',   'onnx',   '512x512', 'fp16', 60,  0.7400, FALSE, 'Thermal-specific detector'),
('gunshot-classifier', '1.0', 'classifier', 'tflite', '224x224', 'int8', 12,  0.8900, FALSE, 'Audio-to-gunshot classifier'),
('fire-classifier',    '1.0', 'classifier', 'tflite', '224x224', 'int8', 14,  0.9100, FALSE, 'Fire/smoke classifier'),
('behaviour-lstm',     '1.0', 'behavior',   'onnx',   '30x128',  'fp16', 35,  0.8300, FALSE, 'Behaviour model'),
('fusion-scorer',      '1.0', 'fusion',     'custom', 'n/a',     'fp32', 2,   0.9500, FALSE, 'Weighted ensemble + threat scoring');

-- AI Thresholds
INSERT INTO ai_thresholds (zone_id, camera_id, detection_type, min_confidence, min_ensemble_confidence, min_threat_score, min_track_frames, cooldown_seconds) VALUES
(NULL, NULL, 'human',   0.7500, 0.8000, 55.000, 2, 30),
(NULL, NULL, 'vehicle', 0.7000, 0.7500, 45.000, 2, 45),
(NULL, NULL, 'animal',  0.6500, 0.7000, 25.000, 2, 60),
(NULL, NULL, 'fire',    0.7000, 0.7500, 70.000, 1, 15),
(NULL, NULL, 'gunshot', 0.8000, 0.8500, 85.000, 1, 10),
(NULL, NULL, 'unknown', 0.8500, 0.9000, 60.000, 2, 60);

-- AI Rules
INSERT INTO ai_rules (name, rule_key, description, detection_type, condition_json, threat_level, weight) VALUES
('Human near boundary at night',      'human_boundary_night',   'Human detected near zone boundary at night', 'human',   '{"near_boundary":true,"is_night":true}',                         'high',     25.00),
('Human loitering',                   'human_loitering',        'Human stationary inside sensitive area > 2min', 'human', '{"behaviour":"loitering","min_duration_seconds":120}',           'high',     22.00),
('Vehicle intrusion',                 'vehicle_intrusion',      'Vehicle in restricted core zone',            'vehicle', '{"inside_zone":true,"subclass_in":["pickup","truck","suv"]}',   'high',     30.00),
('High-confidence gunshot',           'gunshot_high_conf',      'Gunshot classifier above 0.85',              'gunshot', '{"min_confidence":0.85}',                                        'critical', 45.00),
('Fire in dry season',                'fire_dry_season',        'Fire or smoke in dry season',                'fire',    '{"min_confidence":0.70,"month_in":[6,7,8,9,10]}',                'critical', 40.00),
('Animal distress',                   'animal_distress',        'Animal with high distress behaviour score',  'animal',  '{"behaviour_score_min":0.75}',                                   'medium',   18.00),
('Rapid approach towards village',    'human_approach_village', 'Human track heading to village at >1m/s',    'human',   '{"behaviour":"approaching","min_speed_mps":1.0,"target":"village"}','high',  28.00);

-- ============================================================
-- VIEWS
-- ============================================================
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
SELECT zone_id,
       COUNT(*) AS count,
       string_agg(id::text, ',') AS incident_ids
FROM incidents
WHERE status = 'reported'
GROUP BY zone_id;

CREATE OR REPLACE VIEW park_registration_status AS
SELECT id, name, park_type, park_code, is_registered,
       CASE WHEN is_registered THEN '✅ Registered' ELSE '🔴 Not Registered' END AS registration_status,
       CASE WHEN is_registered THEN '#28a745' ELSE '#dc3545' END AS status_color,
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
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'ranger' AND u.is_on_duty = TRUE) AS rangers_on_duty,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'ranger' AND u.is_active = TRUE) AS total_rangers,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'scout' AND u.is_online = TRUE) AS scouts_online,
    (SELECT COUNT(*) FROM users u WHERE u.zone_id = z.id AND u.role = 'scout' AND u.is_active = TRUE) AS total_scouts,
    (SELECT COUNT(*) FROM ai_anomalies a WHERE a.zone_id = z.id AND a.detected_at >= NOW() - INTERVAL '24 hours') AS ai_anomalies_24h
FROM zones z
WHERE z.is_active = TRUE;

CREATE OR REPLACE VIEW ai_active_threats AS
SELECT d.id, d.zone_id, z.name AS zone_name, d.camera_id, c.camera_name,
       d.detection_type, d.subclass, d.confidence, d.ensemble_confidence,
       d.threat_level, d.threat_score, d.behaviour, d.behaviour_score,
       d.detected_at, d.snapshot_url, d.location_lat, d.location_lng
FROM ai_detections_v2 d
LEFT JOIN zones z ON d.zone_id = z.id
LEFT JOIN cctv_cameras c ON d.camera_id = c.id
WHERE d.is_threat = TRUE
  AND d.threat_score >= 50
  AND d.detected_at >= NOW() - INTERVAL '1 hour'
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

-- ============================================================
-- Done
-- ============================================================
SELECT '✅ Wildlife Sentinel PostgreSQL schema applied successfully!' AS status;
