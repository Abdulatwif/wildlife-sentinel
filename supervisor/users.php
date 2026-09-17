<?php
// ============================================================
// supervisor/users.php
// Zone Supervisor — Manage users in own zone (rangers/scouts/tourism)
// Cannot manage admins or other supervisors.
// ============================================================

require_once '../includes/functions.php';
requireLogin();

if (!hasRole('zone_supervisor')) {
    header('Location: ../index.php');
    exit();
}

$user = getCurrentUser();
$pdo  = getDB();
$activeZoneId = $user['zone_id'];

// ============================================================
// SAFE HELPERS
// ============================================================
function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return (int)($stmt->fetch()['count'] ?? 0);
    } catch (PDOException $e) { return 0; }
}
function safeFetchAll(PDO $pdo, string $sql, array $params = []): array {
    try { $stmt = $pdo->prepare($sql); $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (PDOException $e) { return []; }
}

// ============================================================
// HELPERS — Email and Phone validation
// ============================================================

/** Strict real-world email validator. Returns ['ok'=>bool,'msg'=>string]. */
function validateRealEmail(string $email): array {
    $email = trim($email);

    if ($email === '')                return ['ok'=>false, 'msg'=>'Email is required'];
    if (strlen($email) > 254)         return ['ok'=>false, 'msg'=>'Email is too long (max 254 chars)'];
    if (strpos($email, ' ') !== false) return ['ok'=>false, 'msg'=>'Email cannot contain spaces'];
    if (strpos($email, '..') !== false) return ['ok'=>false, 'msg'=>'Email cannot contain consecutive dots'];
    if ($email[0] === '.' || substr($email, -1) === '.') {
        return ['ok'=>false, 'msg'=>'Email cannot start or end with a dot'];
    }
    if (substr_count($email, '@') !== 1) {
        return ['ok'=>false, 'msg'=>'Please enter a valid email address'];
    }

    list($local, $domain) = explode('@', $email);

    if ($local === '' || strlen($local) > 64) {
        return ['ok'=>false, 'msg'=>'Please enter a valid email address'];
    }
    if (!preg_match('/^[A-Za-z0-9._%+\-]+$/', $local)) {
        return ['ok'=>false, 'msg'=>'Email contains invalid characters'];
    }
    if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
        return ['ok'=>false, 'msg'=>'Please enter a valid email address'];
    }
    if (!preg_match('/^(?=.{4,253}$)([A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/', $domain)) {
        return ['ok'=>false, 'msg'=>'Please enter a valid email domain (e.g. example.com)'];
    }

    $blocked = [
        'example.com','example.org','example.net','test.com','test.org','test.net',
        'localhost','invalid','mailinator.com','tempmail.com','guerrillamail.com',
        '10minutemail.com','yopmail.com','trashmail.com','sharklasers.com'
    ];
    if (in_array(strtolower($domain), $blocked, true)) {
        return ['ok'=>false, 'msg'=>'Please use a real email (disposable/placeholder domains are not allowed)'];
    }
    return ['ok'=>true, 'msg'=>''];
}

/** Normalize a Zambian phone number to 09xxxxxxxx / 07xxxxxxxx form. */
function normalizeZambianPhone(string $raw): ?string {
    $raw = trim($raw);
    if ($raw === '') return null;
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if (strpos($digits, '260') === 0) $digits = substr($digits, 3);
    if (!preg_match('/^(09|07)[0-9]{8}$/', $digits)) return null;
    return $digits;
}

