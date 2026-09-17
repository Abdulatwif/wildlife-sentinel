<?php
require_once '../includes/functions.php';
requireSupervisor();

$user = getCurrentUser();
$pdo = getDB();
$error = '';
$success = '';

// ============================================================
// HELPER: Validate & normalize phone (Zambia)
// ============================================================
function validateAndNormalizePhone($raw) {
    $raw = trim((string)$raw);
    if ($raw === '') {
        return ['ok' => true, 'phone' => null, 'msg' => ''];
    }
    $normalized = preg_replace('/[^0-9]/', '', $raw);
    if (strpos($normalized, '260') === 0) {
        $normalized = substr($normalized, 3);
    }
    if (!preg_match('/^(09|07)[0-9]{8}$/', $normalized)) {
        return ['ok' => false, 'phone' => null, 'msg' => 'Invalid phone format. Use 0971234567 or 0771234567.'];
    }
    return ['ok' => true, 'phone' => $normalized, 'msg' => ''];
}

// ============================================================
// HELPER: Check if phone already exists (excluding a given user id)
// ============================================================
function phoneExists(PDO $pdo, $phone, $excludeUserId = 0) {
    if (empty($phone)) return false;
    $sql = "SELECT id, full_name FROM users WHERE phone = ?";
    $params = [$phone];
    if ($excludeUserId > 0) {
        $sql .= " AND id != ?";
        $params[] = $excludeUserId;
    }
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: false;
}

// ============================================================
// HELPER: Common-provider spelling check
// ------------------------------------------------------------
// If the domain is a common provider (gmail, yahoo, hotmail,
// outlook, ...), accept it ONLY when it's spelled exactly right.
// Otherwise return the correct spelling so the user can fix it.
// ============================================================
function checkCommonProviderSpelling(string $domain): array {
    $domain = strtolower(trim($domain));

    // Exact spellings that are always accepted
    $canonical = [
        'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
        'live.com', 'icloud.com', 'aol.com', 'protonmail.com'
    ];

    if (in_array($domain, $canonical, true)) {
        return ['ok' => true, 'msg' => ''];
    }

    // Map of known misspellings → correct spelling
    $misspellings = [
        // Gmail
        'gmial.com'  => 'gmail.com',
        'gmai.com'   => 'gmail.com',
        'gamil.com'  => 'gmail.com',
        'gmal.com'   => 'gmail.com',
        'gnail.com'  => 'gmail.com',
        'gmaill.com' => 'gmail.com',
        'gmail.co'   => 'gmail.com',
        'gmail.con'  => 'gmail.com',
        'gmail.cm'   => 'gmail.com',
        'gmail.cim'  => 'gmail.com',
        'gmail.comm' => 'gmail.com',
        'gmailcom'   => 'gmail.com',
        'g-mail.com' => 'gmail.com',
        'gmaill.co'  => 'gmail.com',
        'gmaill.con' => 'gmail.com',

        // Yahoo
        'yaho.com'    => 'yahoo.com',
        'yahooo.com'  => 'yahoo.com',
        'yahoo.co'    => 'yahoo.com',
        'yahho.com'   => 'yahoo.com',
        'yahoo.con'   => 'yahoo.com',

        // Hotmail
        'hotmial.com' => 'hotmail.com',
        'hotmai.com'  => 'hotmail.com',
        'hotmal.com'  => 'hotmail.com',
        'hotmail.co'  => 'hotmail.com',
        'hotmail.con' => 'hotmail.com',

        // Outlook
        'outlok.com'  => 'outlook.com',
        'outloo.com'  => 'outlook.com',
        'outllok.com' => 'outlook.com',
        'outlook.co'  => 'outlook.com',
        'outlook.con' => 'outlook.com',

        // Live
        'live.co'     => 'live.com',
        'live.con'    => 'live.com',

        // iCloud
        'icloud.co'   => 'icloud.com',
        'iclod.com'   => 'icloud.com',
    ];

    if (isset($misspellings[$domain])) {
        return [
            'ok'  => false,
            'msg' => 'The word "<strong>' . htmlspecialchars($domain) . '</strong>" is misspelled. '
                   . 'Please use <strong>' . $misspellings[$domain] . '</strong> instead.'
        ];
    }

    // Not a common provider — let the general validator decide
    return ['ok' => true, 'msg' => ''];
}

// ============================================================
// HELPER: Strict real-world email validator
// ============================================================
function validateRealEmail($email) {
    $email = trim((string)$email);

    if ($email === '') {
        return ['ok' => false, 'msg' => 'Email is required'];
    }
    if (strlen($email) > 254) {
        return ['ok' => false, 'msg' => 'Email address is too long (max 254 chars)'];
    }
    if (strpos($email, ' ') !== false) {
        return ['ok' => false, 'msg' => 'Email cannot contain spaces'];
    }
    if (strpos($email, '..') !== false) {
        return ['ok' => false, 'msg' => 'Email cannot contain consecutive dots'];
    }
    if ($email[0] === '.' || substr($email, -1) === '.') {
        return ['ok' => false, 'msg' => 'Email cannot start or end with a dot'];
    }
    if (substr_count($email, '@') !== 1) {
        return ['ok' => false, 'msg' => 'Please enter a valid email address'];
    }

    list($local, $domain) = explode('@', $email);

    if ($local === '' || strlen($local) > 64) {
        return ['ok' => false, 'msg' => 'Please enter a valid email address'];
    }
    if (!preg_match('/^[A-Za-z0-9._%+\-]+$/', $local)) {
        return ['ok' => false, 'msg' => 'Email contains invalid characters'];
    }
    if ($local[0] === '.' || substr($local, -1) === '.' || strpos($local, '..') !== false) {
        return ['ok' => false, 'msg' => 'Please enter a valid email address'];
    }
    if (!preg_match('/^(?=.{4,253}$)([A-Za-z0-9]([A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/', $domain)) {
        return ['ok' => false, 'msg' => 'Please enter a valid email domain (e.g. example.com)'];
    }
    if (strpos($domain, '..') !== false) {
        return ['ok' => false, 'msg' => 'Please enter a valid email address'];
    }

    // ---- Common provider spelling check (gmail, yahoo, ...) ----
    $spelling = checkCommonProviderSpelling($domain);
    if (!$spelling['ok']) {
        return ['ok' => false, 'msg' => $spelling['msg']];
    }

    // Reject disposable / placeholder domains
    $blocked = [
        'example.com','example.org','example.net','test.com','test.org','test.net',
        'localhost','invalid','mailinator.com','tempmail.com','guerrillamail.com',
        '10minutemail.com','yopmail.com','trashmail.com','sharklasers.com'
    ];
    if (in_array(strtolower($domain), $blocked, true)) {
        return ['ok' => false, 'msg' => 'Please use a real email address (disposable/placeholder domains are not allowed)'];
    }

    return ['ok' => true, 'msg' => ''];
}

