<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf'; // your project ref

echo "<pre>";
echo "Testing multiple Supabase connection methods...\n\n";

$attempts = [
    'Direct IPv4 (port 5432)' => [
        'dsn'  => "pgsql:host=db.{$ref}.supabase.co;port=5432;dbname=postgres;sslmode=require",
        'user' => 'postgres',
        'pass' => $pass,
    ],
    'Pooler session (port 5432)' => [
        'dsn'  => "pgsql:host=aws-0-eu-west-1.pooler.supabase.com;port=5432;dbname=postgres;sslmode=require",
        'user' => "postgres.{$ref}",
        'pass' => $pass,
    ],
    'Pooler transaction (port 6543)' => [
        'dsn'  => "pgsql:host=aws-0-eu-west-1.pooler.supabase.com;port=6543;dbname=postgres;sslmode=require",
        'user' => "postgres.{$ref}",
        'pass' => $pass,
    ],
    'Direct no SSL (port 5432)' => [
        'dsn'  => "pgsql:host=db.{$ref}.supabase.co;port=5432;dbname=postgres;sslmode=disable",
        'user' => 'postgres',
        'pass' => $pass,
    ],
];

foreach ($attempts as $label => $cfg) {
    echo "--- {$label} ---\n";
    echo "DSN : {$cfg['dsn']}\n";
    echo "User: {$cfg['user']}\n";
    try {
        $pdo = new PDO($cfg['dsn'], $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE  => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT  => 10,
        ]);
        echo "✅ SUCCESS\n";
        $row = $pdo->query("SELECT version()")->fetch();
        echo "Version: " . $row[0] . "\n\n";
        break; // stop on first success
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    }
}
echo "</pre>";
