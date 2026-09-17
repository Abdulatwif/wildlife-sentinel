<?php
header('Content-Type: application/json');
require_once '../includes/functions.php';
requireLogin();

$pdo = getDB();
$user = getCurrentUser();
$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'mark_read':
            $data = json_decode(file_get_contents('php://input'), true);
            $notificationId = $data['notification_id'] ?? $_GET['id'] ?? null;
            
            if (!$notificationId) {
                throw new Exception('Notification ID required');
            }
            
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notificationId, $user['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Notification marked as read'
            ]);
            break;
            
        case 'mark_all_read':
            $stmt = $pdo->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW() 
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'All notifications marked as read'
            ]);
            break;
            
        case 'count':
            $count = getUnreadNotificationCount($user['id']);
            echo json_encode([
                'success' => true,
                'count' => $count
            ]);
            break;
            
        case 'list':
            $limit = $_GET['limit'] ?? 20;
            $notifications = getUserNotifications($user['id'], $limit);
            echo json_encode([
                'success' => true,
                'notifications' => $notifications,
                'count' => count($notifications)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>