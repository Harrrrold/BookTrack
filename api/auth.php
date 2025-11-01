<?php
/**
 * Authentication API Endpoints
 * Handles login, register, logout, and session validation
 */

header('Content-Type: application/json');
require_once '../config/database.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

switch ($method) {
    case 'POST':
        if ($action === 'login') {
            handleLogin();
        } elseif ($action === 'register') {
            handleRegister();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'GET':
        if ($action === 'check') {
            checkSession();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        if ($action === 'logout') {
            handleLogout();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function handleLogin() {
    $data = json_decode(file_get_contents('php://input'), true);
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Email and password are required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, password, role, status FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid email or password']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Account is suspended or inactive']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    // Update last login
    $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $updateStmt->bind_param("i", $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    // Create session
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['is_logged_in'] = true;
    
    // Build full name
    $fullName = trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name'] . ' ' . ($user['suffix'] ?? ''));
    
    // Log system activity
    logSystemActivity($conn, $user['id'], 'User logged in', 'info');
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'full_name' => $fullName,
            'first_name' => $user['first_name'],
            'last_name' => $user['last_name'],
            'role' => $user['role']
        ]
    ]);
}

function handleRegister() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $firstName = $data['firstName'] ?? '';
    $middleName = $data['middleName'] ?? '';
    $lastName = $data['lastName'] ?? '';
    $suffix = $data['suffix'] ?? '';
    $email = $data['email'] ?? '';
    $password = $data['password'] ?? '';
    $confirmPassword = $data['confirmPassword'] ?? '';
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'First name, last name, email, and password are required']);
        return;
    }
    
    if ($password !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Passwords do not match']);
        return;
    }
    
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $email);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $checkStmt->close();
        closeDBConnection($conn);
        return;
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $stmt = $conn->prepare("INSERT INTO users (first_name, middle_name, last_name, suffix, email, password, role) VALUES (?, ?, ?, ?, ?, ?, 'user')");
    $stmt->bind_param("ssssss", $firstName, $middleName, $lastName, $suffix, $email, $hashedPassword);
    
    if ($stmt->execute()) {
        $userId = $conn->insert_id;
        
        // Log system activity
        logSystemActivity($conn, $userId, 'New user registered', 'info');
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $userId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Registration failed: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function handleLogout() {
    if (isset($_SESSION['user_id'])) {
        $userId = $_SESSION['user_id'];
        
        $conn = getDBConnection();
        if ($conn) {
            logSystemActivity($conn, $userId, 'User logged out', 'info');
            closeDBConnection($conn);
        }
    }
    
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

function checkSession() {
    if (isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true) {
        $conn = getDBConnection();
        if (!$conn) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            return;
        }
        
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT id, first_name, middle_name, last_name, suffix, email, role, status FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            session_destroy();
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Session invalid']);
            $stmt->close();
            closeDBConnection($conn);
            return;
        }
        
        $user = $result->fetch_assoc();
        
        if ($user['status'] !== 'active') {
            session_destroy();
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Account is suspended']);
            $stmt->close();
            closeDBConnection($conn);
            return;
        }
        
        $fullName = trim($user['first_name'] . ' ' . ($user['middle_name'] ?? '') . ' ' . $user['last_name'] . ' ' . ($user['suffix'] ?? ''));
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email'],
                'full_name' => $fullName,
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'role' => $user['role']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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

