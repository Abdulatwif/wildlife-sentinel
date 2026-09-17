<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf';

echo "<pre>";
echo "Testing Supabase direct connection...\n\n";

$attempts = [
    'Direct + sslmode=disable' => [
        'dsn'  => "pgsql:host=db.{$ref}.supabase.co;port=5432;dbname=postgres;sslmode=disable",
        'user' => 'postgres',
    ],
    'Direct + sslmode=require' => [
        'dsn'  => "pgsql:host=db.{$ref}.supabase.co;port=5432;dbname=postgres;sslmode=require",
        'user' => 'postgres',
    ],
    'Direct + sslmode=prefer' => [
        'dsn'  => "pgsql:host=db.{$ref}.supabase.co;port=5432;dbname=postgres;sslmode=prefer",
        'user' => 'postgres',
    ],
    'Pooler transaction + sslmode=require' => [
        'dsn'  => "pgsql:host=aws-0-eu-west-1.pooler.supabase.com;port=6543;dbname=postgres;sslmode=require",
        'user' => "postgres.{$ref}",
    ],
];

foreach ($attempts as $label => $cfg) {
    echo "--- {$label} ---\n";
    echo "DSN : {$cfg['dsn']}\n";
    echo "User: {$cfg['user']}\n";
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        echo "✅ SUCCESS\n";
        $row = $pdo->query("SELECT version()")->fetch();
        echo "Version: " . $row[0] . "\n\n";
        echo "=== USE THIS CONNECTION ===\n";
        break;
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    }
}
echo "</pre>";
