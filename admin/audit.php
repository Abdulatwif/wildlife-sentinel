<?php
require_once '../includes/functions.php';
requireAdmin();

$user = getCurrentUser();
$pdo = getDB();
$error = '';
$success = '';

// Handle clear logs action
if (isset($_POST['action']) && $_POST['action'] === 'clear_all') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $error = 'Security validation failed. Please try again.';
    } else {
        // Double check with confirmation
        if (isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
            try {
                // Get count before clearing
                $stmt = $pdo->query("SELECT COUNT(*) as total FROM audit_logs");
                $count = $stmt->fetch()['total'];
                
                // Clear all audit logs
                $stmt = $pdo->prepare("DELETE FROM audit_logs");
                $stmt->execute();
                
                // Log the action
                logAudit($user['id'], 'clear_all_audit_logs', ['deleted_count' => $count]);
                
                $success = "✅ All audit logs have been cleared successfully. ({$count} records deleted)";
                
                // Redirect to refresh the page
                header("Location: audit.php?cleared=1");
                exit();
            } catch (PDOException $e) {
                $error = 'Database error: ' . $e->getMessage();
            }
        } else {
            $error = 'Please confirm that you want to clear all logs.';
        }
    }
}

// Get filter parameters
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$actionFilter = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$dateFrom = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$dateTo = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$sql = "
    SELECT a.*, u.full_name as user_name, u.email as user_email, u.role as user_role
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    WHERE 1=1
";
$params = [];

if ($userId) {
    $sql .= " AND a.user_id = ?";
    $params[] = $userId;
}

if ($actionFilter) {
    $sql .= " AND a.action LIKE ?";
    $params[] = "%$actionFilter%";
}

if ($dateFrom) {
    $sql .= " AND DATE(a.created_at) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo) {
    $sql .= " AND DATE(a.created_at) <= ?";
    $params[] = $dateTo;
}

