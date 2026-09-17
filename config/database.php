<?php
// ============================================================
// config/database.php
// Wildlife Sentinel — Database connection & core helpers
// ------------------------------------------------------------
// Backend: Supabase (PostgreSQL via PDO pgsql)
//
// Environment variables (Render / any host):
//   DB_HOST      — Supabase host  (e.g. db.xxxx.supabase.co)
//   DB_PORT      — 5432 (default)
//   DB_NAME      — postgres
//   DB_USER      — postgres
//   DB_PASSWORD  — your Supabase DB password
//
// Local dev: set the same env vars, or override the defaults
// below with your Supabase credentials.
// ============================================================

// ------------------------------------------------------------
// CREDENTIALS
// ------------------------------------------------------------
if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')     ?: 'localhost');
if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT')     ?: '5432');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')     ?: 'postgres');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')     ?: 'postgres');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
if (!defined('DB_SSLMODE')) define('DB_SSLMODE', getenv('DB_SSLMODE')  ?: 'require');

// ------------------------------------------------------------
// EXTERNAL SERVICES
// ------------------------------------------------------------
if (!defined('WS_URL')) {
    define('WS_URL', getenv('WS_URL') ?: 'http://localhost:3001');
}
if (!defined('AI_SERVICE_URL')) {
    define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://localhost:5000');
}

// ------------------------------------------------------------
// SESSION
// ------------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    $secure = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );
    @session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ------------------------------------------------------------
// getDB() — PDO singleton (PostgreSQL)
// ------------------------------------------------------------
if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'pgsql:host=' . DB_HOST
             . ';port='      . DB_PORT
             . ';dbname='    . DB_NAME
             . ';sslmode='   . DB_SSLMODE;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 15,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[WS-DB] Connection failed: ' . $e->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode([
                'success' => false,
                'error'   => 'Database connection failed. Please try again later.',
            ]);
            exit();
        }

        return $pdo;
    }
}

// ------------------------------------------------------------
// pgLastInsertId() — fetch last inserted ID via RETURNING
// Usage: instead of 0 /* TODO: use RETURNING id */, use:
//   $id = pgLastInsertId($pdo, $stmt);
// The INSERT must include "RETURNING id " at the end.
// ------------------------------------------------------------
if (!function_exists('pgLastInsertId')) {
    function pgLastInsertId(PDOStatement $stmt): int {
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($row['id'] ?? 0);
    }
}

// ------------------------------------------------------------
// jsonResponse() — universal JSON output helper (guarded)
// ------------------------------------------------------------
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($data);
        exit();
    }
}

// ------------------------------------------------------------
// Load shared functions (auth, notifications, etc.)
// ------------------------------------------------------------
require_once __DIR__ . '/../includes/functions.php';
