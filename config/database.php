<?php
if (!ob_get_level()) { ob_start(); }

// ============================================================
// config/database.php — Supabase / PostgreSQL
// Render env vars:
//   DB_HOST     = db.xxxx.supabase.co
//   DB_PORT     = 5432
//   DB_NAME     = postgres
//   DB_USER     = postgres
//   DB_PASSWORD = <your supabase db password>
//   DB_SSLMODE  = require
// ============================================================

if (!defined('DB_HOST'))    define('DB_HOST',    getenv('DB_HOST')     ?: 'aws-1-eu-west-1.pooler.supabase.com');
if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT')     ?: '6543');
if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')     ?: 'postgres');
if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')     ?: 'postgres.vpgytgumxyravtzrahaf');
if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
if (!defined('DB_SSLMODE')) define('DB_SSLMODE', getenv('DB_SSLMODE')  ?: 'require');

if (!defined('WS_URL'))         define('WS_URL',         getenv('WS_URL')         ?: 'http://localhost:3001');
if (!defined('AI_SERVICE_URL')) define('AI_SERVICE_URL', getenv('AI_SERVICE_URL') ?: 'http://localhost:5000');

if (session_status() === PHP_SESSION_NONE) {
    // Use file-based sessions (default) — reliable on Render
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.save_path', '/tmp');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
           || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    @session_start();
}

if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'pgsql:host=' . DB_HOST
             . ';port='      . DB_PORT
             . ';dbname='    . DB_NAME
             . ';sslmode='   . DB_SSLMODE
             . ';options=--client_encoding=UTF8';

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_TIMEOUT            => 15,
            ]);
        } catch (PDOException $e) {
            error_log('[WS-DB] Connection failed: ' . $e->getMessage());
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
            }
            echo json_encode(['success' => false, 'error' => 'Database connection failed. Please try again later.']);
            exit();
        }
        return $pdo;
    }
}

if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $status = 200) {
        if (!headers_sent()) { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); }
        echo json_encode($data);
        exit();
    }
}

require_once __DIR__ . '/../includes/functions.php';