// ============================================================
// HANDLE USER ACTIONS
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_user') {
        $email    = sanitize(trim($_POST['email'] ?? ''));
        $fullName = sanitize(trim($_POST['full_name'] ?? ''));
        $phoneRaw = trim($_POST['phone'] ?? '');
        $role     = $_POST['role'] ?? '';
        $zoneId   = $_POST['zone_id'] ?? null;
        $password = $_POST['password'] ?? '';
        
        $errors = [];
        
        // Name
        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        } elseif (mb_strlen($fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters';
        } elseif (mb_strlen($fullName) > 100) {
            $errors[] = 'Full name must not exceed 100 characters';
        } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $fullName)) {
            $errors[] = 'Full name contains invalid characters';
        }
        
        // Email (strict — includes gmail spelling check)
        $emailCheck = validateRealEmail($email);
        if (!$emailCheck['ok']) {
            $errors[] = strip_tags($emailCheck['msg']); // plain text for the top-level alert
        }
        
        // Password
        if (empty($password)) {
            $errors[] = 'Password is required';
        } elseif (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters';
        }
        
        // Role
        $validRoles = ['ranger', 'scout', 'tourism', 'zone_supervisor'];
        if ($user['role'] === 'admin') $validRoles[] = 'admin';
        if (empty($role) || !in_array($role, $validRoles, true)) {
            $errors[] = 'Please select a valid role';
        }
        
        // Zone permissions
        if ($user['role'] === 'zone_supervisor' && $zoneId && $zoneId != $user['zone_id']) {
            $errors[] = 'You can only assign users to your zone';
        }
        if ($user['role'] === 'zone_supervisor' && $role === 'admin') {
            $errors[] = 'You cannot assign admin role';
        }
        
        // Phone (optional but unique)
        $phoneResult = validateAndNormalizePhone($phoneRaw);
        if (!$phoneResult['ok']) {
            $errors[] = $phoneResult['msg'];
        }
        $phone = $phoneResult['phone'];
        
        if (empty($errors) && $phone) {
            $existing = phoneExists($pdo, $phone);
            if ($existing) {
                $errors[] = 'This phone number is already registered to ' . $existing['full_name'] . '. Each phone number can only be used once (it receives SMS alerts).';
            }
        }
        
        if (empty($errors)) {
            $hash = hashPassword($password);
            $stmt = $pdo->prepare("
                INSERT INTO users (email, phone, password_hash, full_name, role, zone_id, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?) RETURNING id
            ");
            try {
                $stmt->execute([$email, $phone ?: null, $hash, $fullName, $role, $zoneId ?: null, $user['id']]);
                $success = 'User created successfully';
                logAudit($user['id'], 'create_user', ['email' => $email, 'role' => $role]);
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    if (stripos($e->getMessage(), 'phone') !== false) {
                        $error = 'This phone number is already registered.';
                    } else {
                        $error = 'Email already exists';
                    }
                } else {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    if ($action === 'update_user') {
        $userId   = (int)($_POST['user_id'] ?? 0);
        $fullName = sanitize(trim($_POST['full_name'] ?? ''));
        $phoneRaw = trim($_POST['phone'] ?? '');
        $role     = $_POST['role'] ?? '';
        $zoneId   = $_POST['zone_id'] ?? null;
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        $email    = sanitize(trim($_POST['email'] ?? ''));
        
        $errors = [];
        
        if (empty($fullName)) {
            $errors[] = 'Full name is required';
        } elseif (mb_strlen($fullName) < 2) {
            $errors[] = 'Full name must be at least 2 characters';
        } elseif (!preg_match("/^[\p{L}\s'\-\.]+$/u", $fullName)) {
            $errors[] = 'Full name contains invalid characters';
        }
        
        $emailCheck = validateRealEmail($email);
        if (!$emailCheck['ok']) {
            $errors[] = strip_tags($emailCheck['msg']);
        }
        
        $validRoles = ['ranger', 'scout', 'tourism', 'zone_supervisor'];
        if ($user['role'] === 'admin') $validRoles[] = 'admin';
        if (empty($role) || !in_array($role, $validRoles, true)) {
            $errors[] = 'Please select a valid role';
        }
        
        if ($user['role'] === 'zone_supervisor' && $role === 'admin') {
            $errors[] = 'You cannot assign admin role';
        }
        if ($user['role'] === 'zone_supervisor' && $zoneId != $user['zone_id']) {
            $errors[] = 'You can only assign users to your zone';
        }
        
        $phoneResult = validateAndNormalizePhone($phoneRaw);
        if (!$phoneResult['ok']) {
            $errors[] = $phoneResult['msg'];
        }
        $phone = $phoneResult['phone'];
        
        if (empty($errors) && $phone) {
            $existing = phoneExists($pdo, $phone, $userId);
            if ($existing) {
                $errors[] = 'This phone number is already registered to ' . $existing['full_name'] . '.';
            }
        }
        
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $error = 'Email already exists for another user';
            } else {
                $sql = "UPDATE users SET full_name = ?, phone = ?, email = ?, role = ?, zone_id = ?, is_active = ? WHERE id = ?";
                $params = [$fullName, $phone ?: null, $email, $role, $zoneId ?: null, $isActive, $userId];
                $stmt = $pdo->prepare($sql);
                try {
                    if ($stmt->execute($params)) {
                        $success = 'User updated successfully';
                        logAudit($user['id'], 'update_user', ['user_id' => $userId]);
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $editUser = $stmt->fetch();
                    } else {
                        $error = 'Failed to update user';
                    }
                } catch (PDOException $e) {
                    if ($e->getCode() == 23000) {
                        if (stripos($e->getMessage(), 'phone') !== false) {
                            $error = 'This phone number is already registered.';
                        } else {
                            $error = 'Email already exists';
                        }
                    } else {
                        $error = 'Database error: ' . $e->getMessage();
                    }
                }
            }
        } else {
            $error = implode('<br>', $errors);
        }
    }
    
    if ($action === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $userToDelete = $stmt->fetch();
        
        if (!$userToDelete) {
            $error = 'User not found';
        } elseif ($userId == $user['id']) {
            $error = 'You cannot delete your own account';
        } elseif ($userToDelete['role'] === 'admin' && $user['role'] !== 'admin') {
            $error = 'You cannot delete an admin account';
        } elseif ($userToDelete['role'] === 'admin' && $user['role'] === 'admin') {
            if (!isset($_POST['confirm_delete']) || $_POST['confirm_delete'] !== 'yes') {
                $error = 'Please confirm deletion of admin account';
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    logAudit($user['id'], 'delete_user', ['user_id' => $userId, 'role' => 'admin']);
                    $success = 'Admin user deleted successfully';
                } else {
                    $error = 'Failed to delete admin user';
                }
            }
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM incidents WHERE reporter_id = ? OR acknowledged_by = ?");
            $stmt->execute([$userId, $userId]);
            $incidentCount = $stmt->fetch()['count'];
            
            if ($incidentCount > 0) {
                if (isset($_POST['reassign_incidents']) && $_POST['reassign_incidents'] === 'yes') {
                    $pdo->beginTransaction();
                    try {
                        $stmt = $pdo->prepare("UPDATE incidents SET reporter_id = NULL WHERE reporter_id = ?");
                        $stmt->execute([$userId]);
                        $stmt = $pdo->prepare("UPDATE incidents SET acknowledged_by = NULL WHERE acknowledged_by = ?");
                        $stmt->execute([$userId]);
                        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $pdo->commit();
                        logAudit($user['id'], 'delete_user_with_incidents', ['user_id' => $userId, 'incident_count' => $incidentCount]);
                        $success = "User deleted successfully. {$incidentCount} incidents were reassigned.";
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $error = 'Failed to delete user: ' . $e->getMessage();
                    }
                } else {
                    $error = "Cannot delete user - they have {$incidentCount} incidents. Choose to reassign incidents or confirm deletion.";
                }
            } else {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                if ($stmt->execute([$userId])) {
                    logAudit($user['id'], 'delete_user', ['user_id' => $userId]);
                    $success = 'User deleted successfully';
                } else {
                    $error = 'Failed to delete user';
                }
            }
        }
    }
}

