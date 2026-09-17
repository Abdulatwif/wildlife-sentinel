<?php
// ============================================================
// tourism/dashboard.php
// Tourism / Lodge Operator — Dashboard
// ------------------------------------------------------------
// Layout:
//   - Animal photo slideshow (8 rotating wildlife images)
//   - Live stats (my reports, zone incidents, safety)
//   - My recent reports
//   - Zone safety status
//   - Quick actions
//   - Mini map (read-only safety view)
// ============================================================

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/functions.php';
requireLogin();

$user = getCurrentUser();
if (!$user || $user['role'] !== 'tourism') {
    header('Location: ../index.php');
    exit();
}

$pdo          = getDB();
$activeZoneId = (int)($user['zone_id'] ?? 0);
$zone         = getZone($activeZoneId);

// ============================================================
// SAFE HELPERS
// ============================================================
if (!function_exists('safeCount')) {
    function safeCount(PDO $pdo, string $sql, array $params = []): int {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return (int)($stmt->fetch()['count'] ?? 0);
        } catch (PDOException $e) { return 0; }
    }
}
if (!function_exists('safeFetchAll')) {
    function safeFetchAll(PDO $pdo, string $sql, array $params = []): array {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) { return []; }
    }
}

// ============================================================
// MY RECENT REPORTS
// ============================================================
$myReports = safeFetchAll($pdo, "
    SELECT i.*, r.full_name AS responder_name
    FROM incidents i
    LEFT JOIN users r ON i.acknowledged_by = r.id
    WHERE i.reporter_id = ?
    ORDER BY i.reported_at DESC
    LIMIT 6
", [$user['id']]);

// ============================================================
// STATS
// ============================================================
$stats = [
    'my_total'       => safeCount($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ?", [$user['id']]),
    'my_active'      => safeCount($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? AND status NOT IN ('resolved','closed')", [$user['id']]),
    'my_resolved'    => safeCount($pdo, "SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? AND status = 'resolved'", [$user['id']]),
    'zone_active'    => safeCount($pdo, "SELECT COUNT(*) as count FROM incidents WHERE zone_id = ? AND status NOT IN ('resolved','closed')", [$activeZoneId]),
    'zone_critical'  => safeCount($pdo, "SELECT COUNT(*) as count FROM incidents WHERE zone_id = ? AND severity = 'critical' AND status NOT IN ('resolved','closed')", [$activeZoneId]),
    'zone_rangers'   => safeCount($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'ranger' AND is_active = 1", [$activeZoneId]),
    'unread_notifs'  => function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount($user['id']) : 0,
    'unread_msgs'    => function_exists('getUnreadMessageCount')      ? getUnreadMessageCount($user['id'])      : 0,
];

// ============================================================
// ZONE SAFETY STATUS (read-only — no sensitive info)
// ============================================================
$safetyLevel = 'low';
$safetyColor = '#28a745';
$safetyLabel = 'Low Risk';
$safetyDesc  = 'Your zone is currently calm. Enjoy your visit responsibly.';

if ($stats['zone_critical'] > 0) {
    $safetyLevel = 'critical';
    $safetyColor = '#dc3545';
    $safetyLabel = 'High Alert';
    $safetyDesc  = 'There are critical incidents in your zone. Please stay within secured areas and follow ranger guidance.';
} elseif ($stats['zone_active'] >= 5) {
    $safetyLevel = 'high';
    $safetyColor = '#fd7e14';
    $safetyLabel = 'Elevated Risk';
    $safetyDesc  = 'Multiple active incidents in your zone. Exercise extra caution and report anything unusual.';
} elseif ($stats['zone_active'] > 0) {
    $safetyLevel = 'medium';
    $safetyColor = '#ffc107';
    $safetyLabel = 'Moderate Risk';
    $safetyDesc  = 'A few active incidents in your zone. Normal activity is fine — stay alert.';
}

// ============================================================
// ANIMAL SLIDESHOW — stable Unsplash URLs (no API key)
// ============================================================
$animalSlides = [
    [
        'url'  => 'https://images.unsplash.com/photo-1546182990-dffeafbe841d?w=1400&q=80',
        'icon' => '🦁',
        'name' => 'African Lion',
        'desc' => 'Apex predator of the Luangwa Valley',
        'tag'  => 'Kafue & South Luangwa',
    ],
    [
        'url'  => 'https://images.unsplash.com/photo-1549366021-9f761d450615?w=1400&q=80',
        'icon' => '🐘',
        'name' => 'African Elephant',
        'desc' => 'Largest land mammal on Earth',
        'tag'  => 'South Luangwa',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRJJBMyQRF1-CsZwjlkFNeRQvRftWMbyn3GEZ__MdzlFw&s=10',
        'icon' => '🦒',
        'name' => 'Thornicroft\'s Giraffe',
        'desc' => 'Endemic to the Luangwa Valley',
        'tag'  => 'South Luangwa',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSWgRhOEYyfdiNH8kRy9aGTanO2nmPb50EtUimbw9WqZA&s=10',
        'icon' => '🦏',
        'name' => 'Black Rhinoceros',
        'desc' => 'Critically endangered — heavily protected',
        'tag'  => 'North Luangwa',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTgEJ8mra8aL4Uoug0jjPTfxaj7plkRsyFgO-los0THOg&s=10',
        'icon' => '🐆',
        'name' => 'African Leopard',
        'desc' => 'Elusive nocturnal predator',
        'tag'  => 'Liuwa Plain',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQi5FCbjMdl6SuH2r5E-Hk9MdXoSgTG1MpqCfjwslq8Uw&s=10',
        'icon' => '🦓',
        'name' => 'Plains Zebra',
        'desc' => 'Famous Liuwa migration herds',
        'tag'  => 'Liuwa Plain',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSpTszrX2Xvx9AJOF-9u8PC7lGOH_YtrIm5b8SliUK-YQ&s=10',
        'icon' => '🐊',
        'name' => 'Nile Crocodile',
        'desc' => 'Ancient apex predator of the Zambezi',
        'tag'  => 'Lower Zambezi',
    ],
    [
        'url'  => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRO-1w87JpESgqJLpQnGK0hxhCwUKplz_19VGIT8eY6IQ&s=10',
        'icon' => '🦅',
        'name' => 'African Fish Eagle',
        'desc' => 'Zambia\'s national bird',
        'tag'  => 'Nationwide',
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - Tourism - Wildlife Sentinel</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transitions.css">

    <style>
        .dashboard-greeting { margin-bottom: 22px; }
        .dashboard-greeting h1 { font-size: 26px; color: #0d3b22; }
        .dashboard-greeting p  { color: #6c757d; font-size: 15px; }

        /* ============================================================
           ANIMAL SLIDESHOW
           ============================================================ */
        .animal-slideshow {
            position: relative;
            height: 280px;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15);
            background: #0d3b22;
        }

        .animal-slideshow .slide {
            position: absolute;
            inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            transition: opacity 1.4s ease-in-out;
            transform: scale(1.05);
        }
        .animal-slideshow .slide.active {
            opacity: 1;
            animation: kenburns 8s ease-in-out infinite alternate;
        }
        @keyframes kenburns {
            from { transform: scale(1); }
            to   { transform: scale(1.08); }
        }

        .animal-slideshow .slide-overlay {
            position: absolute;
            inset: 0;
            z-index: 1;
            background: linear-gradient(
                135deg,
                rgba(10,20,10,0.75) 0%,
                rgba(10,20,10,0.25) 45%,
                rgba(10,20,10,0.75) 100%
            );
        }

        .animal-slideshow .slide-content {
            position: relative;
            z-index: 2;
            height: 100%;
            padding: 28px 36px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .animal-slideshow .slide-content .animal-icon {
            font-size: 48px;
            display: block;
            margin-bottom: 10px;
            filter: drop-shadow(0 2px 10px rgba(0,0,0,0.4));
        }

        .animal-slideshow .slide-content .animal-name {
            font-family: 'Playfair Display', Georgia, serif;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 12px rgba(0,0,0,0.5);
            margin: 0;
        }

        .animal-slideshow .slide-content .animal-desc {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 6px;
            max-width: 480px;
            text-shadow: 0 1px 6px rgba(0,0,0,0.5);
        }

        .animal-slideshow .slide-content .animal-tag {
            display: inline-block;
            align-self: flex-start;
            margin-top: 14px;
            padding: 5px 14px;
            border-radius: 20px;
            background: rgba(74, 222, 128, 0.2);
            border: 1px solid rgba(74, 222, 128, 0.4);
            color: #d4ffe0;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            text-transform: uppercase;
            backdrop-filter: blur(6px);
        }

        .animal-slideshow .slide-indicators {
            position: absolute;
            bottom: 14px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 3;
            display: flex;
            gap: 8px;
        }
        .animal-slideshow .slide-indicators .dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            cursor: pointer;
            transition: all 0.3s;
        }
        .animal-slideshow .slide-indicators .dot.active {
            background: #4ade80;
            transform: scale(1.3);
        }

        .animal-slideshow .slide-counter {
            position: absolute;
            top: 16px;
            right: 20px;
            z-index: 3;
            background: rgba(0,0,0,0.4);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 1px;
            backdrop-filter: blur(6px);
        }

        /* ============================================================
           SAFETY BANNER
           ============================================================ */
        .safety-banner {
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 18px;
            flex-wrap: wrap;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border-left: 6px solid;
        }
        .safety-banner .safety-icon {
            font-size: 40px;
            flex-shrink: 0;
        }
        .safety-banner .safety-info { flex: 1; min-width: 200px; }
        .safety-banner .safety-info .level {
            font-size: 18px;
            font-weight: 800;
            letter-spacing: -0.3px;
        }
        .safety-banner .safety-info .desc {
            font-size: 13px;
            color: #495057;
            margin-top: 4px;
            line-height: 1.5;
        }

        /* ============================================================
           STATS GRID
           ============================================================ */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 14px;
            margin-bottom: 22px;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 16px 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
            border: 1px solid #f0f0f0;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        .stat-card .icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            flex-shrink: 0;
        }
        .stat-card .icon.blue   { background: #cce5ff; color: #004085; }
        .stat-card .icon.green  { background: #d4edda; color: #155724; }
        .stat-card .icon.red    { background: #f8d7da; color: #721c24; }
        .stat-card .icon.orange { background: #fff3cd; color: #856404; }
        .stat-card .icon.purple { background: #e8d5f5; color: #6f42c1; }
        .stat-card .info .number { font-size: 22px; font-weight: 700; color: #0d3b22; }
        .stat-card .info .label  { font-size: 11px; color: #6c757d; }

        /* ============================================================
           SECTIONS
           ============================================================ */
        .section {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            border: 1px solid #f0f0f0;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
            gap: 10px;
        }
        .section-header h2 {
            font-size: 17px;
            color: #0d3b22;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-header .view-all {
            color: #1a5c3a;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
        }
        .section-header .view-all:hover { text-decoration: underline; }

        /* ============================================================
           REPORT ITEM
           ============================================================ */
        .report-item {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 8px;
            background: #fafafa;
            border-left: 4px solid #ffc107;
            transition: all 0.2s;
            text-decoration: none;
            color: inherit;
            gap: 12px;
        }
        .report-item:hover {
            background: #f0f0f0;
            transform: translateX(2px);
        }
        .report-item.critical { border-left-color: #dc3545; background: #fdf5f5; }
        .report-item.high     { border-left-color: #fd7e14; }
        .report-item.medium   { border-left-color: #ffc107; }
        .report-item.low      { border-left-color: #28a745; }

        .report-item .report-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .report-item .report-info { flex: 1; min-width: 0; }
        .report-item .report-title {
            font-weight: 600; font-size: 14px; color: #0d3b22;
        }
        .report-item .report-meta {
            font-size: 11px; color: #6c757d; margin-top: 3px;
        }

        .status-pill {
            padding: 3px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            flex-shrink: 0;
        }
        .status-pill.reported     { background: #cce5ff; color: #004085; }
        .status-pill.acknowledged { background: #fff3cd; color: #856404; }
        .status-pill.in_progress  { background: #d1ecf1; color: #0c5460; }
        .status-pill.resolved     { background: #d4edda; color: #155724; }
        .status-pill.closed       { background: #e9ecef; color: #495057; }

        /* ============================================================
           QUICK ACTIONS
           ============================================================ */
        .quick-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .quick-tile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 18px 12px;
            background: #fafafa;
            border-radius: 10px;
            text-decoration: none;
            color: #495057;
            border: 2px solid transparent;
            transition: all 0.2s;
        }
        .quick-tile:hover {
            background: white;
            border-color: #1a5c3a;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        .quick-tile .icon { font-size: 26px; margin-bottom: 6px; }
        .quick-tile .label { font-size: 12px; font-weight: 600; text-align: center; }
        .quick-tile .badge {
            font-size: 10px; padding: 2px 8px;
            border-radius: 10px; background: #dc3545;
            color: white; margin-top: 4px;
        }

        /* ============================================================
           MINI MAP
           ============================================================ */
        #miniMap {
            height: 320px;
            border-radius: 12px;
            background: #e0e0e0;
        }

        /* ============================================================
           EMPTY STATE
           ============================================================ */
        .empty-state {
            text-align: center;
            padding: 30px 20px;
            color: #6c757d;
            font-size: 13px;
        }
        .empty-state .icon {
            font-size: 40px;
            display: block;
            margin-bottom: 8px;
            opacity: 0.4;
        }

        /* ============================================================
           RESPONSIVE
           ============================================================ */
        @media (max-width: 768px) {
            .animal-slideshow { height: 200px; border-radius: 12px; }
            .animal-slideshow .slide-content { padding: 20px 24px; }
            .animal-slideshow .slide-content .animal-icon { font-size: 36px; }
            .animal-slideshow .slide-content .animal-name { font-size: 22px; }
            .animal-slideshow .slide-content .animal-desc { font-size: 12px; }
            .animal-slideshow .slide-content .animal-tag { font-size: 9px; padding: 3px 10px; }
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .dashboard-greeting h1 { font-size: 22px; }
            #miniMap { height: 260px; }
        }
        @media (max-width: 480px) {
            .animal-slideshow { height: 170px; }
            .animal-slideshow .slide-content { padding: 14px 18px; }
            .animal-slideshow .slide-content .animal-icon { font-size: 28px; margin-bottom: 4px; }
            .animal-slideshow .slide-content .animal-name { font-size: 18px; }
            .animal-slideshow .slide-content .animal-desc { font-size: 11px; }
            .animal-slideshow .slide-counter { top: 10px; right: 12px; font-size: 10px; padding: 2px 8px; }
            .stat-card { padding: 10px 12px; }
            .stat-card .icon { width: 36px; height: 36px; font-size: 16px; }
            .stat-card .info .number { font-size: 18px; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h1>Dashboard</h1>
                <div class="header-right">
                    <span class="online-status">● Online</span>
                    <span class="data-honesty-badge">🟢 Live Data</span>
                    <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                </div>
            </header>

            <div class="content">
                <div class="dashboard-greeting">
                    <h1>👋 Welcome, <?= htmlspecialchars($user['full_name']) ?>!</h1>
                    <p>
                        Zone: <strong><?= htmlspecialchars($zone['name'] ?? 'Unassigned') ?></strong>
                        · <?= date('l, F j, Y') ?>
                    </p>
                </div>

                <!-- ============================================================
                     ANIMAL SLIDESHOW
                     ============================================================ -->
                <div class="animal-slideshow" id="animalSlideshow">
                    <?php foreach ($animalSlides as $i => $slide): ?>
                        <div class="slide <?= $i === 0 ? 'active' : '' ?>"
                             data-index="<?= $i ?>"
                             style="background-image: url('<?= htmlspecialchars($slide['url']) ?>');">
                        </div>
                    <?php endforeach; ?>

                    <div class="slide-overlay"></div>

                    <div class="slide-content" id="slideContent">
                        <span class="animal-icon" id="slideIcon"><?= htmlspecialchars($animalSlides[0]['icon']) ?></span>
                        <h2 class="animal-name" id="slideName"><?= htmlspecialchars($animalSlides[0]['name']) ?></h2>
                        <div class="animal-desc" id="slideDesc"><?= htmlspecialchars($animalSlides[0]['desc']) ?></div>
                        <span class="animal-tag" id="slideTag"><?= htmlspecialchars($animalSlides[0]['tag']) ?></span>
                    </div>

                    <div class="slide-counter" id="slideCounter">1 / <?= count($animalSlides) ?></div>

                    <div class="slide-indicators" id="slideIndicators">
                        <?php foreach ($animalSlides as $i => $slide): ?>
                            <span class="dot <?= $i === 0 ? 'active' : '' ?>" data-index="<?= $i ?>"></span>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- ============================================================
                     SAFETY BANNER
                     ============================================================ -->
                <div class="safety-banner" style="border-left-color: <?= $safetyColor ?>; background: <?= $safetyColor ?>10;">
                    <div class="safety-icon">
                        <?php
                        $safetyIcons = [
                            'low'      => '🟢',
                            'medium'   => '🟡',
                            'high'     => '🟠',
                            'critical' => '🔴',
                        ];
                        echo $safetyIcons[$safetyLevel] ?? '🟢';
                        ?>
                    </div>
                    <div class="safety-info">
                        <div class="level" style="color: <?= $safetyColor ?>;">
                            Zone Safety: <?= htmlspecialchars($safetyLabel) ?>
                        </div>
                        <div class="desc"><?= htmlspecialchars($safetyDesc) ?></div>
                    </div>
                    <a href="safety-map.php" class="btn btn-primary" style="padding:10px 18px;font-size:13px;">
                        🗺️ View Safety Map
                    </a>
                </div>

                <!-- ============================================================
                     STATS
                     ============================================================ -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="icon blue">📋</div>
                        <div class="info">
                            <div class="number"><?= $stats['my_total'] ?></div>
                            <div class="label">My Reports</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon orange">⏳</div>
                        <div class="info">
                            <div class="number"><?= $stats['my_active'] ?></div>
                            <div class="label">Active Reports</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon green">✅</div>
                        <div class="info">
                            <div class="number"><?= $stats['my_resolved'] ?></div>
                            <div class="label">Resolved</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon red">🚨</div>
                        <div class="info">
                            <div class="number"><?= $stats['zone_active'] ?></div>
                            <div class="label">Zone Incidents</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon purple">🛡️</div>
                        <div class="info">
                            <div class="number"><?= $stats['zone_rangers'] ?></div>
                            <div class="label">Rangers in Zone</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon blue">🔔</div>
                        <div class="info">
                            <div class="number"><?= $stats['unread_notifs'] ?></div>
                            <div class="label">Notifications</div>
                        </div>
                    </div>
                </div>

                <!-- ============================================================
                     MY RECENT REPORTS
                     ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <h2>📋 My Recent Reports (<?= count($myReports) ?>)</h2>
                        <a href="my-reports.php" class="view-all">View All →</a>
                    </div>

                    <?php if (count($myReports) > 0): ?>
                        <?php foreach ($myReports as $r): ?>
                            <a href="my-reports.php" class="report-item <?= htmlspecialchars($r['severity']) ?>">
                                <div class="report-icon">
                                    <?= function_exists('getCategoryIcon') ? getCategoryIcon($r['category']) : '🚨' ?>
                                </div>
                                <div class="report-info">
                                    <div class="report-title">
                                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $r['category']))) ?>
                                        <span style="font-weight:400;color:#6c757d;font-size:12px;">#<?= (int)$r['id'] ?></span>
                                    </div>
                                    <div class="report-meta">
                                        🕐 <?= timeAgo($r['reported_at']) ?>
                                        <?php if ($r['responder_name']): ?>
                                            • 🛡️ <?= htmlspecialchars($r['responder_name']) ?> responding
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <span class="status-pill <?= htmlspecialchars($r['status']) ?>">
                                    <?= strtoupper(str_replace('_', ' ', htmlspecialchars($r['status']))) ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <span class="icon">📋</span>
                            <h3 style="font-size:14px;color:#495057;">No reports yet</h3>
                            <p>Report an incident and it will appear here.</p>
                            <a href="report.php" class="btn btn-primary" style="margin-top:10px;">
                                ➕ Report an Incident
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ============================================================
                     QUICK ACTIONS
                     ============================================================ -->
                <div class="section">
                    <div class="section-header"><h2>⚡ Quick Actions</h2></div>
                    <div class="quick-grid">
                        <a href="report.php" class="quick-tile">
                            <span class="icon">✏️</span>
                            <span class="label">Report Incident</span>
                        </a>
                        <a href="my-reports.php" class="quick-tile">
                            <span class="icon">📋</span>
                            <span class="label">My Reports</span>
                            <?php if ($stats['my_active'] > 0): ?>
                                <span class="badge"><?= $stats['my_active'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="safety-map.php" class="quick-tile">
                            <span class="icon">🗺️</span>
                            <span class="label">Safety Map</span>
                        </a>
                        <a href="notifications.php" class="quick-tile">
                            <span class="icon">🔔</span>
                            <span class="label">Notifications</span>
                            <?php if ($stats['unread_notifs'] > 0): ?>
                                <span class="badge"><?= $stats['unread_notifs'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="messages.php" class="quick-tile">
                            <span class="icon">💬</span>
                            <span class="label">Messages</span>
                            <?php if ($stats['unread_msgs'] > 0): ?>
                                <span class="badge"><?= $stats['unread_msgs'] ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="settings.php" class="quick-tile">
                            <span class="icon">⚙️</span>
                            <span class="label">Settings</span>
                        </a>
                    </div>
                </div>

                <!-- ============================================================
                     MINI SAFETY MAP
                     ============================================================ -->
                <div class="section">
                    <div class="section-header">
                        <h2>🗺️ Zone Safety Map</h2>
                        <a href="safety-map.php" class="view-all">Full Map →</a>
                    </div>
                    <div id="miniMap"></div>
                    <div style="margin-top:10px;font-size:12px;color:#6c757d;">
                        🛡️ This view shows general zone safety only. For your privacy, ranger locations and sensitive incident details are hidden.
                    </div>
                </div>

                <!-- Debug (remove later) -->
                <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:10px 14px;font-family:monospace;font-size:11px;color:#856404;margin-top:14px;">
                    🐛 Reports: <?= $stats['my_total'] ?> (<?= $stats['my_active'] ?> active) |
                    Zone incidents: <?= $stats['zone_active'] ?> (<?= $stats['zone_critical'] ?> critical) |
                    Safety: <?= $safetyLevel ?> |
                    Zone: #<?= $activeZoneId ?>
                </div>
            </div>
        </main>
    </div>

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/transitions.js"></script>

    <script>
        // ============================================================
        // ANIMAL SLIDESHOW
        // ============================================================
        (function () {
            const slides = <?= json_encode($animalSlides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            const totalSlides = slides.length;
            const slideEls = document.querySelectorAll('.animal-slideshow .slide');
            const dotEls   = document.querySelectorAll('.animal-slideshow .dot');
            const el = {
                icon:    document.getElementById('slideIcon'),
                name:    document.getElementById('slideName'),
                desc:    document.getElementById('slideDesc'),
                tag:     document.getElementById('slideTag'),
                counter: document.getElementById('slideCounter'),
            };
            let current = 0;
            let timer = null;

            function goTo(index) {
                index = ((index % totalSlides) + totalSlides) % totalSlides;

                slideEls.forEach((s, i) => s.classList.toggle('active', i === index));
                dotEls.forEach((d, i) => d.classList.toggle('active', i === index));

                const slide = slides[index];
                el.icon.textContent = slide.icon;
                el.name.textContent = slide.name;
                el.desc.textContent = slide.desc;
                el.tag.textContent  = slide.tag;
                el.counter.textContent = (index + 1) + ' / ' + totalSlides;

                current = index;
            }

            function next() { goTo(current + 1); }
            function start() {
                stop();
                timer = setInterval(next, 5500);
            }
            function stop() {
                if (timer) clearInterval(timer);
                timer = null;
            }

            dotEls.forEach(d => {
                d.addEventListener('click', () => {
                    goTo(parseInt(d.dataset.index));
                    start();
                });
            });

            const wrapper = document.getElementById('animalSlideshow');
            wrapper.addEventListener('mouseenter', stop);
            wrapper.addEventListener('mouseleave', start);

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) stop(); else start();
            });

            goTo(0);
            start();
        })();

        // ============================================================
        // MINI SAFETY MAP (zone boundary + general risk indicator only)
        // ============================================================
        (function () {
            const zone = <?= json_encode($zone, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
            const safetyLevel = <?= json_encode($safetyLevel) ?>;
            const safetyColor = <?= json_encode($safetyColor) ?>;

            let center = [-14.5, 27.0];
            let zoom = 6;

            if (zone && (zone.center_lat || zone.boundary_center_lat)) {
                center = [
                    parseFloat(zone.center_lat || zone.boundary_center_lat),
                    parseFloat(zone.center_lng || zone.boundary_center_lng)
                ];
                zoom = 11;
            }

            const map = L.map('miniMap', {
                center: center,
                zoom: zoom,
                zoomControl: true,
                scrollWheelZoom: false,
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap',
            }).addTo(map);

            // Zone boundary — colored by safety level
            if (zone && zone.boundary_geojson && zone.boundary_geojson.type === 'Polygon') {
                try {
                    L.geoJSON(zone.boundary_geojson, {
                        style: {
                            color: safetyColor,
                            weight: 3,
                            fillColor: safetyColor,
                            fillOpacity: 0.15,
                        }
                    }).addTo(map).bindPopup(`
                        <strong>${zone.name}</strong><br>
                        <span style="color:${safetyColor};font-weight:700;">
                            Safety: ${safetyLevel.toUpperCase()}
                        </span>
                    `);
                } catch (e) {}
            }

            // Center marker
            if (zone && (zone.center_lat || zone.boundary_center_lat)) {
                const lat = parseFloat(zone.center_lat || zone.boundary_center_lat);
                const lng = parseFloat(zone.center_lng || zone.boundary_center_lng);
                L.marker([lat, lng], {
                    icon: L.divIcon({
                        className: 'tourism-zone-marker',
                        html: `<div style="background:${safetyColor};width:30px;height:30px;border-radius:50%;
                                    border:3px solid white;box-shadow:0 2px 10px rgba(0,0,0,0.3);
                                    display:flex;align-items:center;justify-content:center;
                                    color:white;font-size:14px;font-weight:bold;">🛡️</div>`,
                        iconSize: [30, 30],
                        iconAnchor: [15, 15],
                    })
                }).addTo(map).bindPopup(`<strong>${zone.name}</strong>`);
            }

            document.addEventListener('sidebarToggled', () => {
                setTimeout(() => map.invalidateSize(), 400);
            });
            window.addEventListener('resize', () => map.invalidateSize());

            console.log('✅ Tourism safety map loaded');
        })();

        console.log('✅ Tourism dashboard loaded');
    </script>
</body>
</html>