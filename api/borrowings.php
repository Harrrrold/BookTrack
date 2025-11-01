<?php
/**
 * Borrowings API Endpoints
 * Handles book borrowing and returns
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
$borrowingId = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($borrowingId) {
            getBorrowing($borrowingId);
        } elseif ($action === 'my') {
            getMyBorrowings();
        } else {
            // Admin only
            if ($userRole !== 'admin' && $userRole !== 'library_admin') {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            getAllBorrowings();
        }
        break;
        
    case 'POST':
        if ($action === 'borrow') {
            borrowBook();
        } elseif ($action === 'return') {
            returnBook();
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function borrowBook() {
    global $userId, $userRole;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $bookId = $data['book_id'] ?? null;
    $dueDays = isset($data['due_days']) ? (int)$data['due_days'] : 14;
    
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
    
    // Check if book exists and is available
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
    
    if ($book['availability'] !== 'available') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Book is not available for borrowing']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    // Check if user already has this book borrowed
    $checkStmt = $conn->prepare("SELECT id FROM borrowings WHERE user_id = ? AND book_id = ? AND status = 'borrowed'");
    $checkStmt->bind_param("ii", $userId, $bookId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'You already have this book borrowed']);
        $checkStmt->close();
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    $checkStmt->close();
    
    // Create borrowing record
    $borrowedDate = date('Y-m-d');
    $dueDate = date('Y-m-d', strtotime("+$dueDays days"));
    
    $insertStmt = $conn->prepare("INSERT INTO borrowings (user_id, book_id, borrowed_date, due_date, status) VALUES (?, ?, ?, ?, 'borrowed')");
    $insertStmt->bind_param("iiss", $userId, $bookId, $borrowedDate, $dueDate);
    
    if ($insertStmt->execute()) {
        $borrowingId = $conn->insert_id;
        
        // Update book availability
        $updateStmt = $conn->prepare("UPDATE books SET availability = 'borrowed', last_borrowed = ?, total_borrows = total_borrows + 1 WHERE id = ?");
        $updateStmt->bind_param("si", $borrowedDate, $bookId);
        $updateStmt->execute();
        $updateStmt->close();
        
        // Cancel any pending reservations for this book by this user
        $cancelResStmt = $conn->prepare("UPDATE reservations SET status = 'cancelled' WHERE user_id = ? AND book_id = ? AND status = 'pending'");
        $cancelResStmt->bind_param("ii", $userId, $bookId);
        $cancelResStmt->execute();
        $cancelResStmt->close();
        
        // Log activity
        logSystemActivity($conn, $userId, "Book borrowed: " . $book['title'], 'info');
        
        // Create notification
        createNotification($conn, $userId, 'system', 'Book Borrowed', "You have successfully borrowed '{$book['title']}'. Due date: $dueDate", $bookId);
        
        $insertStmt->close();
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Book borrowed successfully',
            'borrowing_id' => $borrowingId,
            'due_date' => $dueDate
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to borrow book: ' . $insertStmt->error]);
        $insertStmt->close();
        $stmt->close();
        closeDBConnection($conn);
    }
}

function returnBook() {
    global $userId, $userRole;
    
    $data = json_decode(file_get_contents('php://input'), true);
    $borrowingId = $data['borrowing_id'] ?? null;
    $bookId = $data['book_id'] ?? null;
    
    if (!$borrowingId && !$bookId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Borrowing ID or Book ID is required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Find borrowing record
    if ($borrowingId) {
        $stmt = $conn->prepare("SELECT b.*, bk.title as book_title FROM borrowings b JOIN books bk ON b.book_id = bk.id WHERE b.id = ? AND (b.user_id = ? OR ? = 'admin' OR ? = 'library_admin')");
        $stmt->bind_param("isss", $borrowingId, $userId, $userRole, $userRole);
    } else {
        $stmt = $conn->prepare("SELECT b.*, bk.title as book_title FROM borrowings b JOIN books bk ON b.book_id = bk.id WHERE b.book_id = ? AND b.user_id = ? AND b.status = 'borrowed'");
        $stmt->bind_param("ii", $bookId, $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Borrowing record not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $borrowing = $result->fetch_assoc();
    $stmt->close();
    
    // Check if overdue and calculate fine (if needed)
    $returnDate = date('Y-m-d');
    $dueDate = $borrowing['due_date'];
    $fineAmount = 0.00;
    
    if (strtotime($returnDate) > strtotime($dueDate)) {
        $daysOverdue = (int)((strtotime($returnDate) - strtotime($dueDate)) / 86400);
        $fineAmount = $daysOverdue * 1.00; // $1 per day overdue (adjust as needed)
    }
    
    // Update borrowing record
    $updateStmt = $conn->prepare("UPDATE borrowings SET return_date = ?, status = 'returned', fine_amount = ? WHERE id = ?");
    $updateStmt->bind_param("sdi", $returnDate, $fineAmount, $borrowing['id']);
    
    if ($updateStmt->execute()) {
        // Update book availability
        $bookUpdateStmt = $conn->prepare("UPDATE books SET availability = 'available' WHERE id = ?");
        $bookUpdateStmt->bind_param("i", $borrowing['book_id']);
        $bookUpdateStmt->execute();
        $bookUpdateStmt->close();
        
        // Check if there are pending reservations and notify
        $resStmt = $conn->prepare("SELECT user_id FROM reservations WHERE book_id = ? AND status = 'pending' ORDER BY reserved_date ASC LIMIT 1");
        $resStmt->bind_param("i", $borrowing['book_id']);
        $resStmt->execute();
        $resResult = $resStmt->get_result();
        
        if ($resResult->num_rows > 0) {
            $reservation = $resResult->fetch_assoc();
            createNotification($conn, $reservation['user_id'], 'available', 'Reserved Book Available', "The book '{$borrowing['book_title']}' you reserved is now available for pickup.", $borrowing['book_id']);
        }
        $resStmt->close();
        
        // Log activity
        logSystemActivity($conn, $userId, "Book returned: " . $borrowing['book_title'], 'info');
        
        // Create notification
        createNotification($conn, $borrowing['user_id'], 'system', 'Book Returned', "You have successfully returned '{$borrowing['book_title']}'.", $borrowing['book_id']);
        
        $updateStmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Book returned successfully',
            'fine_amount' => $fineAmount
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to return book: ' . $updateStmt->error]);
        $updateStmt->close();
        closeDBConnection($conn);
    }
}

function getMyBorrowings() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $status = $_GET['status'] ?? '';
    $sql = "SELECT b.*, bk.title, bk.author, bk.cover_image, bk.isbn 
            FROM borrowings b 
            JOIN books bk ON b.book_id = bk.id 
            WHERE b.user_id = ?";
    
    if ($status && in_array($status, ['borrowed', 'returned', 'overdue'])) {
        $sql .= " AND b.status = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $userId, $status);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $borrowings = [];
    while ($row = $result->fetch_assoc()) {
        // Check if overdue
        if ($row['status'] === 'borrowed' && strtotime($row['due_date']) < strtotime(date('Y-m-d'))) {
            $row['status'] = 'overdue';
        }
        
        $borrowings[] = [
            'id' => (int)$row['id'],
            'book_id' => (int)$row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'cover_image' => $row['cover_image'],
            'borrowed_date' => $row['borrowed_date'],
            'due_date' => $row['due_date'],
            'return_date' => $row['return_date'],
            'status' => $row['status'],
            'fine_amount' => (float)$row['fine_amount']
        ];
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'borrowings' => $borrowings]);
}

function getAllBorrowings() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $sql = "SELECT b.*, bk.title, bk.author, u.first_name, u.last_name, u.email 
            FROM borrowings b 
            JOIN books bk ON b.book_id = bk.id 
            JOIN users u ON b.user_id = u.id 
            ORDER BY b.created_at DESC";
    
    $result = $conn->query($sql);
    
    $borrowings = [];
    while ($row = $result->fetch_assoc()) {
        $borrowings[] = $row;
    }
    
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'borrowings' => $borrowings]);
}

function getBorrowing($id) {
    global $userId, $userRole;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT b.*, bk.title, bk.author FROM borrowings b JOIN books bk ON b.book_id = bk.id WHERE b.id = ? AND (b.user_id = ? OR ? = 'admin' OR ? = 'library_admin')");
    $stmt->bind_param("isss", $id, $userId, $userRole, $userRole);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Borrowing not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $borrowing = $result->fetch_assoc();
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'borrowing' => $borrowing]);
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

