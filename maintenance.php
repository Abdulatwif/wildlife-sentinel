<?php
// ============================================================
// maintenance.php
// Wildlife Sentinel — Maintenance landing page
// ============================================================
require_once __DIR__ . '/includes/functions.php';

// Read the message + flag directly (avoid full settings load for guests)
$message = 'System under maintenance. Please check back soon.';
try {
    $pdo = getDB();
    $rows = $pdo->query("SELECT setting_key, setting_value FROM settings
                         WHERE setting_key IN ('maintenance_mode','maintenance_message')")->fetchAll();
    $map = [];
    foreach ($rows as $r) { $map[$r['setting_key']] = $r['setting_value']; }

    // If maintenance mode was turned off, bounce to login
    if (($map['maintenance_mode'] ?? '0') !== '1') {
        header('Location: login.php');
        exit;
    }
    if (!empty($map['maintenance_message'])) {
        $message = $map['maintenance_message'];
    }
} catch (Throwable $e) { /* show the default */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#0d3b22">
    <title>Under Maintenance - Wildlife Sentinel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', -apple-system, sans-serif;
            min-height: 100vh; min-height: 100dvh;
            background: linear-gradient(135deg, #0a1a0f 0%, #1a5c3a 50%, #0d3b22 100%);
            display: flex; align-items: center; justify-content: center;
            padding: 24px; color: white;
        }
        .card {
            background: rgba(255,255,255,0.06);
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            max-width: 520px; width: 100%;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        .icon { font-size: 64px; margin-bottom: 16px; display: block; }
        h1 { font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 10px; }
        p {
            color: rgba(255,255,255,0.72); font-size: 15px; line-height: 1.7;
            margin-bottom: 24px; white-space: pre-wrap;
        }
        .meta {
            font-size: 12px; color: rgba(255,255,255,0.45);
            padding-top: 20px; border-top: 1px solid rgba(255,255,255,0.08);
        }
        .pill {
            display: inline-block; padding: 4px 14px; border-radius: 20px;
            background: rgba(250,204,21,0.15); color: #facc15;
            font-size: 11px; font-weight: 700;
            text-transform: uppercase; letter-spacing: 1px;
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="card">
        <span class="pill">🔧 Maintenance Mode</span>
        <span class="icon">🚧</span>
        <h1>We'll be right back</h1>
        <p><?= htmlspecialchars($message) ?></p>
        <div class="meta">
            © <?= date('Y') ?> Wildlife Sentinel — Zambia Wildlife Protection System
        </div>
    </div>
</body>
</html>