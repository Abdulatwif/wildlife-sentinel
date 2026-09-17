<?php
require_once '../includes/functions.php';
requireSupervisor();

$user = getCurrentUser();
$pdo = getDB();

$userId = $_GET['id'] ?? 0;
$error = '';
$success = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$editUser = $stmt->fetch();

if (!$editUser) {
    header('Location: users.php');
    exit();
}

// Check permissions
if ($user['role'] === 'zone_supervisor' && $editUser['zone_id'] != $user['zone_id']) {
    header('Location: users.php');
    exit();
}

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM incidents WHERE reporter_id = ?) as reported_count,
        (SELECT COUNT(*) FROM incidents WHERE acknowledged_by = ?) as acknowledged_count,
        (SELECT COUNT(*) FROM messages WHERE sender_id = ?) as messages_sent,
        (SELECT COUNT(*) FROM messages WHERE recipient_id = ?) as messages_received,
        (SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0) as unread_notifications,
        (SELECT COUNT(*) FROM audit_logs WHERE user_id = ?) as audit_count
");
$stmt->execute([$userId, $userId, $userId, $userId, $userId, $userId]);
$stats = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT action, created_at 
    FROM audit_logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 10
");
$stmt->execute([$userId]);
$activities = $stmt->fetchAll();

// Handle user update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_user') {
        $fullName = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone'] ?? '');
        $role = $_POST['role'];
        $zoneId = $_POST['zone_id'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $email = sanitize($_POST['email']);
        
        // Validate email
        if (!validateEmail($email)) {
            $error = 'Invalid email address';
        } elseif (empty($fullName)) {
            $error = 'Full name is required';
        } else {
            // Check if email already exists for another user
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Email already exists for another user';
            } else {
                // Check permissions for role change
                if ($user['role'] === 'zone_supervisor' && $role === 'admin') {
                    $error = 'You cannot assign admin role';
                } elseif ($user['role'] === 'zone_supervisor' && $zoneId != $user['zone_id']) {
                    $error = 'You can only assign users to your zone';
                } else {
                    $sql = "UPDATE users SET full_name = ?, phone = ?, email = ?, role = ?, zone_id = ?, is_active = ? WHERE id = ?";
                    $params = [$fullName, $phone, $email, $role, $zoneId, $isActive, $userId];
                    
                    $stmt = $pdo->prepare($sql);
                    if ($stmt->execute($params)) {
                        $success = 'User updated successfully';
                        logAudit($user['id'], 'update_user', ['user_id' => $userId]);
                        
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $editUser = $stmt->fetch();
                    } else {
                        $error = 'Failed to update user';
                    }
                }
            }
        }
    }
    
    if ($action === 'reset_password') {
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $error = 'Please fill in both password fields';
        } elseif (strlen($newPassword) < 6) {
            $error = 'Password must be at least 6 characters';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'Passwords do not match';
        } else {
            $hash = hashPassword($newPassword);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            if ($stmt->execute([$hash, $userId])) {
                $success = 'Password reset successfully';
                logAudit($user['id'], 'reset_password', ['user_id' => $userId]);
            } else {
                $error = 'Failed to reset password';
            }
        }
    }
    
    if ($action === 'delete_user') {
        if ($userId == $user['id']) {
            $error = 'You cannot delete your own account';
        } elseif ($editUser['role'] === 'admin' && $user['role'] !== 'admin') {
            $error = 'You cannot delete an admin account';
        } else {
            // Check if user has incidents
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? OR acknowledged_by = ?");
            $stmt->execute([$userId, $userId]);
            $incidentCount = $stmt->fetch()['count'];
            
            if ($incidentCount > 0) {
                $error = 'Cannot delete user - they have ' . $incidentCount . ' incidents. Reassign them first.';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    logAudit($user['id'], 'delete_user', ['user_id' => $userId]);
                    header('Location: users.php?deleted=1');
                    exit();
                } else {
                    $error = 'Failed to delete user';
                }
            }
        }
    }
}

// Get zones
$zones = $pdo->query("SELECT id, name FROM zones WHERE is_active = 1 ORDER BY name")->fetchAll();

