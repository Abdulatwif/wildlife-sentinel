<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf';

echo "<pre>";
echo "Testing correct Supabase pooler host...\n\n";

$attempts = [
    [
        'label' => 'aws-1-eu-west-1 pooler port 6543',
        'host'  => "aws-1-eu-west-1.pooler.supabase.com",
        'port'  => '6543',
        'user'  => "postgres.{$ref}",
    ],
    [
        'label' => 'aws-1-eu-west-1 pooler port 5432',
        'host'  => "aws-1-eu-west-1.pooler.supabase.com",
        'port'  => '5432',
        'user'  => "postgres.{$ref}",
    ],
    [
        'label' => 'aws-0-eu-west-1 pooler port 6543',
        'host'  => "aws-0-eu-west-1.pooler.supabase.com",
        'port'  => '6543',
        'user'  => "postgres.{$ref}",
    ],
    [
        'label' => 'aws-0-eu-west-1 pooler port 5432',
        'host'  => "aws-0-eu-west-1.pooler.supabase.com",
        'port'  => '5432',
        'user'  => "postgres.{$ref}",
    ],
];

foreach ($attempts as $cfg) {
    echo "--- {$cfg['label']} ---\n";
    $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname=postgres;sslmode=require";
    echo "DSN : {$dsn}\n";
    echo "User: {$cfg['user']}\n";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        echo "✅ SUCCESS!\n";
        $row = $pdo->query("SELECT version()")->fetch();
        echo "Version: " . $row[0] . "\n\n";
        echo "=== WORKING CONFIG ===\n";
        echo "DB_HOST={$cfg['host']}\n";
        echo "DB_PORT={$cfg['port']}\n";
        echo "DB_USER={$cfg['user']}\n";
        echo "DB_NAME=postgres\n";
        echo "DB_SSLMODE=require\n";
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        preg_match('/FATAL.*/', $msg, $m);
        echo "❌ " . ($m[0] ?? substr($msg, 0, 120)) . "\n\n";
    }
}
echo "All attempts failed.\n";
echo "</pre>";
