<?php
/**
 * Reservations API Endpoints
 * Handles book reservations
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
$userRole = $_SESSION['user_role'] ?? 'user';
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$reservationId = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($reservationId) {
            getReservation($reservationId);
        } elseif ($action === 'my') {
            getMyReservations();
        } else {
            // Admin only
            if ($userRole !== 'library_admin' && $userRole !== 'library_moderator') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            getAllReservations();
        }
        break;
        
    case 'POST':
        if ($action === 'create') {
            createReservation();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    case 'DELETE':
        if ($reservationId) {
            cancelReservation($reservationId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Reservation ID required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function createReservation() {
    global $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $bookId = $data['book_id'] ?? null;
    $expiryDays = isset($data['expiry_days']) ? (int)$data['expiry_days'] : 7;
    
    if (!$bookId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Book ID is required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Check if book exists
    $stmt = $conn->prepare("SELECT id, title, availability FROM books WHERE id = ?");
    $stmt->bind_param("i", $bookId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $book = $result->fetch_assoc();
    
    // Check if user already has this book borrowed
    $checkBorrowStmt = $conn->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
    $checkBorrowStmt->bind_param("ii", $userId, $bookId);
    $checkBorrowStmt->execute();
    $checkBorrowResult = $checkBorrowStmt->get_result();
    
    if ($checkBorrowResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have this book borrowed']);
        $checkBorrowStmt->close();
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    $checkBorrowStmt->close();
    
    // Check if user already has a pending reservation
    $checkResStmt = $conn->prepare("SELECT id FROM reservations WHERE user_id = ? AND book_id = ? AND status IN ('pending', 'available')");
    $checkResStmt->bind_param("ii", $userId, $bookId);
    $checkResStmt->execute();
    $checkResResult = $checkResStmt->get_result();
    
    if ($checkResResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have a pending reservation for this book']);
        $checkResStmt->close();
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    $checkResStmt->close();
    
    // Create reservation
    $expiryDate = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));
    $status = $book['availability'] === 'available' ? 'available' : 'pending';
    
    $insertStmt = $conn->prepare("INSERT INTO reservations (user_id, book_id, expiry_date, status) VALUES (?, ?, ?, ?)");
    $insertStmt->bind_param("iiss", $userId, $bookId, $expiryDate, $status);
    
    if ($insertStmt->execute()) {
        $reservationId = $conn->insert_id;
        
        // Update book availability if available
        if ($status === 'available') {
            $updateStmt = $conn->prepare("UPDATE books SET availability = 'reserved' WHERE id = ?");
            $updateStmt->bind_param("i", $bookId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        // Log activity
        logSystemActivity($conn, $userId, "Book reserved: " . $book['title'], 'info');
        
        // Create notification
        if ($status === 'available') {
            createNotification($conn, $userId, 'available', 'Reserved Book Ready', "The book '{$book['title']}' you reserved is available for pickup.", $bookId);
        } else {
            createNotification($conn, $userId, 'system', 'Book Reserved', "You have reserved '{$book['title']}'. You will be notified when it becomes available.", $bookId);
        }
        
        $insertStmt->close();
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Book reserved successfully',
            'reservation_id' => $reservationId,
            'expiry_date' => $expiryDate
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create reservation: ' . $insertStmt->error]);
        $insertStmt->close();
        $stmt->close();
        closeDBConnection($conn);
    }
}

function cancelReservation($id) {
    global $userId, $userRole;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Get reservation details
    $stmt = $conn->prepare("SELECT r.*, bk.title FROM reservations r JOIN books bk ON r.book_id = bk.id WHERE r.id = ? AND (r.user_id = ? OR ? = 'library_admin' OR ? = 'library_moderator')");
    $stmt->bind_param("isss", $id, $userId, $userRole, $userRole);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $reservation = $result->fetch_assoc();
    $stmt->close();
    
    // Update reservation status
    $updateStmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE id = ?");
    $updateStmt->bind_param("i", $id);
    
    if ($updateStmt->execute()) {
        // Check if book should be made available
        $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM reservations WHERE book_id = ? AND status IN ('pending', 'available')");
        $checkStmt->bind_param("i", $reservation['book_id']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $check = $checkResult->fetch_assoc();
        $checkStmt->close();
        
        if ($check['count'] == 0) {
            // No more reservations, make book available
            $bookStmt = $conn->prepare("UPDATE books SET availability = 'available' WHERE id = ?");
            $bookStmt->bind_param("i", $reservation['book_id']);
            $bookStmt->execute();
            $bookStmt->close();
        }
        
        // Log activity
        logSystemActivity($conn, $userId, "Reservation cancelled: " . $reservation['title'], 'info');
        
        $updateStmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Reservation cancelled successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel reservation: ' . $updateStmt->error]);
        $updateStmt->close();
        closeDBConnection($conn);
    }
}

function getMyReservations() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $sql = "SELECT r.*, bk.title, bk.author, bk.cover_image, bk.isbn 
            FROM reservations r 
            JOIN books bk ON r.book_id = bk.id 
            WHERE r.user_id = ? AND r.status != 'cancelled'
            ORDER BY r.reserved_date DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        // Check if expired
        if ($row['status'] === 'pending' && strtotime($row['expiry_date']) < time()) {
            $row['status'] = 'expired';
        }
        
        $reservations[] = [
            'id' => (int)$row['id'],
            'book_id' => (int)$row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'cover_image' => $row['cover_image'],
            'reserved_date' => $row['reserved_date'],
            'expiry_date' => $row['expiry_date'],
            'status' => $row['status']
        ];
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'reservations' => $reservations]);
}

function getAllReservations() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $sql = "SELECT r.*, bk.title, bk.author, u.first_name, u.last_name, u.email 
            FROM reservations r 
            JOIN books bk ON r.book_id = bk.id 
            JOIN users u ON r.user_id = u.id 
            ORDER BY r.created_at DESC";
    
    $result = $conn->query($sql);
    
    $reservations = [];
    while ($row = $result->fetch_assoc()) {
        $reservations[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'reservations' => $reservations]);
}

function getReservation($id) {
    global $userId, $userRole;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT r.*, bk.title, bk.author FROM reservations r JOIN books bk ON r.book_id = bk.id WHERE r.id = ? AND (r.user_id = ? OR ? = 'library_admin' OR ? = 'library_moderator')");
    $stmt->bind_param("isss", $id, $userId, $userRole, $userRole);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Reservation not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $reservation = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'reservation' => $reservation]);
}

function createNotification($conn, $userId, $type, $title, $message, $bookId = null) {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, book_id, priority) VALUES (?, ?, ?, ?, ?, 'medium')");
    $stmt->bind_param("isssi", $userId, $type, $title, $message, $bookId);
    $stmt->execute();
    $stmt->close();
}

function logSystemActivity($conn, $userId, $action, $level = 'info') {
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, action, level, ip_address) VALUES (?, ?, ?, ?)");
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt->bind_param("isss", $userId, $action, $level, $ipAddress);
    $stmt->execute();
    $stmt->close();
}

?>

