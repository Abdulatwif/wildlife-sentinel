<?php
// Start output buffering to prevent "headers already sent" issues
if (!ob_get_level()) { ob_start(); }

// ============================================================
// config/database.php
// Wildlife Sentinel — Database connection (MySQL / TiDB Cloud)
//
// Environment variables (Render):
//   DB_HOST     — TiDB host (e.g. gateway01.eu-central-1.prod.aws.tidbcloud.com)
//   DB_PORT     — 4000
//   DB_NAME     — wildlife_sentinel
//   DB_USER     — your TiDB username
//   DB_PASSWORD — your TiDB password
//   DB_TLS      — 1 (TiDB requires SSL)
// ============================================================

// ------------------------------------------------------------
// CREDENTIALS
// ------------------------------------------------------------
$envHost  = getenv('DB_HOST');
$isHosted = (is_string($envHost) && $envHost !== '' && $envHost !== 'localhost' && $envHost !== '127.0.0.1');

if ($isHosted) {
    if (!defined('DB_HOST'))    define('DB_HOST',    $envHost);
    if (!defined('DB_PORT'))    define('DB_PORT',    getenv('DB_PORT')     ?: '3306');
    if (!defined('DB_NAME'))    define('DB_NAME',    getenv('DB_NAME')     ?: 'wildlife_sentinel');
    if (!defined('DB_USER'))    define('DB_USER',    getenv('DB_USER')     ?: '');
    if (!defined('DB_PASS'))    define('DB_PASS',    getenv('DB_PASSWORD') ?: '');
    if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');
    $tlsDefault = (stripos($envHost, 'tidbcloud.com') !== false);
    if (!defined('DB_USE_TLS')) {
        $tlsEnv = getenv('DB_TLS');
        define('DB_USE_TLS', $tlsEnv !== false ? ($tlsEnv === '1') : $tlsDefault);
    }
} else {
    if (!defined('DB_HOST'))    define('DB_HOST',    '127.0.0.1');
    if (!defined('DB_PORT'))    define('DB_PORT',    '3306');
    if (!defined('DB_NAME'))    define('DB_NAME',    'wildlife_sentinel');
    if (!defined('DB_USER'))    define('DB_USER',    'root');
    if (!defined('DB_PASS'))    define('DB_PASS',    '');
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
    @session_start();
}

// ------------------------------------------------------------
// getDB() — PDO singleton (MySQL / TiDB)
// ------------------------------------------------------------
if (!function_exists('getDB')) {
    function getDB(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dsn = 'mysql:host=' . DB_HOST
             . ';port='      . DB_PORT
             . ';dbname='    . DB_NAME
             . ';charset='   . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_TIMEOUT            => 15,
        ];

        if (defined('PDO::MYSQL_ATTR_INIT_COMMAND')) {
            $options[PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8mb4';
        }

        // TLS for TiDB Cloud
        if (DB_USE_TLS) {
            $caPath = __DIR__ . '/ca.pem';
            if (is_file($caPath) && defined('PDO::MYSQL_ATTR_SSL_CA')) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
                }
            } else {
                error_log('[WS-DB] TLS enabled but ca.pem missing — skipping cert verify');
                if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
                    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
                }
            }
        }

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
