-- ============================================================
-- WILDLIFE SENTINEL — AI & CCTV Integration Migration
-- PostgreSQL / Supabase Edition
-- Additive migration — safe to run on top of wildlife_sentinel_pg.sql
-- Re-runnable (uses INSERT ... ON CONFLICT DO NOTHING)
-- ============================================================

-- ============================================================
-- 1. CCTV CAMERAS — ensure all columns exist
-- (table already created in wildlife_sentinel_pg.sql)
-- ============================================================
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS ip_address               VARCHAR(45);
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS port                     INT DEFAULT 554;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS username                 VARCHAR(100);
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS password                 VARCHAR(255);
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS recording_storage_days   INT DEFAULT 30;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS last_ping                TIMESTAMP;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS status                   VARCHAR(20) DEFAULT 'offline';
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS ai_enabled               BOOLEAN DEFAULT TRUE;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS motion_detection         BOOLEAN DEFAULT TRUE;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS human_detection          BOOLEAN DEFAULT TRUE;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS animal_detection         BOOLEAN DEFAULT TRUE;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS vehicle_detection        BOOLEAN DEFAULT FALSE;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS detection_sensitivity    INT DEFAULT 50;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS created_by               INT;
ALTER TABLE cctv_cameras ADD COLUMN IF NOT EXISTS updated_at               TIMESTAMP DEFAULT CURRENT_TIMESTAMP;

-- ============================================================
-- 2. AI DETECTIONS — ensure all columns exist
-- ============================================================
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS bounding_box        JSONB;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS image_url           VARCHAR(500);
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS video_clip_url      VARCHAR(500);
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS is_resolved         BOOLEAN DEFAULT FALSE;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS resolved_by         INT;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS resolved_at         TIMESTAMP;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS incident_id         INT;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS notes               TEXT;
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS ai_model_version    VARCHAR(50);
ALTER TABLE ai_detections ADD COLUMN IF NOT EXISTS processing_time_ms  INT;

CREATE INDEX IF NOT EXISTS idx_ad_threat_time ON ai_detections(is_threat, detected_at);

-- ============================================================
-- 3. AI ALERTS — ensure all columns exist
-- ============================================================
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS is_resolved        BOOLEAN DEFAULT FALSE;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS resolved_at        TIMESTAMP;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS escalated          BOOLEAN DEFAULT FALSE;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS escalated_at       TIMESTAMP;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS notification_sent  BOOLEAN DEFAULT FALSE;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS sms_sent           BOOLEAN DEFAULT FALSE;
ALTER TABLE ai_alerts ADD COLUMN IF NOT EXISTS alarm_triggered    BOOLEAN DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_aia_zone_time ON ai_alerts(zone_id, created_at);

-- ============================================================
-- 4. ALARM SYSTEMS — ensure all columns exist
-- ============================================================
ALTER TABLE alarm_systems ADD COLUMN IF NOT EXISTS ip_address  VARCHAR(45);
ALTER TABLE alarm_systems ADD COLUMN IF NOT EXISTS port        INT;
ALTER TABLE alarm_systems ADD COLUMN IF NOT EXISTS status      VARCHAR(20) DEFAULT 'offline';
ALTER TABLE alarm_systems ADD COLUMN IF NOT EXISTS created_by  INT;

-- ============================================================
-- 5. ALARM TRIGGERS — ensure column exists
-- ============================================================
ALTER TABLE alarm_triggers ADD COLUMN IF NOT EXISTS notes TEXT;

-- ============================================================
-- 6. SMS LOGS — ensure all columns exist
-- ============================================================
ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS phone_number         VARCHAR(20);
ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS related_incident_id  INT;
ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS related_alert_id     INT;
ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS provider_message_id  VARCHAR(255);
ALTER TABLE sms_logs ADD COLUMN IF NOT EXISTS error_message        TEXT;

CREATE INDEX IF NOT EXISTS idx_sl_status_time ON sms_logs(status, created_at);

