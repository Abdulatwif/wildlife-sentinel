<?php
// Temporary DB connection test — DELETE THIS FILE after debugging
$host    = getenv('DB_HOST')     ?: 'NOT SET';
$port    = getenv('DB_PORT')     ?: '5432';
$dbname  = getenv('DB_NAME')     ?: 'postgres';
$user    = getenv('DB_USER')     ?: 'NOT SET';
$pass    = getenv('DB_PASSWORD') ?: 'NOT SET';
$sslmode = getenv('DB_SSLMODE')  ?: 'require';

echo "<pre>";
echo "DB_HOST    : $host\n";
echo "DB_PORT    : $port\n";
echo "DB_NAME    : $dbname\n";
echo "DB_USER    : $user\n";
echo "DB_SSLMODE : $sslmode\n";
echo "DB_PASSWORD: " . (($pass !== 'NOT SET' && $pass !== '') ? '(set, ' . strlen($pass) . ' chars)' : 'NOT SET') . "\n\n";

$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=$sslmode";
echo "DSN: $dsn\n\n";

try {
    $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "✅ CONNECTION SUCCESSFUL\n";
    $row = $pdo->query("SELECT version()")->fetch();
    echo "PostgreSQL: " . $row[0] . "\n";
} catch (Exception $e) {
    echo "❌ CONNECTION FAILED\n";
    echo "Error: " . $e->getMessage() . "\n";
}
echo "</pre>";
