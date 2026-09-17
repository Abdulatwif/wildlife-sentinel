<?php
require_once '../includes/functions.php';
requireLogin();

$user = getCurrentUser();
$pdo = getDB();
$success = '';
$error = '';

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $email = sanitize($_POST['email']);
        
        if (empty($fullName)) {
            $error = 'Full name is required';
        } elseif (!validateEmail($email)) {
            $error = 'Invalid email address';
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user['id']]);
            if ($stmt->fetch()) {
                $error = 'Email already exists for another user';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, email = ? WHERE id = ?");
                if ($stmt->execute([$fullName, $phone, $email, $user['id']])) {
                    $success = 'Profile updated successfully';
                    $_SESSION['user_name'] = $fullName;
                    logAudit($user['id'], 'update_profile');
                    $user = getCurrentUser();
                } else {
                    $error = 'Failed to update profile';
                }
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in all password fields';
        } elseif (!verifyPassword($currentPassword, $user['password_hash'])) {
            $error = 'Current password is incorrect';
        } elseif (strlen($newPassword) < 8) {
            $error = 'New password must be at least 8 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $hash = hashPassword($newPassword);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $user['id']])) {
                $success = 'Password changed successfully';
                logAudit($user['id'], 'change_password');
            } else {
                $error = 'Failed to change password';
            }
        }
    }
}

