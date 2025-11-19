<?php
/**
 * System Logs API Endpoints
 * Admin only - Handles system logs viewing
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Session is already started in database.php
$isAuthenticated = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$userRole = $_SESSION['user_role'] ?? 'user';

if (!$isAuthenticated || ($userRole !== 'library_admin' && $userRole !== 'library_moderator')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$logId = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($logId) {
            getLog($logId);
        } else {
            getLogs();
        }
        break;
        
    case 'DELETE':
        if ($logId) {
            deleteLog($logId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Log ID required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getLogs() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $level = $_GET['level'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT sl.*, u.first_name, u.last_name, u.email 
            FROM system_logs sl 
            LEFT JOIN users u ON sl.user_id = u.id 
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($level && in_array($level, ['info', 'warning', 'error', 'success'])) {
        $sql .= " AND sl.level = ?";
        $params[] = $level;
        $types .= "s";
    }
    
    if ($userId && is_numeric($userId)) {
        $sql .= " AND sl.user_id = ?";
        $params[] = (int)$userId;
        $types .= "i";
    }
    
    $sql .= " ORDER BY sl.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'user_name' => $row['first_name'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'user_email' => $row['email'],
            'action' => $row['action'],
            'details' => $row['details'],
            'level' => $row['level'],
            'ip_address' => $row['ip_address'],
            'timestamp' => $row['created_at'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'logs' => $logs, 'count' => count($logs)]);
}

function getLog($id) {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT sl.*, u.first_name, u.last_name, u.email 
                           FROM system_logs sl 
                           LEFT JOIN users u ON sl.user_id = u.id 
                           WHERE sl.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Log not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'log' => [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'user_name' => $row['first_name'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'user_email' => $row['email'],
            'action' => $row['action'],
            'details' => $row['details'],
            'level' => $row['level'],
            'ip_address' => $row['ip_address'],
            'timestamp' => $row['created_at']
        ]
    ]);
}

function deleteLog($id) {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $userId = $_SESSION['user_id'] ?? null;
    
    $stmt = $conn->prepare("DELETE FROM system_logs WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Log activity
        if ($userId) {
            logSystemActivity($conn, $userId, "Log deleted: ID $id", 'info');
        }
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Log deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete log: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function logSystemActivity($conn, $userId, $action, $level = 'info') {
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, level, ip_address) VALUES (?, ?, ?, ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param("isss", $userId, $action, $level, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

?>