// Available roles
$roles = ['ranger', 'scout', 'tourism', 'zone_supervisor'];
if ($user['role'] === 'admin') {
    $roles[] = 'admin';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-container {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: sticky;
            top: 20px;
        }
        
        .user-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1a5c3a, #2d8a4e);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 15px;
            box-shadow: 0 4px 15px rgba(26,92,58,0.3);
        }
        
        .user-name {
            font-size: 20px;
            font-weight: 700;
            color: #0d3b22;
        }
        
        .user-email {
            color: #6c757d;
            font-size: 14px;
        }
        
        .user-role-badge {
            display: inline-block;
            padding: 4px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        
        .badge-purple { background: #6f42c1; color: white; }
        .badge-danger { background: #dc3545; color: white; }
        .badge-primary { background: #007bff; color: white; }
        .badge-info { background: #17a2b8; color: white; }
        .badge-warning { background: #ffc107; color: #333; }
        .badge-secondary { background: #6c757d; color: white; }
        
        .user-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e9ecef;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
        }
        
        .stat-item .number {
            font-size: 20px;
            font-weight: 700;
            color: #0d3b22;
        }
        
        .stat-item .label {
            font-size: 10px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        
        .edit-forms {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .edit-form {
            background: white;
            border-radius: 12px;
            padding: 25px 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }
        
        .edit-form h3 {
            font-size: 18px;
            color: #0d3b22;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 13px;
            color: #495057;
        }
        
        .form-control {
            width: 100%;
            padding: 10px 14px;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #1a5c3a;
            outline: none;
            box-shadow: 0 0 0 4px rgba(26,92,58,0.1);
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: #1a5c3a;
            color: white;
        }
        
        .btn-primary:hover {
            background: #0d3b22;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(26,92,58,0.3);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-danger:hover {
            background: #c62828;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        
        .btn-warning:hover {
            background: #e0a800;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-block {
            width: 100%;
        }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item .time {
            color: #6c757d;
            font-size: 12px;
        }
        
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 15px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        @media (max-width: 1024px) {
            .edit-container {
                grid-template-columns: 1fr;
            }
            
            .user-card {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .user-card {
                padding: 20px;
            }
            
            .user-avatar {
                width: 80px;
                height: 80px;
                font-size: 32px;
            }
            
            .edit-form {
                padding: 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn-group .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <?php include '../includes/sidebar.php'; ?>
        
        <main class="main-content">
            <header class="top-header">
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
                <h1>Edit User</h1>
                <div class="header-right">
                    <span class="online-status">● Online</span>
                    <span class="data-honesty-badge">🟢 Live Data</span>
                    <span class="user-name"><?= $user['full_name'] ?></span>
                </div>
            </header>
            
            <div class="content">
                <?php if ($success): ?>
                    <div class="alert alert-success">✅ <?= $success ?></div>
                <?php endif; ?>
                <?php if ($error): ?>
                    <div class="alert alert-danger">❌ <?= $error ?></div>
                <?php endif; ?>
                
                <div class="edit-container">
                    <!-- User Card -->
                    <div class="user-card">
                        <div class="user-avatar">
                            <?= substr($editUser['full_name'], 0, 1) ?>
                        </div>
                        <div class="user-name"><?= $editUser['full_name'] ?></div>
                        <div class="user-email">📧 <?= $editUser['email'] ?></div>
                        <div class="user-role-badge <?= getRoleBadgeClass($editUser['role']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $editUser['role'])) ?>
                        </div>
                        <?php if ($editUser['zone_id']): ?>
                        <div style="margin-top:5px;color:#6c757d;font-size:13px;">
                            📍 <?= getZoneName($editUser['zone_id']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="user-stats">
                            <div class="stat-item">
                                <div class="number"><?= $stats['reported_count'] ?? 0 ?></div>
                                <div class="label">Reports</div>
                            </div>
                            <div class="stat-item">
                                <div class="number"><?= $stats['acknowledged_count'] ?? 0 ?></div>
                                <div class="label">Acknowledged</div>
                            </div>
                            <div class="stat-item">
                                <div class="number"><?= $stats['messages_sent'] ?? 0 ?></div>
                                <div class="label">Messages Sent</div>
                            </div>
                            <div class="stat-item">
                                <div class="number"><?= $stats['unread_notifications'] ?? 0 ?></div>
                                <div class="label">Unread</div>
                            </div>
                        </div>
                        
                        <div style="margin-top:15px;font-size:12px;color:#adb5bd;">
                            Member since <?= date('M j, Y', strtotime($editUser['created_at'])) ?>
                        </div>
                        
                        <?php if ($editUser['last_online']): ?>
                        <div style="margin-top:5px;font-size:12px;color:#adb5bd;">
                            Last online: <?= timeAgo($editUser['last_online']) ?>
                        </div>
                        <?php endif; ?>
                        
                        <div style="margin-top:15px;">
                            <span class="badge badge-<?= $editUser['is_active'] ? 'success' : 'danger' ?>">
                                <?= $editUser['is_active'] ? '🟢 Active' : '🔴 Inactive' ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Edit Forms -->
                    <div class="edit-forms">
                        <!-- Update User Form -->
                        <div class="edit-form">
                            <h3>✏️ Edit User Details</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="update_user">
                                
                                <div class="form-group">
                                    <label>Full Name *</label>
                                    <input type="text" name="full_name" class="form-control" 
                                           value="<?= $editUser['full_name'] ?>" required>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Email Address *</label>
                                        <input type="email" name="email" class="form-control" 
                                               value="<?= $editUser['email'] ?>" required>
                                    </div>
                                    <div class="form-group">
                                        <label>Phone Number</label>
                                        <input type="tel" name="phone" class="form-control" 
                                               value="<?= $editUser['phone'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>Role *</label>
                                        <select name="role" class="form-control" required>
                                            <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role ?>" <?= $editUser['role'] === $role ? 'selected' : '' ?>>
                                                <?= ucfirst(str_replace('_', ' ', $role)) ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Zone</label>
                                        <select name="zone_id" class="form-control">
                                            <option value="">No Zone</option>
                                            <?php foreach ($zones as $zone): ?>
                                            <option value="<?= $zone['id'] ?>" <?= $editUser['zone_id'] == $zone['id'] ? 'selected' : '' ?>>
                                                <?= $zone['name'] ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" name="is_active" value="1" <?= $editUser['is_active'] ? 'checked' : '' ?>>
                                    <label style="margin:0;">Active Account</label>
                                </div>
                                
                                <button type="submit" class="btn btn-primary">💾 Update User</button>
                            </form>
                        </div>
                        
                        <!-- Reset Password Form -->
                        <div class="edit-form">
                            <h3>🔒 Reset Password</h3>
                            <form method="POST">
                                <input type="hidden" name="action" value="reset_password">
                                
                                <div class="form-row">
                                    <div class="form-group">
                                        <label>New Password *</label>
                                        <input type="password" name="new_password" class="form-control" 
                                               placeholder="Enter new password" required minlength="6">
                                    </div>
                                    <div class="form-group">
                                        <label>Confirm Password *</label>
                                        <input type="password" name="confirm_password" class="form-control" 
                                               placeholder="Confirm new password" required minlength="6">
                                    </div>
                                </div>
                                
                                <button type="submit" class="btn btn-warning">🔑 Reset Password</button>
                            </form>
                        </div>
                        
                        <!-- Recent Activity -->
                        <div class="edit-form">
                            <h3>📋 Recent Activity</h3>
                            <?php if (count($activities) > 0): ?>
                                <?php foreach ($activities as $activity): ?>
                                <div class="activity-item">
                                    <span><?= str_replace('_', ' ', $activity['action']) ?></span>
                                    <span class="time"><?= timeAgo($activity['created_at']) ?></span>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color:#6c757d;font-size:14px;">No recent activity found.</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Danger Zone -->
                        <div class="edit-form" style="border: 2px solid #dc3545;">
                            <h3 style="color:#dc3545;">⚠️ Danger Zone</h3>
                            <p style="color:#6c757d;font-size:14px;margin-bottom:15px;">
                                Deleting this user will permanently remove their account and all associated data.
                                This action cannot be undone.
                            </p>
                            
                            <form method="POST" onsubmit="return confirm('⚠️ Are you sure you want to delete this user? This action cannot be undone!');">
                                <input type="hidden" name="action" value="delete_user">
                                <button type="submit" class="btn btn-danger">🗑️ Delete User</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="../assets/js/app.js"></script>
    <script>
        // Helper function for role badge class
        function getRoleBadgeClass(role) {
            const classes = {
                'admin': 'badge-danger',
                'ranger': 'badge-primary',
                'scout': 'badge-info',
                'tourism': 'badge-warning',
                'zone_supervisor': 'badge-purple'
            };
            return classes[role] || 'badge-secondary';
        }
    </script>
</body>
</html>