// Get user statistics
$stmt = $pdo->prepare("SELECT (SELECT COUNT(*) FROM incidents WHERE reporter_id = ?) as reported_count, (SELECT COUNT(*) FROM incidents WHERE acknowledged_by = ?) as acknowledged_count, (SELECT COUNT(*) FROM messages WHERE sender_id = ?) as messages_sent, (SELECT COUNT(*) FROM messages WHERE recipient_id = ?) as messages_received, (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) as unread_notifications, (SELECT COUNT(*) FROM audit_logs WHERE user_id = ?) as audit_count");
$stmt->execute([$user['id'], $user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);
$stats = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("SELECT action, created_at FROM audit_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stmt->execute([$user['id']]);
$activities = $stmt->fetchAll();

function getRoleBadgeClass($role) {
    $classes = ['admin' => 'badge-danger', 'ranger' => 'badge-primary', 'scout' => 'badge-info', 'tourism' => 'badge-warning', 'zone_supervisor' => 'badge-purple'];
    return isset($classes[$role]) ? $classes[$role] : 'badge-secondary';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .profile-container { display: grid; grid-template-columns: 320px 1fr; gap: 25px; }
        .profile-card { background: white; border-radius: 12px; padding: 30px; text-align: center; box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: sticky; top: 20px; }
        .profile-avatar { width: 120px; height: 120px; border-radius: 50%; background: linear-gradient(135deg, #1a5c3a, #2d8a4e); color: white; display: flex; align-items: center; justify-content: center; font-size: 48px; margin: 0 auto 15px; box-shadow: 0 4px 15px rgba(26,92,58,0.3); }
        .profile-name { font-size: 22px; font-weight: 700; color: #0d3b22; }
        .profile-role { display: inline-block; padding: 4px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 5px; }
        .badge-purple { background: #6f42c1; color: white; }
        .profile-email { color: #6c757d; font-size: 14px; margin-top: 5px; }
        .profile-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 20px; padding-top: 20px; border-top: 1px solid #e9ecef; }
        .stat-item { background: #f8f9fa; padding: 12px; border-radius: 8px; }
        .stat-item .number { font-size: 22px; font-weight: 700; color: #0d3b22; }
        .stat-item .label { font-size: 11px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.3px; }
        .profile-forms { display: flex; flex-direction: column; gap: 25px; }
        .profile-form { background: white; border-radius: 12px; padding: 25px 30px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .profile-form h3 { font-size: 18px; color: #0d3b22; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; display: flex; align-items: center; gap: 10px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .password-hint { font-size: 12px; color: #6c757d; margin-top: 5px; }
        .password-hint ul { margin: 5px 0 0 20px; padding: 0; }
        .password-hint li { margin: 2px 0; }
        .activity-item { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .activity-item:last-child { border-bottom: none; }
        .activity-item .time { color: #6c757d; font-size: 12px; }
        @media (max-width: 1024px) { .profile-container { grid-template-columns: 1fr; } .profile-card { position: static; } .form-row { grid-template-columns: 1fr; } }
        @media (max-width: 768px) { .profile-card { padding: 20px; } .profile-avatar { width: 80px; height: 80px; font-size: 32px; } .profile-form { padding: 20px; } .profile-stats { grid-template-columns: 1fr 1fr; } }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h1>My Profile</h1>
                <div class="header-right">
                    <span class="online-status">● Online</span>
                    <span class="data-honesty-badge">🟢 Live Data</span>
                    <span class="user-name"><?= $user['full_name'] ?></span>
                </div>
            </header>
            
            <div class="content">
                <?php if ($success): ?><div class="alert alert-success">✅ <?= $success ?></div><?php endif; ?>
                <?php if ($error): ?><div class="alert alert-danger">❌ <?= $error ?></div><?php endif; ?>
                
                <div class="profile-container">
                    <div class="profile-card">
                        <div class="profile-avatar"><?= substr($user['full_name'], 0, 1) ?></div>
                        <div class="profile-name"><?= $user['full_name'] ?></div>
                        <div class="profile-role <?= getRoleBadgeClass($user['role']) ?>"><?= ucfirst(str_replace('_', ' ', $user['role'])) ?></div>
                        <div class="profile-email">📧 <?= $user['email'] ?></div>
                        <?php if ($user['phone']): ?><div class="profile-email">📞 <?= $user['phone'] ?></div><?php endif; ?>
                        <?php if ($user['zone_id']): ?><div class="profile-email">📍 <?= getZoneName($user['zone_id']) ?></div><?php endif; ?>
                        <div class="profile-stats">
                            <div class="stat-item"><div class="number"><?= $stats['reported_count'] ?? 0 ?></div><div class="label">Reports</div></div>
                            <div class="stat-item"><div class="number"><?= $stats['acknowledged_count'] ?? 0 ?></div><div class="label">Acknowledged</div></div>
                            <div class="stat-item"><div class="number"><?= $stats['messages_sent'] ?? 0 ?></div><div class="label">Messages Sent</div></div>
                            <div class="stat-item"><div class="number"><?= $stats['unread_notifications'] ?? 0 ?></div><div class="label">Unread</div></div>
                        </div>
                        <div style="margin-top:15px;font-size:12px;color:#adb5bd;">Member since <?= date('M j, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    
                    <div class="profile-forms">
                        <div class="profile-form">
                            <h3>✏️ Edit Profile</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="form-group"><label>Full Name *</label><input type="text" name="full_name" class="form-control" value="<?= $user['full_name'] ?>" required></div>
                                <div class="form-row">
                                    <div class="form-group"><label>Email Address *</label><input type="email" name="email" class="form-control" value="<?= $user['email'] ?>" required></div>
                                    <div class="form-group"><label>Phone Number</label><input type="tel" name="phone" class="form-control" value="<?= $user['phone'] ?? '' ?>"></div>
                                </div>
                                <button type="submit" class="btn btn-primary">💾 Update Profile</button>
                            </form>
                        </div>
                        
                        <div class="profile-form">
                            <h3>🔒 Change Password</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="change_password">
                                <div class="form-group"><label>Current Password *</label><input type="password" name="current_password" class="form-control" required></div>
                                <div class="form-row">
                                    <div class="form-group"><label>New Password *</label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
                                    <div class="form-group"><label>Confirm New Password *</label><input type="password" name="confirm_password" class="form-control" required minlength="8"></div>
                                </div>
                                <div class="password-hint"><strong>Password Requirements:</strong><ul><li>✅ Minimum 8 characters</li><li>✅ At least one uppercase letter</li><li>✅ At least one lowercase letter</li><li>✅ At least one number</li></ul></div>
                                <button type="submit" class="btn btn-primary">🔑 Change Password</button>
                            </form>
                        </div>
                        
                        <div class="profile-form">
                            <h3>📋 Recent Activity</h3>
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $activity): ?>
                                <div class="activity-item"><span><?= str_replace('_', ' ', $activity['action']) ?></span><span class="time"><?= timeAgo($activity['created_at']) ?></span></div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#6c757d;font-size:14px;">No recent activity found.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js"></script>
</body>
</html>