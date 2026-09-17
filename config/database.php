<?php
// ============================================================
// config/database.php
// Wildlife Sentinel — Database connection & core helpers
// ------------------------------------------------------------
// Default: XAMPP / MariaDB on localhost.
// Also supports hosted environments (Render, Railway, Fly)
// via DB_HOST env vars and optional TLS.
//
// This file ONLY contains:
//   1. DB credentials
//   2. getDB()   — PDO singleton
//   3. jsonResponse() — JSON output helper (guarded)
//   4. Auto-includes includes/functions.php
//
// Environment variables (optional, for hosted deployments):
//   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD, DB_TLS
//   WS_URL, AI_SERVICE_URL
// ------------------------------------------------------------
// TLS:
//   Place the CA certificate at: config/ca.pem
//   Set env var DB_TLS=1 to enable.
// ============================================================

// ------------------------------------------------------------
// ENVIRONMENT DETECTION
// ------------------------------------------------------------
$envHost = getenv('DB_HOST');
$isHosted = (is_string($envHost) && $envHost !== '' && $envHost !== 'localhost' && $envHost !== '127.0.0.1');

// ------------------------------------------------------------
// CREDENTIALS
// ------------------------------------------------------------
if ($isHosted) {
    // ---- Hosted deployment ----
    if (!defined('DB_HOST'))    define('DB_HOST',    $envHost);
    if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT') ?: '3306');
    if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME') ?: 'wildlife_sentinel');
    if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER') ?: '');
    if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
    // Enable TLS only if explicitly requested OR if the host looks like TiDB Cloud
    $tlsDefault = (stripos($envHost, 'tidbcloud.com') !== false);
    if (!defined('DB_USE_TLS')) {
        $tlsEnv = getenv('DB_TLS');
        define('DB_USE_TLS', $tlsEnv !== false ? ($tlsEnv === '1') : $tlsDefault);
    }
} else {
    // ---- Local XAMPP / MAMP / WAMP / Laragon ----
    if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
    if (!defined('DB_PORT'))    define('DB_PORT',    '3306');
    if (!defined('DB_NAME'))    define('DB_NAME',    'wildlife_sentinel');
    if (!defined('DB_USER'))    define('DB_USER',    'root');
    if (!defined('DB_PASS'))    define('DB_PASS',    '');       // XAMPP default
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
    if (!defined('DB_USE_TLS')) define('DB_USE_TLS', false);
}

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
    // Conservative cookie flags — works on plain HTTP XAMPP
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
// getDB() — PDO singleton
// ------------------------------------------------------------
if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'mysql:host=' . DB_HOST
             . ';port='     . DB_PORT
             . ';dbname='   . DB_NAME
             . ';charset='  . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 15,
        ];

        // Some builds of MariaDB on Windows reject MYSQL_ATTR_INIT_COMMAND.
        // Only add it if the constant exists.
        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES " . DB_CHARSET;
        }

        // ---------- TLS (only for hosted / TiDB) ----------
        if (DB_USE_TLS) {
            $caPath = __DIR__ . '/ca.pem';
            if (is_file($caPath)) {
                if (defined('PDO::MYSQL_ATTR_SSL_CA')) {
                    $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                }
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
            } else {
                // TLS requested but CA missing — disable CA verify so connection
                // still works. Log a warning so admins know to add ca.pem.
                error_log('[WS-DB] TLS enabled but config/ca.pem is missing');
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
        }

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[WS-DB] Connection failed: ' . $e->getMessage());

            // If headers are already sent, don't try to emit JSON.
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