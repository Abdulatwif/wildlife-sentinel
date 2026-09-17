<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf';

echo "<pre>";
echo "Testing all possible Supabase pooler hosts...\n\n";

$hosts = [
    "aws-0-eu-west-1.pooler.supabase.com",
    "aws-0-eu-west-2.pooler.supabase.com",
    "eu-west-1.pooler.supabase.com",
    "pooler.supabase.com",
];

$ports = [6543, 5432];
$users = [
    "postgres.{$ref}",
    "postgres",
];

foreach ($hosts as $host) {
    foreach ($ports as $port) {
        foreach ($users as $user) {
            $dsn = "pgsql:host={$host};port={$port};dbname=postgres;sslmode=require";
            echo "Host: {$host}:{$port} User: {$user}\n";
            try {
                $pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_TIMEOUT => 5,
                ]);
                echo "✅ SUCCESS!\n";
                echo "=== WORKING CONFIG ===\n";
                echo "DB_HOST: {$host}\n";
                echo "DB_PORT: {$port}\n";
                echo "DB_USER: {$user}\n";
                echo "DB_NAME: postgres\n";
                echo "DB_SSLMODE: require\n";
                exit;
            } catch (Exception $e) {
                $msg = $e->getMessage();
                // Shorten error
                if (strpos($msg, 'FATAL') !== false) {
                    preg_match('/FATAL.*/', $msg, $m);
                    echo "❌ " . ($m[0] ?? $msg) . "\n";
                } else {
                    echo "❌ " . substr($msg, 0, 80) . "\n";
                }
            }
        }
    }
}
echo "\nAll attempts failed.\n";
echo "</pre>";
