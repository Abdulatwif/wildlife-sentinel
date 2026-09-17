<?php
$host    = getenv('DB_HOST')     ?: 'NOT SET';
$port    = getenv('DB_PORT')     ?: '5432';
$dbname  = getenv('DB_NAME')     ?: 'postgres';
$user    = getenv('DB_USER')     ?: 'NOT SET';
$pass    = getenv('DB_PASSWORD') ?: '';
$sslmode = getenv('DB_SSLMODE')  ?: 'require';

echo "<pre>";
echo "DB_HOST    : $host\n";
echo "DB_PORT    : $port\n";
echo "DB_NAME    : $dbname\n";
echo "DB_USER    : $user\n";
echo "DB_SSLMODE : $sslmode\n";
echo "DB_PASSWORD: " . ($pass !== '' ? '(set, ' . strlen($pass) . ' chars)' : 'NOT SET') . "\n\n";

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
echo "DSN: $dsn\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ CONNECTION SUCCESSFUL\n";
    $row = $pdo->query("SELECT version()")->fetch();
    echo "Version: " . $row[0] . "\n";
    $tables = $pdo->query("SELECT COUNT(*) AS c FROM information_schema.tables WHERE table_schema = 'public'")->fetch();
    echo "Tables in public schema: " . $tables['c'] . "\n";
} catch (Exception $e) {
    echo "❌ CONNECTION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
