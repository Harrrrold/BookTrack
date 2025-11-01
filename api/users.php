<?php
/**
 * Users API Endpoints
 * Handles user profile and management
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Session is already started in database.php
$isAuthenticated = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$targetUserId = $_GET['id'] ?? null;

if ($method === 'GET' && $action === 'profile' && !$targetUserId) {
    // Allow getting own profile without authentication check first
} elseif (!$isAuthenticated) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? 'user';

switch ($method) {
    case 'GET':
        if ($action === 'profile') {
            if ($targetUserId) {
                getUserProfile($targetUserId);
            } else {
                getMyProfile();
            }
        } elseif ($action === 'list') {
            if ($userRole !== 'admin' && $userRole !== 'library_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            getUserList();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'PUT':
        if ($action === 'profile') {
            if ($targetUserId && ($userRole !== 'admin' && $userRole !== 'library_admin')) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            updateProfile($targetUserId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        if ($targetUserId && ($userRole === 'admin' || $userRole === 'library_admin')) {
            deleteUser($targetUserId);
        } else {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getMyProfile() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, role, profile_image, phone, address, status, created_at, last_login FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $user = formatUser($result->fetch_assoc());
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'user' => $user]);
}

function getUserProfile($targetUserId) {
    global $userId, $userRole;
    
    // Users can only view their own profile unless admin
    if ($targetUserId != $userId && $userRole !== 'admin' && $userRole !== 'library_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, role, profile_image, phone, address, status, created_at, last_login FROM users WHERE id = ?");
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $user = formatUser($result->fetch_assoc());
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'user' => $user]);
}

function getUserList() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $status = $_GET['status'] ?? '';
    $role = $_GET['role'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT id, first_name, middle_name, last_name, suffix, email, role, profile_image, phone, address, status, created_at, last_login FROM users WHERE 1=1";
    $params = [];
    $types = "";
    
    if ($status && in_array($status, ['active', 'suspended', 'inactive'])) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }
    
    if ($role && in_array($role, ['user', 'admin', 'library_admin'])) {
        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= "s";
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = formatUser($row);
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'users' => $users]);
}

function updateProfile($targetUserId = null) {
    global $userId, $userRole;
    
    $updateUserId = $targetUserId ?? $userId;
    
    // Users can only update their own profile unless admin
    if ($updateUserId != $userId && $userRole !== 'admin' && $userRole !== 'library_admin') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        return;
    }
    
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
    
    $allowedFields = ['first_name', 'middle_name', 'last_name', 'suffix', 'phone', 'address', 'profile_image'];
    
    // Admins can update status and role
    if ($userRole === 'admin' || $userRole === 'library_admin') {
        $allowedFields[] = 'status';
        if ($updateUserId != $userId) { // Can't change own role
            $allowedFields[] = 'role';
        }
    }
    
    foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= is_int($data[$field]) ? "i" : "s";
        }
    }
    
    // Handle password update separately if provided
    if (isset($data['password']) && isset($data['current_password'])) {
        // Verify current password
        $checkStmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $checkStmt->bind_param("i", $updateUserId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            $checkStmt->close();
            closeDBConnection($conn);
            return;
        }
        
        $user = $checkResult->fetch_assoc();
        
        if (!password_verify($data['current_password'], $user['password'])) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
            $checkStmt->close();
            closeDBConnection($conn);
            return;
        }
        
        $updates[] = "password = ?";
        $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
        $types .= "s";
        $checkStmt->close();
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        closeDBConnection($conn);
        return;
    }
    
    $params[] = $updateUserId;
    $types .= "i";
    
    $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Log activity
        logSystemActivity($conn, $userId, "Profile updated: User ID $updateUserId", 'info');
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function deleteUser($targetUserId) {
    global $userId;
    
    // Can't delete yourself
    if ($targetUserId == $userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Get user info for logging
    $stmt = $conn->prepare("SELECT email, first_name, last_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $targetUserId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Delete user (cascade will handle related records)
    $deleteStmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $deleteStmt->bind_param("i", $targetUserId);
    
    if ($deleteStmt->execute()) {
        // Log activity
        logSystemActivity($conn, $userId, "User deleted: " . $user['email'], 'warning');
        
        $deleteStmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete user: ' . $deleteStmt->error]);
        $deleteStmt->close();
        closeDBConnection($conn);
    }
}

function formatUser($row) {
    $fullName = trim($row['first_name'] . ' ' . ($row['middle_name'] ?? '') . ' ' . $row['last_name'] . ' ' . ($row['suffix'] ?? ''));
    
    return [
        'id' => (int)$row['id'],
        'first_name' => $row['first_name'],
        'middle_name' => $row['middle_name'],
        'last_name' => $row['last_name'],
        'suffix' => $row['suffix'],
        'full_name' => $fullName,
        'email' => $row['email'],
        'role' => $row['role'],
        'profile_image' => $row['profile_image'],
        'phone' => $row['phone'],
        'address' => $row['address'],
        'status' => $row['status'],
        'created_at' => $row['created_at'],
        'last_login' => $row['last_login']
    ];
}

function logSystemActivity($conn, $userId, $action, $level = 'info') {
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, level, ip_address) VALUES (?, ?, ?, ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param("isss", $userId, $action, $level, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

?>