-- ============================================================
-- 7. SMS GATEWAY (provider config)
-- ============================================================
CREATE TABLE IF NOT EXISTS sms_gateway (
    id            SERIAL PRIMARY KEY,
    zone_id       INT REFERENCES zones(id) ON DELETE CASCADE,
    provider_name VARCHAR(100) NOT NULL,
    provider_type VARCHAR(30) DEFAULT 'custom',
    api_key       VARCHAR(255),
    api_secret    VARCHAR(255),
    sender_id     VARCHAR(50),
    account_sid   VARCHAR(255),
    is_active     BOOLEAN DEFAULT TRUE,
    is_default    BOOLEAN DEFAULT FALSE,
    sms_per_minute INT DEFAULT 30,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_sgw_zone   ON sms_gateway(zone_id);
CREATE INDEX IF NOT EXISTS idx_sgw_active ON sms_gateway(is_active);

-- ============================================================
-- 8. SIMULATION CONTROLS — ensure table exists (already in main schema)
-- ============================================================
-- No additional columns needed

-- ============================================================
-- 9. SIMULATION EVENTS — ensure table exists (already in main schema)
-- ============================================================
-- No additional columns needed

-- ============================================================
-- 10. RANGER SIMULATION TRACKS
-- ============================================================
CREATE TABLE IF NOT EXISTS ranger_simulation_tracks (
    id                   SERIAL PRIMARY KEY,
    ranger_id            INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    zone_id              INT NOT NULL REFERENCES zones(id) ON DELETE CASCADE,
    track_name           VARCHAR(255),
    track_points         JSONB NOT NULL,
    is_active            BOOLEAN DEFAULT TRUE,
    loop_track           BOOLEAN DEFAULT TRUE,
    speed_multiplier     DECIMAL(3,2) DEFAULT 1.00,
    started_at           TIMESTAMP,
    last_position_index  INT DEFAULT 0,
    created_by           INT REFERENCES users(id) ON DELETE SET NULL,
    created_at           TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_rst_ranger ON ranger_simulation_tracks(ranger_id);
CREATE INDEX IF NOT EXISTS idx_rst_zone   ON ranger_simulation_tracks(zone_id);

-- ============================================================
-- 11. ZONE NOTIFICATION SETTINGS — ensure columns exist
-- ============================================================
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS sms_incident_alerts    BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS sms_acknowledgment     BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS sms_manpower_requests  BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS sms_alarm_triggers     BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS email_enabled          BOOLEAN DEFAULT FALSE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS push_enabled           BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS notify_rangers         BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS notify_supervisors     BOOLEAN DEFAULT TRUE;
ALTER TABLE zone_notification_settings ADD COLUMN IF NOT EXISTS notify_admin           BOOLEAN DEFAULT TRUE;

-- ============================================================
-- 12. ZONE SYSTEM SETTINGS — ensure columns exist
-- ============================================================
-- All columns already present in wildlife_sentinel_pg.sql

-- ============================================================
-- 13. ZONE AI SETTINGS
-- ============================================================
-- Already created in main schema

-- ============================================================
-- 14. ADMIN AI SETTINGS
-- ============================================================
-- Already created in main schema

-- ============================================================
-- 15. SMS MESSAGES — already in main schema
-- ============================================================
-- No additional columns needed

-- ============================================================
-- SEED: ensure every active zone has a settings row
-- ============================================================
INSERT INTO zone_notification_settings (zone_id)
SELECT id FROM zones WHERE is_active = TRUE
ON CONFLICT (zone_id) DO NOTHING;

INSERT INTO zone_system_settings (zone_id)
SELECT id FROM zones WHERE is_active = TRUE
ON CONFLICT (zone_id) DO NOTHING;

INSERT INTO zone_ai_settings (zone_id)
SELECT id FROM zones WHERE is_active = TRUE
ON CONFLICT (zone_id) DO NOTHING;

-- ============================================================
-- Done
-- ============================================================
SELECT '✅ AI & CCTV Integration migration applied (PostgreSQL)' AS status;
