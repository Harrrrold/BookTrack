<?php
/**
 * Notifications API Endpoints
 * Handles user notifications
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Session is already started in database.php
$isAuthenticated = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

if (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$notificationId = $_GET['id'] ?? null;
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'GET':
        if ($notificationId) {
            getNotification($notificationId);
        } else {
            getNotifications();
        }
        break;
        
    case 'PUT':
        if ($notificationId) {
            if ($action === 'read') {
                markAsRead($notificationId);
            } else {
                updateNotification($notificationId);
            }
        } elseif ($action === 'read-all') {
            markAllAsRead();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        if ($notificationId) {
            deleteNotification($notificationId);
        } elseif ($action === 'clear-all') {
            clearAllNotifications();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Notification ID or action required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getNotifications() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $filter = $_GET['filter'] ?? 'all';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT n.*, bk.title as book_title, bk.id as book_id 
            FROM notifications n 
            LEFT JOIN books bk ON n.book_id = bk.id 
            WHERE n.user_id = ?";
    
    $params = [$userId];
    $types = "i";
    
    switch ($filter) {
        case 'unread':
            $sql .= " AND n.is_read = 0";
            break;
        case 'due':
            $sql .= " AND n.type = 'due'";
            break;
        case 'available':
            $sql .= " AND n.type = 'available'";
            break;
        case 'system':
            $sql .= " AND n.type = 'system'";
            break;
        // 'all' - no additional filter
    }
    
    $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = [
            'id' => (int)$row['id'],
            'type' => $row['type'],
            'title' => $row['title'],
            'message' => $row['message'],
            'book_id' => $row['book_id'] ? (int)$row['book_id'] : null,
            'book_title' => $row['book_title'],
            'is_read' => (bool)$row['is_read'],
            'priority' => $row['priority'],
            'created_at' => $row['created_at'],
            'timestamp' => $row['created_at']
        ];
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    // Get unread count
    $unreadCount = getUnreadCount($userId);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount
    ]);
}

function getNotification($id) {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT n.*, bk.title as book_title, bk.id as book_id 
                           FROM notifications n 
                           LEFT JOIN books bk ON n.book_id = bk.id 
                           WHERE n.id = ? AND n.user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Notification not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $notification = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'notification' => [
            'id' => (int)$notification['id'],
            'type' => $notification['type'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'book_id' => $notification['book_id'] ? (int)$notification['book_id'] : null,
            'book_title' => $notification['book_title'],
            'is_read' => (bool)$notification['is_read'],
            'priority' => $notification['priority'],
            'created_at' => $notification['created_at']
        ]
    ]);
}

function markAsRead($id) {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Notification marked as read']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function markAllAsRead() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'All notifications marked as read']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function deleteNotification($id) {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $id, $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function clearAllNotifications() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'All notifications cleared']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to clear notifications']);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function updateNotification($id) {
    global $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Build update query
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($data['is_read'])) {
        $updates[] = "is_read = ?";
        $params[] = $data['is_read'] ? 1 : 0;
        $types .= "i";
    }
    
    if (isset($data['priority'])) {
        $updates[] = "priority = ?";
        $params[] = $data['priority'];
        $types .= "s";
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        closeDBConnection($conn);
        return;
    }
    
    $params[] = $id;
    $params[] = $userId;
    $types .= "ii";
    
    $sql = "UPDATE notifications SET " . implode(", ", $updates) . " WHERE id = ? AND user_id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Notification updated']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update notification']);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function getUnreadCount($userId) {
    $conn = getDBConnection();
    if (!$conn) {
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($conn);
    
    return (int)$row['count'];
}

?>

