<?php
require_once '../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$pdo = getDB();

// ============================================================
// NOTIFICATION SETTINGS (mirrors admin/settings.php)
// ============================================================
// Map notification types -> setting keys that gate them.
// If a setting is '0', that type is hidden from this page entirely
// (dropdown + list). 'system_alert', 'acknowledged', 'status_update',
// and 'new_message' are always shown (not gated by settings).
$typeSettingMap = [
    'new_incident'     => 'notify_on_incident',
    'ai_alert'         => 'notify_on_ai_alert',
    'alarm'            => 'notify_on_alarm',
    'manpower_request' => 'notify_on_manpower',
];

// Read the setting (uses the app's cached helper if available)
function ws_notif_setting(string $key, string $default = '1'): string {
    if (function_exists('getSetting')) {
        $v = getSetting($key);
        return $v !== null ? (string)$v : $default;
    }
    // Fallback: query directly
    try {
        $stmt = getDB()->prepare("SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

// Determine which types are enabled
$enabledTypes = [];
foreach ($typeSettingMap as $type => $settingKey) {
    if (ws_notif_setting($settingKey, '1') === '1') {
        $enabledTypes[] = $type;
    }
}

// ============================================================
// ACTIONS (mark read / mark all / delete)
// ============================================================
if (isset($_GET['mark_all_read'])) {
    markAllNotificationsRead($user['id']);
    header('Location: notifications.php');
    exit();
}

if (isset($_GET['mark_read']) && isset($_GET['id'])) {
    markNotificationRead($_GET['id'], $user['id']);
    header('Location: notifications.php');
    exit();
}

if (isset($_GET['delete']) && isset($_GET['id'])) {
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['id'], $user['id']]);
    header('Location: notifications.php');
    exit();
}

// ============================================================
// PAGINATION (uses items_per_page setting)
// ============================================================
$page  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = (int) ws_notif_setting('items_per_page', '25');
if ($limit < 1 || $limit > 100) $limit = 25;
$offset = ($page - 1) * $limit;

// ============================================================
// BUILD WHERE CLAUSE
// ============================================================
$where  = ['n.user_id = ?'];
$params = [$user['id']];

// Exclude gated types that are disabled
$gatedTypes = array_keys($typeSettingMap); // all types that *could* be gated
$disabledTypes = array_diff($gatedTypes, $enabledTypes);
if (!empty($disabledTypes)) {
    $placeholders = implode(',', array_fill(0, count($disabledTypes), '?'));
    $where[] = "n.type NOT IN ($placeholders)";
    $params = array_merge($params, $disabledTypes);
}

// Optional filter by type (only allow enabled types)
$filterType = isset($_GET['type']) ? $_GET['type'] : '';
$allowedFilterTypes = array_merge(
    ['acknowledged', 'status_update', 'system_alert', 'new_message'],
    $enabledTypes
);
if ($filterType !== '' && in_array($filterType, $allowedFilterTypes, true)) {
    $where[]  = "n.type = ?";
    $params[] = $filterType;
} else {
    $filterType = ''; // ignore invalid/disabled filter
}

$whereSql = implode(' AND ', $where);

// ============================================================
// TOTAL COUNT
// ============================================================
$stmt = $pdo->prepare("SELECT COUNT(*) AS total FROM notifications n WHERE $whereSql");
$stmt->execute($params);
$total = (int) $stmt->fetch()['total'];
$totalPages = (int) ceil($total / $limit);

// ============================================================
// FETCH NOTIFICATIONS
// ============================================================
$sql = "
    SELECT n.*, i.category AS incident_category, i.status AS incident_status
    FROM notifications n
    LEFT JOIN incidents i ON n.incident_id = i.id
    WHERE $whereSql
    ORDER BY n.created_at DESC
    LIMIT ? OFFSET ?
";
$stmt = $pdo->prepare($sql);
$stmt->execute(array_merge($params, [$limit, $offset]));
$notifications = $stmt->fetchAll();

// ============================================================
// TYPE LABELS (only enabled ones shown in dropdown)
// ============================================================
$allTypeLabels = [
    'new_incident'     => '🚨 New Incident',
    'acknowledged'     => '✅ Acknowledged',
    'status_update'    => '📋 Status Update',
    'system_alert'     => '⚡ System Alert',
    'new_message'      => '💬 New Message',
    'manpower_request' => '🆘 Manpower Request',
    'ai_alert'         => '🤖 AI Alert',
    'alarm'            => '🔔 Alarm',
];
$types = [];
foreach ($allTypeLabels as $key => $label) {
    if (in_array($key, $allowedFilterTypes, true)) {
        $types[$key] = $label;
    }
}

$unread = getUnreadNotificationCount($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notifications-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px; }
        .notifications-header h1 { font-size: 24px; color: #0d3b22; display: flex; align-items: center; gap: 10px; }
        .notifications-header h1 .count { font-size: 14px; background: #dc3545; color: white; padding: 2px 12px; border-radius: 20px; font-weight: 400; }
        .filter-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; }
        .filter-bar select { padding: 8px 15px; border: 1px solid #dee2e6; border-radius: 8px; font-size: 14px; background: white; }
        .notification-item { background: white; border-radius: 10px; padding: 18px 20px; margin-bottom: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: start; gap: 15px; transition: all 0.2s; border-left: 4px solid transparent; }
        .notification-item:hover { box-shadow: 0 4px 15px rgba(0,0,0,0.1); }
        .notification-item.unread { border-left-color: #007bff; background: #f8f9ff; }
        .notification-item .icon { font-size: 28px; width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; background: #f0f7f4; border-radius: 10px; flex-shrink: 0; }
        .notification-item .content { flex: 1; }
        .notification-item .content .title { font-weight: 600; font-size: 15px; margin-bottom: 4px; }
        .notification-item .content .body { color: #6c757d; font-size: 14px; line-height: 1.5; }
        .notification-item .content .meta { display: flex; gap: 15px; flex-wrap: wrap; margin-top: 8px; font-size: 12px; color: #adb5bd; }
        .notification-item .actions { display: flex; gap: 8px; flex-shrink: 0; }
        .notification-item .actions .btn-small { padding: 4px 12px; font-size: 12px; }
        .pagination { display: flex; justify-content: center; gap: 8px; margin-top: 20px; flex-wrap: wrap; }
        .pagination .page-link { padding: 8px 16px; border: 1px solid #dee2e6; border-radius: 6px; text-decoration: none; color: #495057; transition: all 0.2s; }
        .pagination .page-link:hover { background: #1a5c3a; color: white; }
        .pagination .page-link.active { background: #1a5c3a; color: white; border-color: #1a5c3a; }
        .empty-state { text-align: center; padding: 60px 20px; color: #6c757d; }
        .empty-state .icon { font-size: 64px; margin-bottom: 15px; }
        .notification-type-badge { font-size: 10px; padding: 2px 10px; border-radius: 12px; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px; }
        .type-new_incident { background: #d4edda; color: #155724; }
        .type-acknowledged { background: #cce5ff; color: #004085; }
        .type-status_update { background: #fff3cd; color: #856404; }
        .type-system_alert { background: #f8d7da; color: #721c24; }
        .type-new_message { background: #d1ecf1; color: #0c5460; }
        .type-manpower_request { background: #f8d7da; color: #721c24; }
        .type-ai_alert { background: #e2d9f3; color: #432874; }
        .type-alarm { background: #ffe5d0; color: #8a4b08; }
        @media (max-width: 768px) { .notification-item { flex-wrap: wrap; } .notification-item .actions { width: 100%; justify-content: flex-end; } .notifications-header { flex-direction: column; align-items: stretch; } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h1>Notifications</h1>
                <div class="header-right">
                    <span class="online-status">● Online</span>
                    <span class="data-honesty-badge">🟢 Live Data</span>
                    <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
                </div>
            </header>

            <div class="content">
                <div class="notifications-header">
                    <h1>🔔 Notifications <?php if ($unread > 0): ?><span class="count"><?= $unread ?> new</span><?php endif; ?></h1>
                    <div style="display:flex;gap:10px;flex-wrap:wrap;">
                        <?php if ($unread > 0): ?><a href="?mark_all_read=1" class="btn btn-primary">✅ Mark All Read</a><?php endif; ?>
                        <a href="notifications.php" class="btn btn-secondary">🔄 Refresh</a>
                    </div>
                </div>

                <div class="filter-bar">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
                        <select name="type" onchange="this.form.submit()">
                            <option value="">All Types</option>
                            <?php foreach ($types as $key => $label): ?>
                                <option value="<?= htmlspecialchars($key) ?>" <?= $filterType === $key ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-small">Filter</button>
                        <?php if ($filterType): ?><a href="notifications.php" class="btn btn-secondary btn-small">Clear</a><?php endif; ?>
                    </form>
                </div>

                <?php if (count($notifications) > 0): ?>
                    <?php foreach ($notifications as $notif): ?>
                    <div class="notification-item <?= $notif['is_read'] ? 'read' : 'unread' ?>">
                        <div class="icon"><?php
                            $icons = [
                                'new_incident'     => '🚨',
                                'acknowledged'     => '✅',
                                'status_update'    => '📋',
                                'system_alert'     => '⚡',
                                'new_message'      => '💬',
                                'manpower_request' => '🆘',
                                'ai_alert'         => '🤖',
                                'alarm'            => '🔔',
                            ];
                            echo $icons[$notif['type']] ?? '📢';
                        ?></div>
                        <div class="content">
                            <div class="title">
                                <?= htmlspecialchars($notif['title']) ?>
                                <span class="notification-type-badge type-<?= htmlspecialchars($notif['type']) ?>">
                                    <?= htmlspecialchars(str_replace('_', ' ', $notif['type'])) ?>
                                </span>
                            </div>
                            <div class="body"><?= htmlspecialchars($notif['body']) ?></div>
                            <div class="meta">
                                <span>🕐 <?= timeAgo($notif['created_at']) ?></span>
                                <span>📅 <?= date('M j, Y H:i', strtotime($notif['created_at'])) ?></span>
                                <?php if ($notif['incident_id']): ?><span>📌 Incident #<?= (int)$notif['incident_id'] ?></span><?php endif; ?>
                                <?php if ($notif['is_read']): ?><span style="color:#28a745;">✅ Read</span><?php else: ?><span style="color:#007bff;">● Unread</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="actions">
                            <?php if (!$notif['is_read']): ?><a href="?mark_read=1&id=<?= (int)$notif['id'] ?>" class="btn-small btn-primary">Mark Read</a><?php endif; ?>
                            <a href="?delete=1&id=<?= (int)$notif['id'] ?>" class="btn-small btn-danger" onclick="return confirm('Delete this notification?')">🗑️</a>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&type=<?= urlencode($filterType) ?>" class="page-link">← Previous</a><?php endif; ?>
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?><a href="?page=<?= $i ?>&type=<?= urlencode($filterType) ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                        <?php if ($page < $totalPages): ?><a href="?page=<?= $page+1 ?>&type=<?= urlencode($filterType) ?>" class="page-link">Next →</a><?php endif; ?>
                    </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="empty-state"><div class="icon">📭</div><h3>No Notifications</h3><p>You're all caught up! Check back later for new notifications.</p></div>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>