// ============================================================
// GET USERS
// ============================================================
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$statusFilter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

$sql = "SELECT u.*, z.name as zone_name FROM users u LEFT JOIN zones z ON u.zone_id = z.id WHERE 1=1";
$params = [];

if ($user['role'] === 'zone_supervisor') {
    $sql .= " AND u.zone_id = ? AND u.role != 'admin'";
    $params[] = $user['zone_id'];
}

if ($search) {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($roleFilter) {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter === 'active') {
    $sql .= " AND u.is_active = 1";
} elseif ($statusFilter === 'inactive') {
    $sql .= " AND u.is_active = 0";
}

$sql .= " ORDER BY u.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$zones = $pdo->query("SELECT id, name FROM zones WHERE is_active = 1 ORDER BY name")->fetchAll();

$roles = ['ranger', 'scout', 'tourism', 'zone_supervisor'];
if ($user['role'] === 'admin') $roles[] = 'admin';

$roleStats = [];
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
while ($row = $stmt->fetch()) {
    $roleStats[$row['role']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#0d3b22">
    <title>User Management - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transitions.css">
    <style>
        /* [styles unchanged — see previous message] */
        .stats-row { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 16px; }
        .stat-chip { background: white; padding: 8px 16px; border-radius: 20px; border: 1px solid var(--gray-300); font-size: 13px; display: flex; align-items: center; gap: 6px; }
        .stat-chip .count { font-weight: 700; color: var(--primary); }
        .stat-chip .count.danger { color: #dc3545; }
        .stat-chip .count.warning { color: #ffc107; }
        .stat-chip .count.success { color: #28a745; }
        .stat-chip .count.info { color: #17a2b8; }
        .stat-chip .count.purple { color: #6f42c1; }
        .filter-bar { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: center; background: white; padding: 15px 18px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .filter-bar select, .filter-bar input { padding: 8px 14px; border: 2px solid var(--gray-300); border-radius: 8px; font-size: 14px; background: white; min-height: 42px; }
        .filter-bar select:focus, .filter-bar input:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 4px rgba(26, 92, 58, 0.1); }
        .filter-bar .btn { padding: 8px 20px; min-height: 42px; }
        .filter-bar .search-wrapper { flex: 1; min-width: 150px; }
        .filter-bar .search-wrapper input { width: 100%; }
        .filter-bar .filter-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .section-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 15px; }
        .section-header .title-group { display: flex; align-items: center; gap: 12px; }
        .section-header .title-group .user-count { font-size: 13px; color: var(--gray-500); font-weight: 400; }
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; margin: 0 -4px; padding: 0 4px; }
        .user-table { width: 100%; border-collapse: collapse; font-size: 14px; }
        .user-table th { text-align: left; padding: 10px 12px; background: var(--gray-100); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: var(--gray-700); border-bottom: 2px solid var(--gray-300); white-space: nowrap; }
        .user-table td { padding: 10px 12px; border-bottom: 1px solid var(--gray-200); vertical-align: middle; }
        .user-table tr:hover td { background: var(--gray-100); }
        .user-table .user-name { font-weight: 600; color: var(--gray-800); }
        .user-table .user-email { color: var(--gray-600); font-size: 13px; }
        .role-badge { display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
        .role-badge.admin { background: #dc3545; color: white; }
        .role-badge.ranger { background: #007bff; color: white; }
        .role-badge.scout { background: #17a2b8; color: white; }
        .role-badge.tourism { background: #ffc107; color: #333; }
        .role-badge.zone_supervisor { background: #6f42c1; color: white; }
        .status-badge { display: inline-block; padding: 2px 12px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .status-badge.active { background: #d4edda; color: #155724; }
        .status-badge.inactive { background: #f8d7da; color: #721c24; }
        .action-buttons { display: flex; gap: 6px; flex-wrap: wrap; justify-content: center; }
        .btn-small { padding: 4px 12px; font-size: 12px; min-height: 32px; border-radius: 6px; cursor: pointer; }
        .btn-small.btn-danger { background: #dc3545; color: white; }
        .btn-small.btn-danger:hover { background: #c62828; }
        .btn-small.btn-primary { background: #1a5c3a; color: white; }
        .btn-small.btn-primary:hover { background: #0d3b22; }
        .modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); }
        .modal.show { display: flex; }
        .modal-content { background: white; padding: 30px; border-radius: 16px; max-width: 500px; width: 100%; max-height: 90vh; overflow-y: auto; animation: modalSlideIn 0.3s ease; }
        @keyframes modalSlideIn { from { opacity: 0; transform: scale(0.95) translateY(-20px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .modal-header h3 { font-size: 20px; color: #0d3b22; }
        .modal-header .close { font-size: 28px; background: none; border: none; cursor: pointer; padding: 4px 8px; color: var(--gray-500); transition: all 0.3s; }
        .modal-header .close:hover { color: var(--danger); transform: rotate(90deg); }
        .modal-footer { display: flex; gap: 10px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--gray-200); }
        .modal-footer .btn { flex: 1; justify-content: center; min-height: 44px; }
        .modal-body .form-group { margin-bottom: 15px; }
        .modal-body .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #495057; }
        .modal-body .form-control { width: 100%; padding: 10px 14px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 14px; transition: all 0.3s; }
        .modal-body .form-control:focus { border-color: #1a5c3a; outline: none; box-shadow: 0 0 0 4px rgba(26, 92, 58, 0.1); }
        .modal-body .form-control:disabled { background: #f8f9fa; cursor: not-allowed; }
        .modal-body .checkbox-group { display: flex; align-items: center; gap: 10px; padding: 8px 0; }
        .modal-body .checkbox-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; }
        .empty-state { text-align: center; padding: 60px 20px; color: var(--gray-500); }
        .empty-state .empty-icon { font-size: 56px; margin-bottom: 12px; }
        .empty-state h3 { color: var(--gray-700); margin-bottom: 4px; }
        .field-wrap { position: relative; }
        .form-control.is-valid { border-color: #28a745 !important; background: #f0fff4 !important; }
        .form-control.is-invalid { border-color: #dc3545 !important; background: #fff5f5 !important; }
        .form-control.is-invalid:focus { box-shadow: 0 0 0 4px rgba(220,53,69,0.12) !important; }
        .field-status { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; pointer-events: none; opacity: 0; transition: opacity 0.2s; }
        .field-status.show { opacity: 1; }
        .field-status.valid   { color: #28a745; }
        .field-status.invalid { color: #dc3545; }
        .field-hint { font-size: 11.5px; color: var(--gray-500); margin-top: 4px; line-height: 1.5; }
        .field-hint.ok   { color: #28a745; }
        .field-hint.err  { color: #dc3545; }
        .field-hint.checking { color: #6c757d; font-style: italic; }
        .pw-strength { display: none; margin-top: 6px; }
        .pw-strength.show { display: block; }
        .pw-strength-bar { height: 5px; background: #e9ecef; border-radius: 3px; overflow: hidden; margin-bottom: 4px; }
        .pw-strength-fill { height: 100%; width: 0%; border-radius: 3px; background: #dc3545; transition: width 0.3s ease, background 0.3s ease; }
        .pw-strength-text { font-size: 11px; color: var(--gray-500); font-weight: 500; }
        @media (max-width: 1024px) { .user-table { font-size: 13px; } .user-table th, .user-table td { padding: 8px 10px; } }
        @media (max-width: 768px) {
            .filter-bar { flex-direction: column; align-items: stretch; padding: 12px 14px; }
            .filter-bar .search-wrapper { min-width: 100%; }
            .filter-bar select, .filter-bar input { width: 100%; font-size: 16px; }
            .filter-bar .btn { width: 100%; justify-content: center; }
            .filter-bar .filter-actions { display: flex; gap: 8px; }
            .filter-bar .filter-actions .btn { flex: 1; }
            .stats-row { gap: 8px; }
            .stat-chip { font-size: 12px; padding: 6px 12px; }
            .section-header { flex-direction: column; align-items: stretch; }
            .section-header .btn { width: 100%; justify-content: center; }
            .user-table { font-size: 12px; }
            .user-table th, .user-table td { padding: 6px 8px; }
            .user-table .user-email { font-size: 11px; }
            .role-badge { font-size: 10px; padding: 2px 8px; }
            .action-buttons .btn-small { font-size: 11px; padding: 3px 8px; min-height: 28px; }
            .modal-content { padding: 20px; margin: 10px; }
            .modal-footer { flex-direction: column; }
        }
        @media (max-width: 480px) {
            .stats-row { gap: 6px; }
            .stat-chip { font-size: 10px; padding: 4px 10px; }
            .stat-chip .count { font-size: 12px; }
            .user-table { font-size: 11px; }
            .user-table th, .user-table td { padding: 4px 6px; }
            .user-table .user-name { font-size: 12px; }
            .user-table .user-email { font-size: 10px; }
            .role-badge { font-size: 9px; padding: 1px 6px; }
            .action-buttons { flex-direction: column; gap: 4px; }
            .action-buttons .btn-small { width: 100%; justify-content: center; min-height: 30px; font-size: 10px; }
            .modal-content { padding: 16px; }
            .modal-header h3 { font-size: 17px; }
        }
        .table-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        @supports (-webkit-touch-callout: none) { .modal { backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px); } }
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
                
                <div class="stats-row">
                    <span class="stat-chip">👥 Total: <span class="count"><?= count($users) ?></span></span>
                    <?php if (isset($roleStats['admin'])): ?>
                    <span class="stat-chip">👑 Admin: <span class="count danger"><?= $roleStats['admin'] ?></span></span>
                    <?php endif; ?>
                    <?php if (isset($roleStats['ranger'])): ?>
                    <span class="stat-chip">👤 Rangers: <span class="count info"><?= $roleStats['ranger'] ?></span></span>
                    <?php endif; ?>
                    <?php if (isset($roleStats['zone_supervisor'])): ?>
                    <span class="stat-chip">🏛️ Supervisors: <span class="count purple"><?= $roleStats['zone_supervisor'] ?></span></span>
                    <?php endif; ?>
                    <?php if (isset($roleStats['scout'])): ?>
                    <span class="stat-chip">🔍 Scouts: <span class="count success"><?= $roleStats['scout'] ?></span></span>
                    <?php endif; ?>
                    <?php if (isset($roleStats['tourism'])): ?>
                    <span class="stat-chip">🌍 Tourism: <span class="count warning"><?= $roleStats['tourism'] ?></span></span>
                    <?php endif; ?>
                </div>
                
                <div class="filter-bar">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
                        <div class="search-wrapper">
                            <input type="text" name="search" placeholder="Search users..." value="<?= $search ?>" class="form-control">
                        </div>
                        <select name="role">
                            <option value="">All Roles</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="ranger" <?= $roleFilter === 'ranger' ? 'selected' : '' ?>>Ranger</option>
                            <option value="zone_supervisor" <?= $roleFilter === 'zone_supervisor' ? 'selected' : '' ?>>Supervisor</option>
                            <option value="scout" <?= $roleFilter === 'scout' ? 'selected' : '' ?>>Scout</option>
                            <option value="tourism" <?= $roleFilter === 'tourism' ? 'selected' : '' ?>>Tourism</option>
                        </select>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="users.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <div class="section">
                    <div class="section-header">
                        <div class="title-group">
                            <h2>All Users</h2>
                            <span class="user-count">Showing <?= count($users) ?> users</span>
                        </div>
                        <button class="btn btn-primary" onclick="showCreateModal()">+ Add User</button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="user-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>Role</th>
                                    <th>Zone</th>
                                    <th>Status</th>
                                    <th style="text-align:center;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>
                                            <div class="user-name"><?= htmlspecialchars($u['full_name']) ?></div>
                                            <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                        </td>
                                        <td>
                                            <div style="font-size:13px;color:var(--gray-600);"><?= htmlspecialchars($u['phone'] ?? 'No phone') ?></div>
                                            <div style="font-size:11px;color:var(--gray-400);">ID: #<?= $u['id'] ?></div>
                                        </td>
                                        <td><span class="role-badge <?= $u['role'] ?>"><?= str_replace('_', ' ', $u['role']) ?></span></td>
                                        <td><?= htmlspecialchars($u['zone_name'] ?? 'N/A') ?></td>
                                        <td><span class="status-badge <?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td style="text-align:center;">
                                            <div class="action-buttons" style="justify-content:center;">
                                                <button class="btn-small btn-primary" onclick="editUser(<?= $u['id'] ?>)">✏️ Edit</button>
                                                <?php if ($u['id'] != $user['id']): ?>
                                                <button class="btn-small btn-danger" onclick="deleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['full_name'])) ?>', '<?= $u['role'] ?>')">🗑️ Delete</button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="6">
                                        <div class="empty-state">
                                            <div class="empty-icon">👤</div>
                                            <h3>No Users Found</h3>
                                            <p>Try adjusting your search or filter criteria.</p>
                                            <button class="btn btn-primary" onclick="showCreateModal()" style="margin-top:10px;">+ Add User</button>
                                        </div>
                                    </td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- ============================================================
         CREATE USER MODAL  — always validates live
         ============================================================ -->
    <div id="createModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>➕ Create New User</h3>
                <button class="close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form method="POST" id="createUserForm" novalidate autocomplete="off">
                <input type="hidden" name="action" value="create_user">
                
                <div class="form-group">
                    <label for="create_full_name">Full Name *</label>
                    <div class="field-wrap">
                        <input type="text" name="full_name" id="create_full_name" class="form-control"
                               placeholder="Enter full name" required minlength="2" maxlength="100"
                               autocomplete="name">
                        <span class="field-status" id="create_full_name_status"></span>
                    </div>
                    <div class="field-hint" id="create_full_name_hint"></div>
                </div>
                
                <div class="form-group">
                    <label for="create_email">Email Address *</label>
                    <div class="field-wrap">
                        <input type="email" name="email" id="create_email" class="form-control"
                               placeholder="user@gmail.com" required maxlength="254"
                               autocomplete="email">
                        <span class="field-status" id="create_email_status"></span>
                    </div>
                    <div class="field-hint" id="create_email_hint">
                        Must be a real address. Common providers like <b>gmail.com</b> must be spelt correctly.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="create_phone">Phone Number</label>
                    <div class="field-wrap">
                        <input type="tel" name="phone" id="create_phone" class="form-control"
                               placeholder="e.g. 0971234567" maxlength="15"
                               autocomplete="tel" inputmode="tel">
                        <span class="field-status" id="create_phone_status"></span>
                    </div>
                    <div class="field-hint" id="create_phone_hint">
                        Used for SMS alerts. Must be unique — one phone per user.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="create_password">Password *</label>
                    <div class="field-wrap">
                        <input type="password" name="password" id="create_password" class="form-control"
                               placeholder="Enter password (min 6 characters)" required minlength="6"
                               autocomplete="new-password">
                        <span class="field-status" id="create_password_status"></span>
                        <button type="button" class="toggle-password"
                                onclick="togglePassword('create_password', this)"
                                aria-label="Show password"
                                style="position:absolute;right:12px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;font-size:16px;padding:4px;">
                            👁️
                        </button>
                    </div>
                    <div class="pw-strength" id="create_pw_strength">
                        <div class="pw-strength-bar">
                            <div class="pw-strength-fill" id="create_pw_strength_fill"></div>
                        </div>
                        <div class="pw-strength-text" id="create_pw_strength_text">Enter a password</div>
                    </div>
                    <div class="field-hint" id="create_password_hint"></div>
                </div>
                
                <div class="form-group">
                    <label for="create_role">Role *</label>
                    <select name="role" id="create_role" class="form-control" required>
                        <option value="">Select Role</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>"><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="field-hint" id="create_role_hint"></div>
                </div>
                
                <div class="form-group">
                    <label for="create_zone">Zone</label>
                    <select name="zone_id" id="create_zone" class="form-control">
                        <option value="">No Zone</option>
                        <?php foreach ($zones as $zone): ?>
                        <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="createSubmit">Create User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>✏️ Edit User</h3>
                <button class="close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form method="POST" id="editUserForm" novalidate autocomplete="off">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" id="edit_user_id">
                
                <div class="form-group">
                    <label for="edit_full_name">Full Name *</label>
                    <div class="field-wrap">
                        <input type="text" name="full_name" id="edit_full_name" class="form-control"
                               required minlength="2" maxlength="100">
                        <span class="field-status" id="edit_full_name_status"></span>
                    </div>
                    <div class="field-hint" id="edit_full_name_hint"></div>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email Address *</label>
                    <div class="field-wrap">
                        <input type="email" name="email" id="edit_email" class="form-control"
                               required maxlength="254">
                        <span class="field-status" id="edit_email_status"></span>
                    </div>
                    <div class="field-hint" id="edit_email_hint"></div>
                </div>
                
                <div class="form-group">
                    <label for="edit_phone">Phone Number</label>
                    <div class="field-wrap">
                        <input type="tel" name="phone" id="edit_phone" class="form-control"
                               maxlength="15" inputmode="tel">
                        <span class="field-status" id="edit_phone_status"></span>
                    </div>
                    <div class="field-hint" id="edit_phone_hint">
                        Used for SMS alerts. Must be unique — one phone per user.
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Role *</label>
                    <select name="role" id="edit_role" class="form-control" required>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?= $role ?>"><?= ucfirst(str_replace('_', ' ', $role)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_zone">Zone</label>
                    <select name="zone_id" id="edit_zone" class="form-control">
                        <option value="">No Zone</option>
                        <?php foreach ($zones as $zone): ?>
                        <option value="<?= $zone['id'] ?>"><?= htmlspecialchars($zone['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="edit_active" value="1" checked>
                    <label style="margin:0;" for="edit_active">Active Account</label>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="editSubmit">Update User</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- DELETE MODAL -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🗑️ Delete User</h3>
                <button class="close" onclick="closeModal('deleteModal')">&times;</button>
            </div>
            <form method="POST" id="deleteUserForm">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete_user_id">
                <input type="hidden" name="confirm_delete" value="yes">
                <div class="modal-body">
                    <div style="text-align:center;margin-bottom:15px;">
                        <span style="font-size:48px;display:block;margin-bottom:10px;">⚠️</span>
                        <h4 style="color:#dc3545;">Are you sure you want to delete this user?</h4>
                    </div>
                    <div style="background:#f8f9fa;padding:15px;border-radius:10px;margin-bottom:15px;">
                        <p id="delete_user_name" style="font-weight:600;font-size:16px;margin:0;"></p>
                        <p id="delete_user_role" style="color:#6c757d;font-size:13px;margin:4px 0 0;"></p>
                    </div>
                    <div style="background:#fff3cd;padding:12px 16px;border-radius:8px;border-left:4px solid #ffc107;margin-bottom:15px;">
                        <p style="margin:0;font-size:13px;color:#856404;"><strong>⚠️ Warning:</strong> This action cannot be undone. All user data will be permanently removed.</p>
                    </div>
                    <div id="incident_warning" style="display:none;background:#f8d7da;padding:12px 16px;border-radius:8px;border-left:4px solid #dc3545;margin-bottom:15px;">
                        <p style="margin:0;font-size:13px;color:#721c24;"><strong>📋 This user has incidents:</strong> <span id="incident_count"></span> incidents assigned to this user.</p>
                        <div style="margin-top:8px;">
                            <label style="font-size:13px;cursor:pointer;">
                                <input type="checkbox" name="reassign_incidents" value="yes" checked>
                                Reassign incidents (set to NULL)
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">🗑️ Yes, Delete User</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/transitions.js"></script>
    <script>
        // ============================================================
        // MODAL HELPERS
        // ============================================================
        function showCreateModal() {
            document.getElementById('createModal').classList.add('show');
            document.body.style.overflow = 'hidden';
            const form = document.getElementById('createUserForm');
            form.reset();
            form.querySelectorAll('.form-control').forEach(el => el.classList.remove('is-valid', 'is-invalid'));
            form.querySelectorAll('.field-status').forEach(el => { el.classList.remove('show', 'valid', 'invalid'); el.textContent = ''; });
            form.querySelectorAll('.field-hint').forEach(el => { el.classList.remove('ok', 'err', 'checking'); el.textContent = ''; });
            const pwStrength = document.getElementById('create_pw_strength');
            if (pwStrength) pwStrength.classList.remove('show');
            const submit = document.getElementById('createSubmit');
            if (submit) { submit.disabled = false; submit.textContent = 'Create User'; }
            // Re-set the email hint default text
            const emailHint = document.getElementById('create_email_hint');
            if (emailHint) emailHint.textContent = 'Must be a real address. Common providers like gmail.com must be spelt correctly.';
            setTimeout(() => { const first = document.getElementById('create_full_name'); if (first) first.focus(); }, 150);
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            document.body.style.overflow = '';
        }
        
        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') { input.type = 'text'; btn.textContent = '🙈'; }
            else { input.type = 'password'; btn.textContent = '👁️'; }
        }
        
        function deleteUser(id, name, role) {
            document.getElementById('delete_user_id').value = id;
            document.getElementById('delete_user_name').textContent = '👤 ' + name;
            document.getElementById('delete_user_role').textContent = 'Role: ' + role.charAt(0).toUpperCase() + role.slice(1);
            fetch('../api/users.php?action=check_incidents&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.incident_count > 0) {
                        document.getElementById('incident_warning').style.display = 'block';
                        document.getElementById('incident_count').textContent = data.incident_count;
                    } else {
                        document.getElementById('incident_warning').style.display = 'none';
                    }
                })
                .catch(() => { document.getElementById('incident_warning').style.display = 'none'; });
            document.getElementById('deleteModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function editUser(id) {
            fetch('../api/users.php?action=get&id=' + id)
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.user) {
                        const u = data.user;
                        document.getElementById('edit_user_id').value = u.id;
                        document.getElementById('edit_full_name').value = u.full_name;
                        document.getElementById('edit_email').value = u.email;
                        document.getElementById('edit_phone').value = u.phone || '';
                        document.getElementById('edit_role').value = u.role;
                        document.getElementById('edit_zone').value = u.zone_id || '';
                        document.getElementById('edit_active').checked = u.is_active == 1;
                        document.querySelectorAll('#editUserForm .form-control').forEach(el => el.classList.remove('is-valid', 'is-invalid'));
                        document.querySelectorAll('#editUserForm .field-status').forEach(el => { el.classList.remove('show', 'valid', 'invalid'); el.textContent = ''; });
                        document.querySelectorAll('#editUserForm .field-hint').forEach(el => { el.classList.remove('ok', 'err', 'checking'); el.textContent = ''; });
                        document.getElementById('editModal').classList.add('show');
                        document.body.style.overflow = 'hidden';
                    } else {
                        alert('❌ Error loading user data');
                    }
                })
                .catch(error => alert('❌ Network error: ' + error));
        }
        
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(function(modal) {
                    modal.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'n') { e.preventDefault(); showCreateModal(); }
            if (e.ctrlKey && e.key === 'f') {
                e.preventDefault();
                const searchInput = document.querySelector('input[name="search"]');
                if (searchInput) { searchInput.focus(); searchInput.select(); }
            }
        });
        
        // ============================================================
        // VALIDATION HELPERS
        // ============================================================
        const EMAIL_RE = /^(?=.{6,254}$)[A-Za-z0-9](?:[A-Za-z0-9._%+\-]{0,62}[A-Za-z0-9])?@(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/;
        const PHONE_RE = /^(09|07)[0-9]{8}$/;
        const NAME_RE  = /^[\p{L}\s'\-\.]+$/u;

        // ---- Common-provider spelling (gmail, yahoo, ...) ----
        const COMMON_PROVIDERS_OK = new Set([
            'gmail.com','yahoo.com','hotmail.com','outlook.com','live.com','icloud.com','aol.com','protonmail.com'
        ]);

        const COMMON_PROVIDER_TYPOS = {
            // Gmail
            'gmial.com':'gmail.com', 'gmai.com':'gmail.com', 'gamil.com':'gmail.com',
            'gmal.com':'gmail.com',  'gnail.com':'gmail.com', 'gmaill.com':'gmail.com',
            'gmail.co':'gmail.com',  'gmail.con':'gmail.com', 'gmail.cm':'gmail.com',
            'gmail.cim':'gmail.com', 'gmail.comm':'gmail.com', 'gmailcom':'gmail.com',
            'g-mail.com':'gmail.com', 'gmaill.co':'gmail.com', 'gmaill.con':'gmail.com',
            // Yahoo
            'yaho.com':'yahoo.com', 'yahooo.com':'yahoo.com', 'yahoo.co':'yahoo.com',
            'yahho.com':'yahoo.com', 'yahoo.con':'yahoo.com',
            // Hotmail
            'hotmial.com':'hotmail.com', 'hotmai.com':'hotmail.com',
            'hotmal.com':'hotmail.com', 'hotmail.co':'hotmail.com', 'hotmail.con':'hotmail.com',
            // Outlook
            'outlok.com':'outlook.com', 'outloo.com':'outlook.com',
            'outllok.com':'outlook.com', 'outlook.co':'outlook.com', 'outlook.con':'outlook.com',
            // Live
            'live.co':'live.com', 'live.con':'live.com',
            // iCloud
            'icloud.co':'icloud.com', 'iclod.com':'icloud.com',
        };

        function checkCommonProviderSpelling(domain) {
            const d = (domain || '').toLowerCase().trim();
            if (COMMON_PROVIDERS_OK.has(d)) return { ok: true, msg: '' };
            if (COMMON_PROVIDER_TYPOS[d]) {
                return {
                    ok: false,
                    msg: '❌ The word <strong>' + d + '</strong> is misspelled. Please use <strong>'
                       + COMMON_PROVIDER_TYPOS[d] + '</strong> instead.'
                };
            }
            return { ok: true, msg: '' };
        }

        const EMAIL_BLOCKED_DOMAINS = new Set([
            'example.com','example.org','example.net','test.com','test.org','test.net',
            'localhost','invalid','mailinator.com','tempmail.com','guerrillamail.com',
            '10minutemail.com','yopmail.com','trashmail.com','sharklasers.com'
        ]);
        
        function setFieldState(inputEl, statusEl, hintEl, state, message, isHtml) {
            inputEl.classList.remove('is-valid', 'is-invalid');
            if (statusEl) statusEl.classList.remove('show', 'valid', 'invalid');
            if (hintEl) hintEl.classList.remove('ok', 'err', 'checking');
            
            if (state === 'valid') {
                inputEl.classList.add('is-valid');
                inputEl.setAttribute('aria-invalid', 'false');
                if (statusEl) { statusEl.textContent = '✓'; statusEl.classList.add('show', 'valid'); }
                if (hintEl && message) { if (isHtml) hintEl.innerHTML = message; else hintEl.textContent = message; hintEl.classList.add('ok'); }
            } else if (state === 'invalid') {
                inputEl.classList.add('is-invalid');
                inputEl.setAttribute('aria-invalid', 'true');
                if (statusEl) { statusEl.textContent = '✕'; statusEl.classList.add('show', 'invalid'); }
                if (hintEl && message) { if (isHtml) hintEl.innerHTML = message; else hintEl.textContent = message; hintEl.classList.add('err'); }
            } else if (state === 'checking') {
                if (hintEl && message) { hintEl.innerHTML = message; hintEl.classList.add('checking'); }
            } else {
                inputEl.setAttribute('aria-invalid', 'false');
                if (hintEl && message) hintEl.textContent = message;
            }
        }
        
        function validateFullName(value) {
            const v = (value || '').trim();
            if (!v) return { ok: false, msg: 'Full name is required' };
            if (v.length < 2) return { ok: false, msg: 'Must be at least 2 characters' };
            if (v.length > 100) return { ok: false, msg: 'Must not exceed 100 characters' };
            if (!NAME_RE.test(v)) return { ok: false, msg: 'Only letters, spaces, hyphens and apostrophes allowed' };
            return { ok: true, msg: 'Looks good' };
        }

        function validateEmail(value) {
            const v = (value || '').trim();

            if (!v) return { ok: false, msg: 'Email is required' };
            if (v.length > 254) return { ok: false, msg: 'Email address is too long (max 254 chars)' };
            if (v.indexOf(' ') !== -1) return { ok: false, msg: 'Email cannot contain spaces' };
            if (v.indexOf('..') !== -1) return { ok: false, msg: 'Email cannot contain consecutive dots' };
            if (v[0] === '.' || v[v.length - 1] === '.') return { ok: false, msg: 'Email cannot start or end with a dot' };

            const atCount = (v.match(/@/g) || []).length;
            if (atCount !== 1) return { ok: false, msg: 'Please enter a valid email address' };

            const parts = v.split('@');
            const local = parts[0];
            const domain = parts[1];

            if (local.length === 0 || local.length > 64) return { ok: false, msg: 'Please enter a valid email address' };
            if (!/^[A-Za-z0-9._%+\-]+$/.test(local)) return { ok: false, msg: 'Email contains invalid characters' };
            if (local[0] === '.' || local[local.length - 1] === '.' || local.indexOf('..') !== -1) return { ok: false, msg: 'Please enter a valid email address' };

            const d = domain.toLowerCase();
            if (!/^(?:[A-Za-z0-9](?:[A-Za-z0-9\-]{0,61}[A-Za-z0-9])?\.)+[A-Za-z]{2,63}$/.test(domain)) {
                return { ok: false, msg: 'Please enter a valid email domain (e.g. example.com)' };
            }

            // ⭐ NEW: strict common-provider spelling check (gmail → gmail.com)
            const spelling = checkCommonProviderSpelling(d);
            if (!spelling.ok) return { ok: false, msg: spelling.msg };

            if (EMAIL_BLOCKED_DOMAINS.has(d)) {
                return { ok: false, msg: 'Please use a real email address (disposable/placeholder domains are not allowed)' };
            }

            if (!EMAIL_RE.test(v)) return { ok: false, msg: 'Please enter a valid email address' };

            return { ok: true, msg: 'Looks good' };
        }
        
        function normalizePhone(value) {
            const v = (value || '').trim();
            if (!v) return '';
            let n = v.replace(/[^0-9]/g, '');
            if (n.indexOf('260') === 0) n = n.substring(3);
            return n;
        }
        
        function validatePhoneFormat(value) {
            const v = (value || '').trim();
            if (!v) return { ok: true, msg: 'Optional — used for SMS alerts. Must be unique.' };
            const n = normalizePhone(v);
            if (!PHONE_RE.test(n)) return { ok: false, msg: 'Use 0971234567 or 0771234567 (or leave blank)' };
            return { ok: true, msg: 'Format OK' };
        }
        
        const phoneCheckTimers = {};
        function checkPhoneUnique(inputEl, statusEl, hintEl, excludeUserId) {
            const raw = inputEl.value.trim();
            const n = normalizePhone(raw);
            if (!raw) { setFieldState(inputEl, statusEl, hintEl, 'neutral', 'Optional — used for SMS alerts. Must be unique.'); return Promise.resolve(true); }
            const fmt = validatePhoneFormat(raw);
            if (!fmt.ok) { setFieldState(inputEl, statusEl, hintEl, 'invalid', fmt.msg); return Promise.resolve(false); }
            const key = inputEl.id;
            if (phoneCheckTimers[key]) clearTimeout(phoneCheckTimers[key]);
            return new Promise(resolve => {
                phoneCheckTimers[key] = setTimeout(() => {
                    setFieldState(inputEl, statusEl, hintEl, 'checking', '⏳ Checking availability...');
                    let url = '../api/users.php?action=check_phone&phone=' + encodeURIComponent(n);
                    if (excludeUserId) url += '&exclude_id=' + excludeUserId;
                    fetch(url).then(r => r.json()).then(data => {
                        if (data.exists) {
                            setFieldState(inputEl, statusEl, hintEl, 'invalid', '❌ This phone is already registered to <strong>' + (data.full_name || 'another user') + '</strong>');
                            resolve(false);
                        } else {
                            setFieldState(inputEl, statusEl, hintEl, 'valid', '✅ Phone number is available');
                            resolve(true);
                        }
                    }).catch(() => {
                        setFieldState(inputEl, statusEl, hintEl, 'invalid', '⚠️ Could not verify phone availability. Try again.');
                        resolve(false);
                    });
                }, 500);
            });
        }
        
        function validatePassword(value, minLen) {
            const v = value || '';
            minLen = minLen || 6;
            if (!v) return { ok: false, msg: 'Password is required' };
            if (v.length < minLen) return { ok: false, msg: 'Must be at least ' + minLen + ' characters' };
            return { ok: true, msg: 'Looks good' };
        }
        
        function updatePasswordStrength(inputId, wrapId, fillId, textId) {
            const input = document.getElementById(inputId);
            const wrap = document.getElementById(wrapId);
            const fill = document.getElementById(fillId);
            const text = document.getElementById(textId);
            if (!input || !wrap || !fill || !text) return;
            const v = input.value || '';
            if (!v) { wrap.classList.remove('show'); return; }
            wrap.classList.add('show');
            const checks = { length: v.length >= 8, upper: /[A-Z]/.test(v), lower: /[a-z]/.test(v), number: /[0-9]/.test(v) };
            let score = 0;
            Object.values(checks).forEach(c => { if (c) score++; });
            const pct = (score / 4) * 100;
            fill.style.width = pct + '%';
            let color, label;
            if (score <= 1) { color = '#dc3545'; label = 'Very weak'; }
            else if (score <= 2) { color = '#fd7e14'; label = 'Weak'; }
            else if (score <= 3) { color = '#ffc107'; label = 'Fair'; }
            else { color = '#28a745'; label = 'Strong'; }
            fill.style.background = color;
            text.textContent = label;
            text.style.color = color;
        }
        
        function formatPhoneInput(el) {
            let v = el.value.replace(/[^\d+\s\-()]/g, '');
            if (/^260\d{0,9}$/.test(v)) v = '+' + v;
            el.value = v;
        }
        
        // ============================================================
        // CREATE FORM VALIDATION
        // ------------------------------------------------------------
        // The email field is validated live on every keystroke. If the
        // user types a common provider (gmail, yahoo, hotmail, ...)
        // with a misspelling, the field turns red and the submit is
        // blocked until they fix it.
        // ============================================================
        (function () {
            const form = document.getElementById('createUserForm');
            if (!form) return;
            const fullName = document.getElementById('create_full_name');
            const email    = document.getElementById('create_email');
            const phone    = document.getElementById('create_phone');
            const password = document.getElementById('create_password');
            const role     = document.getElementById('create_role');
            const submit   = document.getElementById('createSubmit');
            const s = {
                fullNameStatus: document.getElementById('create_full_name_status'),
                fullNameHint:   document.getElementById('create_full_name_hint'),
                emailStatus:    document.getElementById('create_email_status'),
                emailHint:      document.getElementById('create_email_hint'),
                phoneStatus:    document.getElementById('create_phone_status'),
                phoneHint:      document.getElementById('create_phone_hint'),
                pwStatus:       document.getElementById('create_password_status'),
                pwHint:         document.getElementById('create_password_hint'),
                roleHint:       document.getElementById('create_role_hint'),
            };
            function runFullName() { const r = validateFullName(fullName.value); setFieldState(fullName, s.fullNameStatus, s.fullNameHint, r.ok ? 'valid' : (fullName.value ? 'invalid' : 'neutral'), fullName.value ? r.msg : ''); return r.ok; }
            function runEmail() {
                const r = validateEmail(email.value);
                if (!email.value) {
                    setFieldState(email, s.emailStatus, s.emailHint, 'neutral', 'Must be a real address. Common providers like gmail.com must be spelt correctly.');
                } else {
                    setFieldState(email, s.emailStatus, s.emailHint, r.ok ? 'valid' : 'invalid', r.msg, true);
                }
                return r.ok;
            }
            function runPassword() { updatePasswordStrength('create_password', 'create_pw_strength', 'create_pw_strength_fill', 'create_pw_strength_text'); const r = validatePassword(password.value, 6); setFieldState(password, s.pwStatus, s.pwHint, r.ok ? 'valid' : (password.value ? 'invalid' : 'neutral'), password.value ? r.msg : ''); return r.ok; }
            function runRole() { const v = role.value; const ok = !!v; if (s.roleHint) { s.roleHint.textContent = ok ? 'Selected: ' + v : ''; s.roleHint.classList.remove('ok', 'err'); if (ok) s.roleHint.classList.add('ok'); } return ok; }

            // Live wiring
            fullName.addEventListener('input', runFullName);
            fullName.addEventListener('blur',  runFullName);
            email.addEventListener('input', runEmail);
            email.addEventListener('blur',  runEmail);
            password.addEventListener('input', runPassword);
            password.addEventListener('blur',  runPassword);
            role.addEventListener('change', runRole);
            phone.addEventListener('input', function () {
                formatPhoneInput(this);
                const fmt = validatePhoneFormat(this.value);
                setFieldState(phone, s.phoneStatus, s.phoneHint, fmt.ok ? (this.value ? 'valid' : 'neutral') : 'invalid', this.value ? fmt.msg : 'Optional — used for SMS alerts. Must be unique.');
            });
            phone.addEventListener('blur', function () { checkPhoneUnique(phone, s.phoneStatus, s.phoneHint, 0); });
            phone.addEventListener('paste', function () { setTimeout(() => { formatPhoneInput(phone); checkPhoneUnique(phone, s.phoneStatus, s.phoneHint, 0); }, 0); });

            // Submit gate — always runs every validator
            form.addEventListener('submit', async function (e) {
                e.preventDefault();

                const results = await Promise.all([
                    Promise.resolve(runFullName()),
                    Promise.resolve(runEmail()),
                    Promise.resolve(runPassword()),
                    Promise.resolve(runRole()),
                    checkPhoneUnique(phone, s.phoneStatus, s.phoneHint, 0)
                ]);

                if (!results.every(Boolean)) {
                    const firstInvalid = form.querySelector('.form-control.is-invalid') || role;
                    if (firstInvalid) {
                        firstInvalid.focus();
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }

                submit.disabled = true;
                submit.textContent = '⏳ Creating...';
                form.submit();
            });
        })();
        
        // ============================================================
        // EDIT FORM VALIDATION
        // ============================================================
        (function () {
            const form = document.getElementById('editUserForm');
            if (!form) return;
            const fullName = document.getElementById('edit_full_name');
            const email    = document.getElementById('edit_email');
            const phone    = document.getElementById('edit_phone');
            const submit   = document.getElementById('editSubmit');
            const userId   = document.getElementById('edit_user_id');
            const s = {
                fullNameStatus: document.getElementById('edit_full_name_status'),
                fullNameHint:   document.getElementById('edit_full_name_hint'),
                emailStatus:    document.getElementById('edit_email_status'),
                emailHint:      document.getElementById('edit_email_hint'),
                phoneStatus:    document.getElementById('edit_phone_status'),
                phoneHint:      document.getElementById('edit_phone_hint'),
            };
            function runFullName() { const r = validateFullName(fullName.value); setFieldState(fullName, s.fullNameStatus, s.fullNameHint, r.ok ? 'valid' : (fullName.value ? 'invalid' : 'neutral'), fullName.value ? r.msg : ''); return r.ok; }
            function runEmail() { const r = validateEmail(email.value); setFieldState(email, s.emailStatus, s.emailHint, r.ok ? 'valid' : (email.value ? 'invalid' : 'neutral'), email.value ? r.msg : '', true); return r.ok; }
            fullName.addEventListener('input', runFullName); fullName.addEventListener('blur',  runFullName);
            email.addEventListener('input', runEmail); email.addEventListener('blur',  runEmail);
            phone.addEventListener('input', function () { formatPhoneInput(this); });
            phone.addEventListener('blur', function () { const id = parseInt(userId.value || '0', 10); checkPhoneUnique(phone, s.phoneStatus, s.phoneHint, id); });
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const id = parseInt(userId.value || '0', 10);
                const results = await Promise.all([
                    Promise.resolve(runFullName()),
                    Promise.resolve(runEmail()),
                    checkPhoneUnique(phone, s.phoneStatus, s.phoneHint, id)
                ]);
                if (!results.every(Boolean)) {
                    const firstInvalid = form.querySelector('.form-control.is-invalid');
                    if (firstInvalid) { firstInvalid.focus(); firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                    return false;
                }
                submit.disabled = true;
                submit.textContent = '⏳ Updating...';
                form.submit();
            });
        })();
        
        console.log('✅ Users page loaded');
        console.log('👥 Total users: <?= count($users) ?>');
    </script>
</body>
</html>