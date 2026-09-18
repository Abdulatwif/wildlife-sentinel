<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf';

echo "<pre>";
echo "Testing Supabase connections with SNI...\n\n";

$attempts = [
    [
        'label' => 'Pooler 6543 with sslmode=require',
        'host'  => 'aws-0-eu-west-1.pooler.supabase.com',
        'port'  => '6543',
        'user'  => "postgres.{$ref}",
        'ssl'   => 'require',
    ],
    [
        'label' => 'Pooler 5432 with sslmode=require',
        'host'  => 'aws-0-eu-west-1.pooler.supabase.com',
        'port'  => '5432',
        'user'  => "postgres.{$ref}",
        'ssl'   => 'require',
    ],
    // Try with just the project ref as hostname prefix
    [
        'label' => 'Pooler with project prefix hostname',
        'host'  => "{$ref}.pooler.supabase.com",
        'port'  => '6543',
        'user'  => "postgres.{$ref}",
        'ssl'   => 'require',
    ],
    [
        'label' => 'Pooler with project prefix hostname port 5432',
        'host'  => "{$ref}.pooler.supabase.com",
        'port'  => '5432',
        'user'  => "postgres",
        'ssl'   => 'require',
    ],
];

foreach ($attempts as $cfg) {
    echo "--- {$cfg['label']} ---\n";
    $dsn = "pgsql:host={$cfg['host']};port={$cfg['port']};dbname=postgres;sslmode={$cfg['ssl']}";
    echo "DSN : {$dsn}\n";
    echo "User: {$cfg['user']}\n";
    try {
        $pdo = new PDO($dsn, $cfg['user'], $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 8,
        ]);
        echo "✅ SUCCESS!\n";
        $row = $pdo->query("SELECT version()")->fetch();
        echo "Version: " . $row[0] . "\n";
        echo "\n=== WORKING CONFIG ===\n";
        echo "DB_HOST={$cfg['host']}\n";
        echo "DB_PORT={$cfg['port']}\n";
        echo "DB_USER={$cfg['user']}\n";
        echo "DB_NAME=postgres\n";
        echo "DB_SSLMODE={$cfg['ssl']}\n";
        exit;
    } catch (Exception $e) {
        $msg = $e->getMessage();
        preg_match('/FATAL.*/', $msg, $m);
        echo "❌ " . ($m[0] ?? substr($msg, 0, 100)) . "\n\n";
    }
}

// Last resort — try Supabase REST API ping
echo "--- Supabase REST API ping ---\n";
$ch = curl_init("https://{$ref}.supabase.co/rest/v1/");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 8);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
echo "HTTP status: {$code}\n";
echo ($code > 0 ? "✅ REST API reachable" : "❌ REST API unreachable") . "\n";

echo "\nAll DB attempts failed.\n";
echo "</pre>";
