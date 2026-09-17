<?php
$pass = getenv('DB_PASSWORD') ?: '';
$ref  = 'vpgytgumxyravtzrahaf';

echo "<pre>";
echo "Testing Supabase connections...\n\n";

// Resolve IPv4 address of the direct host
$directHost = "db.{$ref}.supabase.co";
$ipv4 = null;
$dnsRecords = dns_get_record($directHost, DNS_A);
if (!empty($dnsRecords)) {
    $ipv4 = $dnsRecords[0]['ip'];
    echo "Resolved {$directHost} IPv4: {$ipv4}\n\n";
} else {
    echo "Could not resolve IPv4 for {$directHost}\n\n";
}

$attempts = [
    'Direct IPv4 address + sslmode=require' => [
        'dsn'  => $ipv4 ? "pgsql:host={$ipv4};port=5432;dbname=postgres;sslmode=require" : null,
        'user' => 'postgres',
    ],
    'Direct IPv4 address + sslmode=disable' => [
        'dsn'  => $ipv4 ? "pgsql:host={$ipv4};port=5432;dbname=postgres;sslmode=disable" : null,
        'user' => 'postgres',
    ],
    'Pooler port 6543 sslmode=require' => [
        'dsn'  => "pgsql:host=aws-0-eu-west-1.pooler.supabase.com;port=6543;dbname=postgres;sslmode=require",
        'user' => "postgres.{$ref}",
    ],
    'Pooler port 5432 sslmode=require' => [
        'dsn'  => "pgsql:host=aws-0-eu-west-1.pooler.supabase.com;port=5432;dbname=postgres;sslmode=require",
        'user' => "postgres.{$ref}",
    ],
];

foreach ($attempts as $label => $cfg) {
    if (!$cfg['dsn']) { echo "--- {$label} ---\nSKIPPED (no IPv4)\n\n"; continue; }
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
        echo "Host: " . parse_url($cfg['dsn'], PHP_URL_HOST) . "\n";
        break;
    } catch (Exception $e) {
        echo "❌ FAILED: " . $e->getMessage() . "\n\n";
    }
}
echo "</pre>";
