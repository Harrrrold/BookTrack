<?php
/**
 * Bookmarks API Endpoints
 * Handles user bookmarks
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
$bookId = $_GET['book_id'] ?? null;

switch ($method) {
    case 'GET':
        getBookmarks();
        break;
        
    case 'POST':
        if ($bookId) {
            addBookmark($bookId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Book ID required']);
        }
        break;
        
    case 'DELETE':
        if ($bookId) {
            removeBookmark($bookId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Book ID required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getBookmarks() {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT bm.id, bm.created_at, bk.*, c.name as category_name 
                           FROM bookmarks bm 
                           JOIN books bk ON bm.book_id = bk.id 
                           LEFT JOIN categories c ON bk.category_id = c.id 
                           WHERE bm.user_id = ? 
                           ORDER BY bm.created_at DESC");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $bookmarks = [];
    while ($row = $result->fetch_assoc()) {
        $bookmarks[] = [
            'id' => (int)$row['id'],
            'book_id' => (int)$row['book_id'],
            'title' => $row['title'],
            'author' => $row['author'],
            'isbn' => $row['isbn'],
            'category' => $row['category_name'],
            'cover_image' => $row['cover_image'],
            'availability' => $row['availability'],
            'rating' => (float)$row['rating'],
            'created_at' => $row['created_at']
        ];
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'bookmarks' => $bookmarks]);
}

function addBookmark($bookId) {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Check if book exists
    $checkStmt = $conn->prepare("SELECT id, title FROM books WHERE id = ?");
    $checkStmt->bind_param("i", $bookId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        $checkStmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $book = $result->fetch_assoc();
    $checkStmt->close();
    
    // Check if bookmark already exists
    $existStmt = $conn->prepare("SELECT id FROM bookmarks WHERE user_id = ? AND book_id = ?");
    $existStmt->bind_param("ii", $userId, $bookId);
    $existStmt->execute();
    $existResult = $existStmt->get_result();
    
    if ($existResult->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Book already bookmarked']);
        $existStmt->close();
        closeDBConnection($conn);
        return;
    }
    $existStmt->close();
    
    // Add bookmark
    $insertStmt = $conn->prepare("INSERT INTO bookmarks (user_id, book_id) VALUES (?, ?)");
    $insertStmt->bind_param("ii", $userId, $bookId);
    
    if ($insertStmt->execute()) {
        $bookmarkId = $conn->insert_id;
        
        // Log activity
        logSystemActivity($conn, $userId, "Book bookmarked: " . $book['title'], 'info');
        
        $insertStmt->close();
        closeDBConnection($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Book bookmarked successfully',
            'bookmark_id' => $bookmarkId
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add bookmark: ' . $insertStmt->error]);
        $insertStmt->close();
        closeDBConnection($conn);
    }
}

function removeBookmark($bookId) {
    global $userId;
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Get book title for logging
    $bookStmt = $conn->prepare("SELECT bk.title FROM bookmarks bm JOIN books bk ON bm.book_id = bk.id WHERE bm.user_id = ? AND bm.book_id = ?");
    $bookStmt->bind_param("ii", $userId, $bookId);
    $bookStmt->execute();
    $bookResult = $bookStmt->get_result();
    
    if ($bookResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Bookmark not found']);
        $bookStmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $book = $bookResult->fetch_assoc();
    $bookStmt->close();
    
    // Remove bookmark
    $deleteStmt = $conn->prepare("DELETE FROM bookmarks WHERE user_id = ? AND book_id = ?");
    $deleteStmt->bind_param("ii", $userId, $bookId);
    
    if ($deleteStmt->execute()) {
        // Log activity
        logSystemActivity($conn, $userId, "Bookmark removed: " . $book['title'], 'info');
        
        $deleteStmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Bookmark removed successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to remove bookmark: ' . $deleteStmt->error]);
        $deleteStmt->close();
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

