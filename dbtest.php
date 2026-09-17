<?php
// Temporary DB connection test — DELETE after debugging
$host   = getenv('DB_HOST')     ?: 'NOT SET';
$port   = getenv('DB_PORT')     ?: '4000';
$dbname = getenv('DB_NAME')     ?: 'wildlife_sentinel';
$user   = getenv('DB_USER')     ?: 'NOT SET';
$pass   = getenv('DB_PASSWORD') ?: '';
$tls    = getenv('DB_TLS')      ?: '1';

echo "<pre>";
echo "DB_HOST    : $host\n";
echo "DB_PORT    : $port\n";
echo "DB_NAME    : $dbname\n";
echo "DB_USER    : $user\n";
echo "DB_TLS     : $tls\n";
echo "DB_PASSWORD: " . ($pass !== '' ? '(set, ' . strlen($pass) . ' chars)' : 'NOT SET') . "\n\n";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
echo "DSN: $dsn\n\n";

try {
    $opts = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10];
    if ($tls === '1' && is_file(__DIR__ . '/config/ca.pem') && defined('PDO::MYSQL_ATTR_SSL_CA')) {
        $opts[PDO::MYSQL_ATTR_SSL_CA] = __DIR__ . '/config/ca.pem';
        echo "SSL CA: config/ca.pem found ✅\n\n";
    } else {
        echo "SSL CA: not found — connecting without cert verify\n\n";
        if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
            $opts[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }
    $pdo = new PDO($dsn, $user, $pass, $opts);
    echo "✅ CONNECTION SUCCESSFUL\n";
    $row = $pdo->query("SELECT version() AS v")->fetch();
    echo "Version: " . ($row['v'] ?? 'unknown') . "\n";
} catch (Exception $e) {
    echo "❌ CONNECTION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