if ($search) {
    $sql .= " AND (a.action LIKE ? OR a.ip_address LIKE ? OR u.full_name LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY a.created_at DESC LIMIT ?";
$params[] = $limit;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get statistics for dashboard
$totalLogs = $pdo->query("SELECT COUNT(*) as count FROM audit_logs")->fetch()['count'];

// Get action statistics
$actionStats = $pdo->query("
    SELECT action, COUNT(*) as count 
    FROM audit_logs 
    GROUP BY action 
    ORDER BY count DESC 
    LIMIT 10
")->fetchAll();

// Get users for filter
$users = $pdo->query("SELECT id, full_name, email FROM users ORDER BY full_name")->fetchAll();

// Get date range for logs
$dateRange = $pdo->query("
    SELECT 
        MIN(created_at) as earliest,
        MAX(created_at) as latest
    FROM audit_logs
")->fetch();

// Generate CSRF token
$csrfToken = generateCSRFToken();

// Check if logs were cleared
$cleared = isset($_GET['cleared']) ? true : false;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Audit Logs - Wildlife Sentinel</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/transitions.css">
    <style>
        /* ============================================
           AUDIT PAGE STYLES
           ============================================ */
        
        /* Stats Row */
        .stats-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 16px;
        }

        .stat-chip {
            background: white;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid var(--gray-300);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .stat-chip .count {
            font-weight: 700;
            color: var(--primary);
        }

        .stat-chip .count.danger { color: var(--danger); }
        .stat-chip .count.warning { color: var(--warning); }
        .stat-chip .count.success { color: var(--success); }

        /* Filter Bar */
        .filter-bar {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            align-items: center;
            background: white;
            padding: 15px 18px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        }

        .filter-bar select,
        .filter-bar input {
            padding: 8px 14px;
            border: 2px solid var(--gray-300);
            border-radius: 8px;
            font-size: 14px;
            background: white;
            min-height: 42px;
        }

        .filter-bar select:focus,
        .filter-bar input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(26, 92, 58, 0.1);
        }

        .filter-bar .btn {
            padding: 8px 20px;
            min-height: 42px;
        }

        .filter-bar .search-wrapper {
            flex: 1;
            min-width: 150px;
        }

        .filter-bar .search-wrapper input {
            width: 100%;
        }

        .filter-bar .date-group {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .filter-bar .date-group input[type="date"] {
            max-width: 150px;
            min-height: 42px;
        }

        /* Section Header */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 15px;
        }

        .section-header .title-group {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-header .title-group .log-count {
            font-size: 13px;
            color: var(--gray-500);
            font-weight: 400;
        }

        .section-header .action-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        /* Clear All Button */
        .btn-danger-clear {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-danger-clear:hover {
            background: #c62828;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger-clear:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        /* Log Table */
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .log-table th {
            text-align: left;
            padding: 10px 12px;
            background: var(--gray-100);
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-700);
            border-bottom: 2px solid var(--gray-300);
            white-space: nowrap;
        }

        .log-table td {
            padding: 8px 12px;
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        .log-table tr:hover td {
            background: var(--gray-100);
        }

        .log-table .action-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .action-badge.login { background: #cce5ff; color: #004085; }
        .action-badge.logout { background: #e2e3e5; color: #383d41; }
        .action-badge.create_user { background: #d4edda; color: #155724; }
        .action-badge.update_user { background: #fff3cd; color: #856404; }
        .action-badge.delete_user { background: #f8d7da; color: #721c24; }
        .action-badge.create_zone { background: #d1ecf1; color: #0c5460; }
        .action-badge.update_zone { background: #fff3cd; color: #856404; }
        .action-badge.delete_zone { background: #f8d7da; color: #721c24; }
        .action-badge.report_incident { background: #f8d7da; color: #721c24; }
        .action-badge.acknowledge_incident { background: #cce5ff; color: #004085; }
        .action-badge.update_profile { background: #d4edda; color: #155724; }
        .action-badge.change_password { background: #fff3cd; color: #856404; }
        .action-badge.register_zone { background: #d1ecf1; color: #0c5460; }
        .action-badge.clear_all_audit_logs { background: #dc3545; color: white; }

        .log-table .user-cell {
            font-weight: 500;
        }

        .log-table .user-cell .user-email {
            font-size: 11px;
            color: var(--gray-500);
        }

        .log-table .details-cell {
            font-size: 12px;
            color: var(--gray-600);
            max-width: 250px;
            word-break: break-all;
        }

        .log-table .time-cell {
            font-size: 12px;
            white-space: nowrap;
        }

        .log-table .time-cell .date {
            color: var(--gray-600);
        }
        .log-table .time-cell .time {
            color: var(--gray-400);
            font-size: 11px;
        }

        /* Clear All Modal */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 16px;
            max-width: 480px;
            width: 100%;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .modal-header h3 {
            font-size: 20px;
            color: #dc3545;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header .close {
            font-size: 28px;
            background: none;
            border: none;
            cursor: pointer;
            padding: 4px 8px;
            color: var(--gray-500);
            transition: all 0.3s;
        }

        .modal-header .close:hover {
            color: var(--danger);
            transform: rotate(90deg);
        }

        .modal-body {
            margin-bottom: 20px;
        }

        .modal-body .warning-icon {
            font-size: 48px;
            text-align: center;
            display: block;
            margin-bottom: 10px;
        }

        .modal-body p {
            color: var(--gray-700);
            line-height: 1.6;
            text-align: center;
        }

        .modal-body .log-count-display {
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            color: #dc3545;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            margin: 10px 0;
        }

        .modal-footer {
            display: flex;
            gap: 10px;
        }

        .modal-footer .btn {
            flex: 1;
            justify-content: center;
            min-height: 44px;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c62828;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray-500);
        }

        .empty-state .empty-icon {
            font-size: 56px;
            margin-bottom: 12px;
        }

        .empty-state h3 {
            color: var(--gray-700);
            margin-bottom: 4px;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .log-table {
                font-size: 12px;
            }
            .log-table th,
            .log-table td {
                padding: 6px 8px;
            }
        }

        @media (max-width: 768px) {
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
                padding: 12px 14px;
            }

            .filter-bar .search-wrapper {
                min-width: 100%;
            }

            .filter-bar select,
            .filter-bar input {
                width: 100%;
                font-size: 16px;
            }

            .filter-bar .btn {
                width: 100%;
                justify-content: center;
            }

            .filter-bar .date-group {
                flex-wrap: wrap;
            }

            .filter-bar .date-group input[type="date"] {
                flex: 1;
                min-width: 120px;
            }

            .stats-row {
                gap: 8px;
            }

            .stat-chip {
                font-size: 12px;
                padding: 6px 12px;
            }

            .section-header {
                flex-direction: column;
                align-items: stretch;
            }

            .section-header .action-group {
                flex-direction: column;
            }

            .section-header .action-group .btn,
            .section-header .action-group .btn-danger-clear {
                width: 100%;
                justify-content: center;
            }

            .log-table {
                font-size: 11px;
            }

            .log-table th,
            .log-table td {
                padding: 4px 6px;
            }

            .log-table .details-cell {
                max-width: 120px;
                font-size: 10px;
            }

            .modal-content {
                padding: 20px;
                margin: 10px;
            }

            .modal-footer {
                flex-direction: column;
            }
        }

        @media (max-width: 480px) {
            .stats-row {
                gap: 6px;
            }

            .stat-chip {
                font-size: 10px;
                padding: 4px 10px;
            }

            .log-table {
                font-size: 10px;
            }

            .log-table th,
            .log-table td {
                padding: 3px 4px;
            }

            .log-table .action-badge {
                font-size: 8px;
                padding: 1px 6px;
            }

            .log-table .details-cell {
                max-width: 80px;
                font-size: 9px;
            }

            .log-table .time-cell {
                font-size: 10px;
            }

            .modal-header h3 {
                font-size: 17px;
            }

            .modal-body .log-count-display {
                font-size: 20px;
            }
        }

        /* Scrollable table */
        .table-scroll {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Success notification */
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
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
                <h1>Audit Logs</h1>
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
                
                <?php if ($cleared): ?>
                    <div class="alert alert-success">✅ All audit logs have been cleared successfully.</div>
                <?php endif; ?>
                
                <!-- Stats Row -->
                <div class="stats-row">
                    <span class="stat-chip">📊 Total Logs: <span class="count"><?= $totalLogs ?></span></span>
                    <span class="stat-chip">📅 From: <span class="count"><?= $dateRange['earliest'] ? date('M j, Y', strtotime($dateRange['earliest'])) : 'N/A' ?></span></span>
                    <span class="stat-chip">📅 To: <span class="count"><?= $dateRange['latest'] ? date('M j, Y', strtotime($dateRange['latest'])) : 'N/A' ?></span></span>
                    <span class="stat-chip">📋 Showing: <span class="count"><?= count($logs) ?></span></span>
                </div>
                
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%;">
                        <div class="search-wrapper">
                            <input type="text" name="search" placeholder="Search logs..." value="<?= $search ?>" class="form-control">
                        </div>
                        
                        <select name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $userId == $u['id'] ? 'selected' : '' ?>>
                                <?= $u['full_name'] ?> (<?= $u['email'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="action">
                            <option value="">All Actions</option>
                            <?php foreach ($actionStats as $stat): ?>
                            <option value="<?= $stat['action'] ?>" <?= $actionFilter === $stat['action'] ? 'selected' : '' ?>>
                                <?= str_replace('_', ' ', ucfirst($stat['action'])) ?> (<?= $stat['count'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <div class="date-group">
                            <input type="date" name="date_from" value="<?= $dateFrom ?>" placeholder="From">
                            <span style="color:var(--gray-500);">to</span>
                            <input type="date" name="date_to" value="<?= $dateTo ?>" placeholder="To">
                        </div>
                        
                        <select name="limit">
                            <option value="20" <?= $limit == 20 ? 'selected' : '' ?>>20</option>
                            <option value="50" <?= $limit == 50 ? 'selected' : '' ?>>50</option>
                            <option value="100" <?= $limit == 100 ? 'selected' : '' ?>>100</option>
                            <option value="200" <?= $limit == 200 ? 'selected' : '' ?>>200</option>
                            <option value="500" <?= $limit == 500 ? 'selected' : '' ?>>500</option>
                        </select>
                        
                        <div class="filter-actions" style="display:flex;gap:8px;flex-wrap:wrap;">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="audit.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <!-- Log Table -->
                <div class="section">
                    <div class="section-header">
                        <div class="title-group">
                            <h2>📋 System Activity Log</h2>
                            <span class="log-count">Showing <?= count($logs) ?> of <?= $totalLogs ?> logs</span>
                        </div>
                        <div class="action-group">
                            <a href="audit.php?export=1" class="btn btn-secondary" onclick="exportLogs(event)">
                                📥 Export
                            </a>
                            <?php if ($totalLogs > 0): ?>
                            <button class="btn-danger-clear" onclick="showClearModal()">
                                🗑️ Clear All Logs
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="table-scroll">
                        <table class="log-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <?= $log['user_name'] ?? 'System' ?>
                                                <?php if ($log['user_email']): ?>
                                                <div class="user-email"><?= $log['user_email'] ?></div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="action-badge <?= str_replace('_', '-', $log['action']) ?>">
                                                <?= str_replace('_', ' ', $log['action']) ?>
                                            </span>
                                        </td>
                                        <td class="details-cell">
                                            <?php 
                                            if ($log['details']) {
                                                $details = json_decode($log['details'], true);
                                                if ($details) {
                                                    $output = [];
                                                    foreach ($details as $key => $value) {
                                                        if (is_array($value)) {
                                                            $output[] = $key . ': ' . json_encode($value);
                                                        } else {
                                                            $output[] = $key . ': ' . $value;
                                                        }
                                                    }
                                                    echo implode(', ', $output);
                                                } else {
                                                    echo $log['details'];
                                                }
                                            } else {
                                                echo '—';
                                            }
                                            ?>
                                        </td>
                                        <td style="font-size:12px;font-family:monospace;">
                                            <?= $log['ip_address'] ?? 'N/A' ?>
                                        </td>
                                        <td class="time-cell">
                                            <div class="date"><?= date('M j, Y', strtotime($log['created_at'])) ?></div>
                                            <div class="time"><?= date('H:i:s', strtotime($log['created_at'])) ?></div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5">
                                            <div class="empty-state">
                                                <div class="empty-icon">📭</div>
                                                <h3>No Logs Found</h3>
                                                <p>Try adjusting your filters or search criteria.</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Clear All Modal -->
    <div id="clearModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>⚠️ Clear All Logs</h3>
                <button class="close" onclick="closeModal('clearModal')">&times;</button>
            </div>
            <form method="POST" id="clearForm">
                <input type="hidden" name="action" value="clear_all">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="confirm" value="yes">
                
                <div class="modal-body">
                    <span class="warning-icon">⚠️</span>
                    <p>
                        <strong>This action cannot be undone!</strong>
                        <br>
                        You are about to permanently delete all audit logs from the system.
                    </p>
                    <div class="log-count-display">
                        <?= $totalLogs ?> logs will be deleted
                    </div>
                    <p style="font-size:13px;color:var(--gray-500);">
                        Are you sure you want to continue?
                    </p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('clearModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">🗑️ Yes, Clear All Logs</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="../assets/js/app.js"></script>
    <script src="../assets/js/transitions.js"></script>
    <script>
        // ============================================
        // MODAL FUNCTIONS
        // ============================================
        
        function showClearModal() {
            document.getElementById('clearModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
            document.body.style.overflow = '';
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
                document.body.style.overflow = '';
            }
        }
        
        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal.show').forEach(function(modal) {
                    modal.classList.remove('show');
                    document.body.style.overflow = '';
                });
            }
        });
        
        // ============================================
        // EXPORT LOGS
        // ============================================
        
        function exportLogs(e) {
            e.preventDefault();
            
            if (confirm('📥 Export audit logs as CSV?')) {
                window.location.href = 'audit.php?export=1&' + window.location.search.substring(1);
            }
        }
        
        // ============================================
        // AUTO-REFRESH (Optional)
        // ============================================
        
        // Refresh logs every 60 seconds if page is visible
        let autoRefresh = true;
        let refreshInterval = 60000;
        
        if (autoRefresh && document.querySelector('.log-table tbody tr')) {
            setInterval(function() {
                if (!document.hidden) {
                    // Update the log count
                    fetch('audit.php?ajax=1')
                        .then(response => response.text())
                        .then(html => {
                            // Update count
                            const match = html.match(/Total Logs: <span class="count">(\d+)/);
                            if (match) {
                                const countElement = document.querySelector('.stat-chip .count');
                                if (countElement) {
                                    countElement.textContent = match[1];
                                }
                            }
                        })
                        .catch(() => {});
                }
            }, refreshInterval);
        }
        
        console.log('✅ Audit Logs page loaded');
        console.log('📊 Total logs: <?= $totalLogs ?>');
        console.log('📋 Displaying: <?= count($logs) ?>');
    </script>
</body>
</html>