/** Check whether a phone already exists in the system. */
function phoneExists(PDO $pdo, string $phone, int $excludeId = 0): bool {
    if ($phone === '') return false;
    $sql = "SELECT id FROM users WHERE phone = ?";
    $params = [$phone];
    if ($excludeId > 0) { $sql .= " AND id != ?"; $params[] = $excludeId; }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

// ============================================================
// AJAX ENDPOINT: check phone availability
// ============================================================
if (isset($_GET['ajax']) && $_GET['ajax'] === 'check_phone') {
    header('Content-Type: application/json');
    $phone = normalizeZambianPhone($_GET['phone'] ?? '');
    if ($phone === null) {
        echo json_encode(['success'=>false,'error'=>'invalid_format']);
        exit;
    }
    $stmt = $pdo->prepare("SELECT id, full_name FROM users WHERE phone = ? LIMIT 1");
    $stmt->execute([$phone]);
    $row = $stmt->fetch();
    echo json_encode([
        'success'   => true,
        'exists'    => (bool)$row,
        'full_name' => $row['full_name'] ?? null,
    ]);
    exit;
}

// ============================================================
// HANDLE ACTIONS
// ============================================================
$message = ''; $messageType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // ---- CREATE ----
    if ($action === 'create') {
        $email    = trim($_POST['email'] ?? '');
        $phoneRaw = trim($_POST['phone'] ?? '');
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = $_POST['role'] ?? 'scout';
        $password = $_POST['password'] ?? '';
        $badge    = trim($_POST['badge_number'] ?? '');

        $errors = [];

        // Name
        if ($fullName === '') {
            $errors[] = 'Full name is required.';
        } elseif (mb_strlen($fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Full name must not exceed 100 characters.';
        } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $fullName)) {
            $errors[] = 'Full name contains invalid characters.';
        }

        // Role
        if (!in_array($role, ['ranger', 'scout', 'tourism'], true)) {
            $errors[] = 'Invalid role for supervisor.';
        }

        // Email (strict)
        $emailCheck = validateRealEmail($email);
        if (!$emailCheck['ok']) $errors[] = $emailCheck['msg'];

        // Password
        if ($password === '') {
            $errors[] = 'Password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        // Phone (optional, but must be valid + unique if provided)
        $phone = null;
        if ($phoneRaw !== '') {
            $phone = normalizeZambianPhone($phoneRaw);
            if ($phone === null) {
                $errors[] = 'Phone must be a valid Zambian number (e.g. 0971234567).';
            }
        }

        if (empty($errors) && $phone) {
            if (phoneExists($pdo, $phone)) {
                $errors[] = 'This phone number is already registered in the system. Each phone can only be used once (it receives SMS alerts).';
            }
        }

        // Email uniqueness (in addition to format)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors[] = 'This email is already registered.';
            }
        }

        if (!empty($errors)) {
            $message = implode('<br>', $errors);
            $messageType = 'danger';
        } else {
            $result = createUser([
                'email'        => $email,
                'phone'        => $phone,
                'password'     => $password,
                'full_name'    => $fullName,
                'role'         => $role,
                'zone_id'      => $activeZoneId,
                'badge_number' => $badge ?: null,
            ], $user['id']);

            if ($result['success']) {
                logAudit($user['id'], 'create_user', ['target' => $result['user_id'], 'role' => $role]);
                $message = ucfirst($role) . " '{$fullName}' created successfully.";
            } else {
                $message = $result['error'];
                $messageType = 'danger';
            }
        }
    }

    // ---- TOGGLE ACTIVE ----
    if ($action === 'toggle') {
        $targetId = (int)$_POST['user_id'];
        $target = safeFetchAll($pdo, "
            SELECT id, is_active FROM users
            WHERE id = ? AND zone_id = ? AND role IN ('ranger','scout','tourism')
        ", [$targetId, $activeZoneId]);
        if ($target) {
            $newState = $target[0]['is_active'] ? 0 : 1;
            $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ?")->execute([$newState, $targetId]);
            logAudit($user['id'], 'update_user', ['target' => $targetId, 'is_active' => $newState]);
            $message = $newState ? 'User activated.' : 'User deactivated.';
        } else {
            $message = 'User not found or not manageable.';
            $messageType = 'danger';
        }
    }

    // ---- CHANGE ROLE ----
    if ($action === 'change_role') {
        $targetId = (int)$_POST['user_id'];
        $newRole  = $_POST['new_role'] ?? '';
        if (!in_array($newRole, ['ranger', 'scout', 'tourism'])) {
            $message = 'Invalid role.';
            $messageType = 'danger';
        } else {
            $target = safeFetchAll($pdo, "
                SELECT id FROM users
                WHERE id = ? AND zone_id = ? AND role IN ('ranger','scout','tourism')
            ", [$targetId, $activeZoneId]);
            if ($target) {
                $pdo->prepare("UPDATE users SET role = ? WHERE id = ?")->execute([$newRole, $targetId]);
                logAudit($user['id'], 'update_user', ['target' => $targetId, 'role' => $newRole]);
                $message = 'User role updated to ' . $newRole . '.';
            } else {
                $message = 'User not found.';
                $messageType = 'danger';
            }
        }
    }

    // ---- RESET PASSWORD ----
    if ($action === 'reset_password') {
        $targetId = (int)$_POST['user_id'];
        $newPass  = $_POST['new_password'] ?? '';
        if (strlen($newPass) < 8) {
            $message = 'Password must be at least 8 characters.';
            $messageType = 'danger';
        } else {
            $target = safeFetchAll($pdo, "
                SELECT id FROM users
                WHERE id = ? AND zone_id = ? AND role IN ('ranger','scout','tourism')
            ", [$targetId, $activeZoneId]);
            if ($target) {
                $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                    ->execute([hashPassword($newPass), $targetId]);
                logAudit($user['id'], 'change_password', ['target' => $targetId]);
                $message = 'Password reset successfully.';
            } else {
                $message = 'User not found.';
                $messageType = 'danger';
            }
        }
    }

    // ---- DELETE (soft) ----
    if ($action === 'delete') {
        $targetId = (int)$_POST['user_id'];
        $target = safeFetchAll($pdo, "
            SELECT id FROM users
            WHERE id = ? AND zone_id = ? AND role IN ('ranger','scout','tourism')
        ", [$targetId, $activeZoneId]);
        if ($target) {
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$targetId]);
            logAudit($user['id'], 'delete_user', ['target' => $targetId]);
            $message = 'User removed (soft-deleted).';
        } else {
            $message = 'User not found.';
            $messageType = 'danger';
        }
    }
}

// ============================================================
// FETCH USERS
// ============================================================
$filterRole = $_GET['role'] ?? '';
$where  = " WHERE u.zone_id = ? AND u.role IN ('ranger','scout','tourism') ";
$params = [$activeZoneId];
if (in_array($filterRole, ['ranger', 'scout', 'tourism'])) {
    $where .= " AND u.role = ? ";
    $params[] = $filterRole;
}

$users = safeFetchAll($pdo, "
    SELECT u.*
    FROM users u
    $where
    ORDER BY u.is_active DESC, u.role, u.full_name
", $params);

// ============================================================
// STATS
// ============================================================
$totalRangers = safeCount($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'ranger' AND is_active = 1", [$activeZoneId]);
$totalScouts  = safeCount($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'scout' AND is_active = 1", [$activeZoneId]);
$totalTourism = safeCount($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role = 'tourism' AND is_active = 1", [$activeZoneId]);
$inactive     = safeCount($pdo, "SELECT COUNT(*) as count FROM users WHERE zone_id = ? AND role IN ('ranger','scout','tourism') AND is_active = 0", [$activeZoneId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>User Management - Supervisor</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transitions.css">
    <style>
        .dashboard-greeting { margin-bottom: 24px; }
        .dashboard-greeting h1 { font-size: 28px; color: #0d3b22; }
        .dashboard-greeting p  { color: #6c757d; font-size: 16px; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin-bottom: 24px; }
        .stat-card { background: white; border-radius: 12px; padding: 16px 18px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); display: flex; align-items: center; gap: 12px; border: 1px solid #f0f0f0; transition: all 0.3s; }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 25px rgba(0,0,0,0.1); }
        .stat-card .icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; flex-shrink: 0; }
        .stat-card .icon.green  { background: #d4edda; color: #155724; }
        .stat-card .icon.blue   { background: #cce5ff; color: #004085; }
        .stat-card .icon.orange { background: #fff3cd; color: #856404; }
        .stat-card .icon.purple { background: #e8d5f5; color: #6f42c1; }
        .stat-card .info .number { font-size: 22px; font-weight: 700; color: #0d3b22; }
        .stat-card .info .label  { font-size: 11px; color: #6c757d; }

        .section { background: white; border-radius: 14px; padding: 20px 22px; margin-bottom: 20px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); border: 1px solid #f0f0f0; }
        .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px; flex-wrap: wrap; gap: 10px; }
        .section-header h2 { font-size: 17px; color: #0d3b22; display: flex; align-items: center; gap: 10px; }

        .btn { padding: 9px 18px; border-radius: 8px; border: none; cursor: pointer; font-size: 13px; font-weight: 600; transition: all 0.2s; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
        .btn-primary { background: #1a5c3a; color: white; }
        .btn-primary:hover { background: #0d3b22; }
        .btn-primary:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn-secondary { background: #f0f0f0; color: #495057; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-sm { padding: 6px 12px; font-size: 11px; }

        .alert { padding: 12px 16px; border-radius: 10px; margin-bottom: 16px; font-size: 14px; }
        .alert.success { background: #d4edda; color: #155724; }
        .alert.danger  { background: #f8d7da; color: #721c24; }

        .users-table { width: 100%; border-collapse: collapse; }
        .users-table thead th {
            text-align: left; font-size: 11px; color: #6c757d;
            text-transform: uppercase; letter-spacing: 0.5px;
            padding: 10px 12px; border-bottom: 2px solid #f0f0f0;
            background: #fafafa; font-weight: 700;
        }
        .users-table tbody tr { border-bottom: 1px solid #f5f5f5; transition: background 0.15s; }
        .users-table tbody tr:hover { background: #fafafa; }
        .users-table td { padding: 12px; font-size: 13px; vertical-align: middle; }

        .user-cell { display: flex; align-items: center; gap: 10px; }
        .user-avatar-sm { width: 36px; height: 36px; border-radius: 50%; background: #1a5c3a; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; flex-shrink: 0; }
        .user-name { font-weight: 600; color: #0d3b22; }
        .user-email { font-size: 11px; color: #6c757d; }

        .role-pill { padding: 3px 10px; border-radius: 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; display: inline-block; }
        .role-pill.ranger   { background: #d4edda; color: #155724; }
        .role-pill.scout    { background: #cce5ff; color: #004085; }
        .role-pill.tourism  { background: #fce4ec; color: #c62828; }
        .role-pill.inactive { background: #f8d7da; color: #721c24; }

        .filter-bar { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .filter-btn { padding: 8px 16px; border-radius: 20px; background: #f0f0f0; color: #495057; text-decoration: none; font-size: 12px; font-weight: 600; transition: all 0.2s; }
        .filter-btn:hover { background: #e0e0e0; }
        .filter-btn.active { background: #1a5c3a; color: white; }

        .empty-state { text-align:center; padding:40px 20px; color:#6c757d; }
        .empty-state .icon { font-size: 48px; display: block; margin-bottom: 10px; opacity: 0.4; }

        .modal-backdrop { position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; display: none; align-items: center; justify-content: center; padding: 20px; }
        .modal-backdrop.show { display: flex; }
        .modal { background: white; border-radius: 14px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
        .modal-header { padding: 18px 22px; border-bottom: 1px solid #f0f0f0; display: flex; justify-content: space-between; align-items: center; }
        .modal-header h3 { font-size: 18px; color: #0d3b22; }
        .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d; }
        .modal-body { padding: 22px; }
        .modal-footer { padding: 16px 22px; border-top: 1px solid #f0f0f0; display: flex; justify-content: flex-end; gap: 10px; }

        .form-group { margin-bottom: 16px; position: relative; }
        .form-group label { display: block; font-size: 12px; color: #495057; font-weight: 600; margin-bottom: 6px; }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%; padding: 10px 38px 10px 14px; border: 1px solid #e0e0e0; border-radius: 8px;
            font-size: 13px; background: #fafafa; transition: all 0.2s;
        }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #1a5c3a; background: white; }

        /* Inline validation */
        .form-control.is-valid   { border-color: #28a745 !important; background: #f0fff4 !important; }
        .form-control.is-invalid { border-color: #dc3545 !important; background: #fff5f5 !important; }
        .field-status {
            position: absolute; right: 12px; top: 38px; font-size: 15px;
            pointer-events: none; opacity: 0; transition: opacity 0.2s;
        }
        .field-status.show { opacity: 1; }
        .field-status.valid   { color: #28a745; }
        .field-status.invalid { color: #dc3545; }
        .field-hint { font-size: 11.5px; color: #6c757d; margin-top: 4px; line-height: 1.4; }
        .field-hint.ok   { color: #28a745; }
        .field-hint.err  { color: #dc3545; }
        .field-hint.warn { color: #b45309; }
        .field-hint.checking { color: #6c757d; font-style: italic; }

        @media (max-width: 1024px) {
            .users-table thead { display: none; }
            .users-table, .users-table tbody, .users-table tr, .users-table td { display: block; width: 100%; }
            .users-table tr { margin-bottom: 12px; padding: 12px; border-radius: 10px; background: #fafafa; border: 1px solid #f0f0f0; }
            .users-table td { padding: 4px 0; border: none; }
            .users-table td::before { content: attr(data-label); font-size: 10px; text-transform: uppercase; color: #adb5bd; display: block; margin-bottom: 2px; }
        }
        @media (max-width: 768px) {
            .stats-grid { grid-template-columns: 1fr 1fr; gap: 10px; }
            .dashboard-greeting h1 { font-size: 22px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <?php include '../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-header">
            <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            <h1>User Management</h1>
            <div class="header-right">
                <span class="online-status">● Online</span>
                <span class="data-honesty-badge">🟢 Live Data</span>
                <span class="user-name"><?= htmlspecialchars($user['full_name']) ?></span>
            </div>
        </header>

        <div class="content">
            <div class="dashboard-greeting">
                <h1>👥 User Management</h1>
                <p>Manage rangers, scouts, and tourism operators in <strong><?= htmlspecialchars(getZoneName($activeZoneId)) ?></strong>.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert <?= $messageType ?>"><?= $message ?></div>
            <?php endif; ?>

            <div class="stats-grid">
                <div class="stat-card"><div class="icon green">🛡️</div><div class="info"><div class="number"><?= $totalRangers ?></div><div class="label">Rangers</div></div></div>
                <div class="stat-card"><div class="icon blue">👥</div><div class="info"><div class="number"><?= $totalScouts ?></div><div class="label">Scouts</div></div></div>
                <div class="stat-card"><div class="icon orange">🏨</div><div class="info"><div class="number"><?= $totalTourism ?></div><div class="label">Tourism Operators</div></div></div>
                <div class="stat-card"><div class="icon purple">⚪</div><div class="info"><div class="number"><?= $inactive ?></div><div class="label">Inactive</div></div></div>
            </div>

            <div class="section">
                <div class="section-header">
                    <h2>👥 Users in Zone</h2>
                    <button class="btn btn-primary" onclick="openCreateModal()">
                        <i class="fas fa-plus"></i> Add User
                    </button>
                </div>

                <div class="filter-bar">
                    <a href="users.php" class="filter-btn <?= !$filterRole ? 'active' : '' ?>">All</a>
                    <a href="users.php?role=ranger" class="filter-btn <?= $filterRole === 'ranger' ? 'active' : '' ?>">Rangers</a>
                    <a href="users.php?role=scout" class="filter-btn <?= $filterRole === 'scout' ? 'active' : '' ?>">Scouts</a>
                    <a href="users.php?role=tourism" class="filter-btn <?= $filterRole === 'tourism' ? 'active' : '' ?>">Tourism</a>
                </div>

                <?php if (count($users) > 0): ?>
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Seen</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): ?>
                            <tr>
                                <td data-label="User">
                                    <div class="user-cell">
                                        <div class="user-avatar-sm"><?= strtoupper(substr($u['full_name'], 0, 1)) ?></div>
                                        <div>
                                            <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Contact">
                                    <div style="font-size:12px;">📞 <?= htmlspecialchars($u['phone'] ?? 'N/A') ?></div>
                                    <?php if (!empty($u['badge_number'])): ?>
                                        <div style="font-size:11px;color:#6c757d;">Badge #<?= htmlspecialchars($u['badge_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Role">
                                    <span class="role-pill <?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars($u['role']) ?></span>
                                </td>
                                <td data-label="Status">
                                    <?php if (!$u['is_active']): ?>
                                        <span class="role-pill inactive">Inactive</span>
                                    <?php elseif (!empty($u['is_online'])): ?>
                                        <span class="role-pill ranger">🟢 Online</span>
                                    <?php else: ?>
                                        <span class="role-pill scout">⚪ Offline</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Last Seen">
                                    <div style="font-size:12px;"><?= timeAgo($u['last_seen'] ?? null) ?></div>
                                </td>
                                <td data-label="Actions">
                                    <div style="display:flex;flex-direction:column;gap:6px;">
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <button type="submit" class="btn btn-sm <?= $u['is_active'] ? 'btn-secondary' : 'btn-success' ?>">
                                                <?= $u['is_active'] ? 'Deactivate' : 'Activate' ?>
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-secondary" onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">Reset Password</button>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $u['id'] ?>, '<?= htmlspecialchars($u['full_name']) ?>')">Delete</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <span class="icon">👥</span>
                        <h3>No users found</h3>
                        <p>Add rangers, scouts, or tourism operators to your zone.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<!-- CREATE MODAL -->
<div class="modal-backdrop" id="createModal">
    <div class="modal">
        <form method="POST" id="createUserForm" novalidate autocomplete="off">
            <div class="modal-header">
                <h3>➕ Add User</h3>
                <button type="button" class="modal-close" onclick="closeCreateModal()">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="create">

                <div class="form-group">
                    <label for="create_full_name">Full Name *</label>
                    <input type="text" name="full_name" id="create_full_name" class="form-control"
                           required minlength="2" maxlength="100" placeholder="e.g. John Banda">
                    <span class="field-status" id="create_full_name_status"></span>
                    <div class="field-hint" id="create_full_name_hint"></div>
                </div>

                <div class="form-group">
                    <label for="create_role">Role *</label>
                    <select name="role" id="create_role" required>
                        <option value="scout">Community Scout</option>
                        <option value="ranger">Ranger</option>
                        <option value="tourism">Tourism / Lodge Operator</option>
                    </select>
                    <div class="field-hint" id="create_role_hint"></div>
                </div>

                <div class="form-group">
                    <label for="create_email">Email *</label>
                    <input type="email" name="email" id="create_email" class="form-control"
                           required maxlength="254" placeholder="user@gmail.com" autocomplete="email">
                    <span class="field-status" id="create_email_status"></span>
                    <div class="field-hint" id="create_email_hint"></div>
                </div>

                <div class="form-group">
                    <label for="create_phone">Phone</label>
                    <input type="tel" name="phone" id="create_phone" class="form-control"
                           maxlength="15" placeholder="+260 97 1234567" inputmode="tel" autocomplete="tel">
                    <span class="field-status" id="create_phone_status"></span>
                    <div class="field-hint" id="create_phone_hint">
                        Optional. Must be unique — one phone per user (used for SMS alerts).
                    </div>
                </div>

                <div class="form-group">
                    <label for="create_badge">Badge Number (rangers only)</label>
                    <input type="text" name="badge_number" id="create_badge"
                           maxlength="20" placeholder="e.g. R-1024">
                    <div class="field-hint" id="create_badge_hint"></div>
                </div>

                <div class="form-group">
                    <label for="create_password">Temporary Password *</label>
                    <input type="text" name="password" id="create_password" class="form-control"
                           required minlength="8" placeholder="Min 8 characters">
                    <span class="field-status" id="create_password_status"></span>
                    <div class="field-hint" id="create_password_hint"></div>
                </div>

                <p style="font-size:12px;color:#6c757d;">User will be assigned to your zone automatically.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeCreateModal()">Cancel</button>
                <button type="submit" class="btn btn-primary" id="createSubmit">Create User</button>
            </div>
        </form>
    </div>
</div>

<!-- RESET PASSWORD MODAL -->
<div class="modal-backdrop" id="resetModal">
    <div class="modal">
        <form method="POST">
            <div class="modal-header">
                <h3>🔑 Reset Password</h3>
                <button type="button" class="modal-close" onclick="document.getElementById('resetModal').classList.remove('show')">×</button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">

                <div class="form-group">
                    <label>User</label>
                    <input type="text" id="resetUserName" disabled style="background:#f0f0f0;">
                </div>

                <div class="form-group">
                    <label>New Password *</label>
                    <input type="text" name="new_password" required minlength="8" placeholder="Min 8 characters">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('resetModal').classList.remove('show')">Cancel</button>
                <button type="submit" class="btn btn-primary">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="user_id" id="deleteUserId">
</form>

<script src="../assets/js/app.js"></script>
<script src="../assets/js/transitions.js"></script>
<script>
    // ============================================================
    // MODAL HELPERS
    // ============================================================
    function openCreateModal() {
        const form = document.getElementById('createUserForm');
        form.reset();
        form.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-valid','is-invalid'));
        form.querySelectorAll('.field-status').forEach(el => {
            el.classList.remove('show','valid','invalid'); el.textContent = '';
        });
        form.querySelectorAll('.field-hint').forEach(el => {
            el.classList.remove('ok','err','warn','checking');
        });
        // Reset the phone hint to its default text
        document.getElementById('create_phone_hint').textContent =
            'Optional. Must be unique — one phone per user (used for SMS alerts).';
        document.getElementById('createModal').classList.add('show');
        setTimeout(() => document.getElementById('create_full_name').focus(), 150);
    }
    function closeCreateModal() {
        document.getElementById('createModal').classList.remove('show');
    }
    function openResetModal(id, name) {
        document.getElementById('resetUserId').value = id;
        document.getElementById('resetUserName').value = name;
        document.getElementById('resetModal').classList.add('show');
    }
    function confirmDelete(id, name) {
        if (confirm('Remove user "' + name + '"? They will be deactivated.')) {
            document.getElementById('deleteUserId').value = id;
            document.getElementById('deleteForm').submit();
        }
    }

    // ============================================================
    // VALIDATION HELPERS
    // ============================================================
    const EMAIL_RE = /^(?=.{6,254}$)[A-Za-z0-9](?:[A-Za-z0-9._%+\-]{0,62}[A-Za-z0-9])?@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/;
    const PHONE_RE = /^(09|07)[0-9]{8}$/;
    const NAME_RE  = /^[\p{L}\s'\-\.]+$/u;

    // Common email typos -> correct domain (soft warning, does not block)
    const EMAIL_TYPO_MAP = {
        'gmial.com':'gmail.com',  'gmai.com':'gmail.com',
        'gamil.com':'gmail.com',  'gmal.com':'gmail.com',
        'gnail.com':'gmail.com',  'gmaill.com':'gmail.com',
        'gmail.co':'gmail.com',   'gmail.con':'gmail.com',
        'gmail.cm':'gmail.com',   'gmail.comm':'gmail.com',
        'yaho.com':'yahoo.com',   'yahooo.com':'yahoo.com',
        'yahoo.co':'yahoo.com',   'hotmial.com':'hotmail.com',
        'hotmai.com':'hotmail.com','outlok.com':'outlook.com',
        'outloo.com':'outlook.com','gmail.cim':'gmail.com'
    };
    const EMAIL_BLOCKED_DOMAINS = new Set([
        'example.com','example.org','example.net','test.com','test.org','test.net',
        'localhost','invalid','mailinator.com','tempmail.com','guerrillamail.com',
        '10minutemail.com','yopmail.com','trashmail.com','sharklasers.com'
    ]);

    function setFieldState(inputEl, statusEl, hintEl, state, msg, hintClass) {
        if (!inputEl) return;
        inputEl.classList.remove('is-valid','is-invalid');
        if (statusEl) statusEl.classList.remove('show','valid','invalid');
        if (hintEl) hintEl.classList.remove('ok','err','warn','checking');

        if (state === 'valid') {
            inputEl.classList.add('is-valid');
            if (statusEl) { statusEl.textContent = '✓'; statusEl.classList.add('show','valid'); }
            if (hintEl && msg) { hintEl.textContent = msg; hintEl.classList.add(hintClass || 'ok'); }
        } else if (state === 'invalid') {
            inputEl.classList.add('is-invalid');
            if (statusEl) { statusEl.textContent = '✕'; statusEl.classList.add('show','invalid'); }
            if (hintEl && msg) { hintEl.textContent = msg; hintEl.classList.add(hintClass || 'err'); }
        } else if (state === 'checking') {
            if (hintEl && msg) { hintEl.textContent = msg; hintEl.classList.add('checking'); }
        } else {
            if (hintEl && msg) hintEl.textContent = msg;
        }
    }

    function validateFullName(v) {
        v = (v || '').trim();
        if (!v) return { ok:false, msg:'Full name is required' };
        if (v.length < 2) return { ok:false, msg:'Must be at least 2 characters' };
        if (v.length > 100) return { ok:false, msg:'Must not exceed 100 characters' };
        if (!NAME_RE.test(v)) return { ok:false, msg:'Only letters, spaces, hyphens and apostrophes' };
        return { ok:true, msg:'Looks good' };
    }

    function normalizePhone(v) {
        v = (v || '').trim();
        if (!v) return '';
        let n = v.replace(/[^0-9]/g, '');
        if (n.indexOf('260') === 0) n = n.substring(3);
        return n;
    }

    function validatePhoneFormat(v) {
        v = (v || '').trim();
        if (!v) return { ok:true, msg:'' }; // optional
        const n = normalizePhone(v);
        if (!PHONE_RE.test(n)) return { ok:false, msg:'Use 0971234567 or 0771234567 (or leave blank)' };
        return { ok:true, msg:'Format OK' };
    }

    function validateEmail(v) {
        v = (v || '').trim();
        if (!v) return { ok:false, msg:'Email is required' };
        if (v.length > 254) return { ok:false, msg:'Email is too long (max 254)' };
        if (v.indexOf(' ') !== -1) return { ok:false, msg:'Email cannot contain spaces' };
        if (v.indexOf('..') !== -1) return { ok:false, msg:'Email cannot contain consecutive dots' };
        if (v[0] === '.' || v[v.length-1] === '.') {
            return { ok:false, msg:'Email cannot start or end with a dot' };
        }
        if ((v.match(/@/g) || []).length !== 1) {
            return { ok:false, msg:'Please enter a valid email address' };
        }
        const [local, domain] = v.split('@');
        if (!local || local.length > 64) return { ok:false, msg:'Please enter a valid email address' };
        if (!/^[A-Za-z0-9._%+\-]+$/.test(local)) return { ok:false, msg:'Email contains invalid characters' };
        if (local[0] === '.' || local[local.length-1] === '.' || local.indexOf('..') !== -1) {
            return { ok:false, msg:'Please enter a valid email address' };
        }
        if (!/^(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/.test(domain)) {
            return { ok:false, msg:'Please enter a valid email domain (e.g. example.com)' };
        }
        const d = domain.toLowerCase();
        if (EMAIL_BLOCKED_DOMAINS.has(d)) {
            return { ok:false, msg:'Please use a real email (disposable/placeholder domains blocked)' };
        }
        if (!EMAIL_RE.test(v)) return { ok:false, msg:'Please enter a valid email address' };

        // Soft typo suggestion
        if (EMAIL_TYPO_MAP[d]) {
            return { ok:true, warn:true, msg:'⚠️ Did you mean ' + local + '@' + EMAIL_TYPO_MAP[d] + '?' };
        }
        return { ok:true, msg:'Looks good' };
    }

    function validatePassword(v) {
        if (!v) return { ok:false, msg:'Password is required' };
        if (v.length < 8) return { ok:false, msg:'Must be at least 8 characters' };
        return { ok:true, msg:'Looks good' };
    }

    // Debounced phone-uniqueness check
    let phoneCheckTimer = null;
    function checkPhoneUnique(inputEl, statusEl, hintEl) {
        const raw = inputEl.value.trim();
        if (!raw) {
            setFieldState(inputEl, statusEl, hintEl, 'neutral',
                'Optional. Must be unique — one phone per user (used for SMS alerts).');
            return Promise.resolve(true);
        }
        const fmt = validatePhoneFormat(raw);
        if (!fmt.ok) {
            setFieldState(inputEl, statusEl, hintEl, 'invalid', fmt.msg);
            return Promise.resolve(false);
        }
        const n = normalizePhone(raw);
        setFieldState(inputEl, statusEl, hintEl, 'checking', '⏳ Checking availability…');

        if (phoneCheckTimer) clearTimeout(phoneCheckTimer);

        return new Promise(resolve => {
            phoneCheckTimer = setTimeout(() => {
                fetch('users.php?ajax=check_phone&phone=' + encodeURIComponent(n))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.success) {
                            setFieldState(inputEl, statusEl, hintEl, 'invalid', 'Invalid phone format');
                            resolve(false);
                        } else if (data.exists) {
                            setFieldState(inputEl, statusEl, hintEl, 'invalid',
                                '❌ Already registered to ' + (data.full_name || 'another user'));
                            resolve(false);
                        } else {
                            setFieldState(inputEl, statusEl, hintEl, 'valid', '✅ Available');
                            resolve(true);
                        }
                    })
                    .catch(() => {
                        setFieldState(inputEl, statusEl, hintEl, 'invalid',
                            '⚠️ Could not verify phone. Try again.');
                        resolve(false);
                    });
            }, 450);
        });
    }

    // ============================================================
    // LIVE VALIDATION WIRING
    // ============================================================
    (function () {
        const form = document.getElementById('createUserForm');
        if (!form) return;

        const nameEl  = document.getElementById('create_full_name');
        const roleEl  = document.getElementById('create_role');
        const emailEl = document.getElementById('create_email');
        const phoneEl = document.getElementById('create_phone');
        const badgeEl = document.getElementById('create_badge');
        const pwEl    = document.getElementById('create_password');
        const submit  = document.getElementById('createSubmit');

        const s = {
            nameStatus:  document.getElementById('create_full_name_status'),
            nameHint:    document.getElementById('create_full_name_hint'),
            emailStatus: document.getElementById('create_email_status'),
            emailHint:   document.getElementById('create_email_hint'),
            phoneStatus: document.getElementById('create_phone_status'),
            phoneHint:   document.getElementById('create_phone_hint'),
            pwStatus:    document.getElementById('create_password_status'),
            pwHint:      document.getElementById('create_password_hint'),
            roleHint:    document.getElementById('create_role_hint'),
            badgeHint:   document.getElementById('create_badge_hint'),
        };

        function runName() {
            const r = validateFullName(nameEl.value);
            setFieldState(nameEl, s.nameStatus, s.nameHint,
                r.ok ? 'valid' : (nameEl.value ? 'invalid' : 'neutral'),
                nameEl.value ? r.msg : '');
            return r.ok;
        }
        function runEmail() {
            const r = validateEmail(emailEl.value);
            setFieldState(emailEl, s.emailStatus, s.emailHint,
                r.ok ? 'valid' : (emailEl.value ? 'invalid' : 'neutral'),
                emailEl.value ? r.msg : '',
                r.warn ? 'warn' : null);
            return r.ok;
        }
        function runPassword() {
            const r = validatePassword(pwEl.value);
            setFieldState(pwEl, s.pwStatus, s.pwHint,
                r.ok ? 'valid' : (pwEl.value ? 'invalid' : 'neutral'),
                pwEl.value ? r.msg : '');
            return r.ok;
        }
        function runRole() {
            const v = roleEl.value;
            if (s.roleHint) {
                s.roleHint.textContent = v ? 'Selected: ' + v : '';
                s.roleHint.classList.remove('ok','err','warn');
                if (v) s.roleHint.classList.add('ok');
            }
            return !!v;
        }

        nameEl.addEventListener('input', runName);
        nameEl.addEventListener('blur',  runName);
        roleEl.addEventListener('change', runRole);

        emailEl.addEventListener('input', runEmail);
        emailEl.addEventListener('blur',  runEmail);

        pwEl.addEventListener('input', runPassword);
        pwEl.addEventListener('blur',  runPassword);

        // Phone: format feedback on every keystroke, uniqueness on blur/paste
        phoneEl.addEventListener('input', function () {
            // auto-prefix +260
            let v = this.value.replace(/[^\d+\s\-()]/g, '');
            if (/^260\d{0,9}$/.test(v)) v = '+' + v;
            this.value = v;

            const fmt = validatePhoneFormat(this.value);
            if (!this.value) {
                setFieldState(phoneEl, s.phoneStatus, s.phoneHint, 'neutral',
                    'Optional. Must be unique — one phone per user (used for SMS alerts).');
            } else if (fmt.ok) {
                setFieldState(phoneEl, s.phoneStatus, s.phoneHint, 'checking',
                    '⏳ Checking availability…');
            } else {
                setFieldState(phoneEl, s.phoneStatus, s.phoneHint, 'invalid', fmt.msg);
            }
        });
        phoneEl.addEventListener('blur', function () {
            checkPhoneUnique(phoneEl, s.phoneStatus, s.phoneHint);
        });
        phoneEl.addEventListener('paste', function () {
            setTimeout(() => checkPhoneUnique(phoneEl, s.phoneStatus, s.phoneHint), 0);
        });

        // Badge number hint updates based on role
        function refreshBadgeHint() {
            if (!s.badgeHint) return;
            s.badgeHint.classList.remove('ok','err','warn');
            if (roleEl.value === 'ranger') {
                s.badgeHint.textContent = 'Recommended for rangers (e.g. R-1024)';
                s.badgeHint.classList.add('ok');
            } else if (roleEl.value === 'scout') {
                s.badgeHint.textContent = 'Optional for scouts';
            } else if (roleEl.value === 'tourism') {
                s.badgeHint.textContent = 'Not typically used for tourism operators';
            }
        }
        roleEl.addEventListener('change', refreshBadgeHint);
        refreshBadgeHint();

        // Submit gate — validate everything, then submit
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const results = await Promise.all([
                Promise.resolve(runName()),
                Promise.resolve(runEmail()),
                Promise.resolve(runPassword()),
                Promise.resolve(runRole()),
                checkPhoneUnique(phoneEl, s.phoneStatus, s.phoneHint),
            ]);
            if (!results.every(Boolean)) {
                const firstInvalid = form.querySelector('.form-control.is-invalid');
                if (firstInvalid) { firstInvalid.focus(); firstInvalid.scrollIntoView({behavior:'smooth',block:'center'}); }
                return false;
            }
            submit.disabled = true;
            submit.textContent = '⏳ Creating…';
            form.submit();
        });

        // Initial validation if values are prefilled by server after a failed POST
        if (nameEl.value) runName();
        if (emailEl.value) runEmail();
        if (pwEl.value)   runPassword();
    })();
</script>
</body>
</html>