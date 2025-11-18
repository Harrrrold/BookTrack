<?php
/**
 * Books API Endpoints
 * Handles book CRUD operations and search
 */

header('Content-Type: application/json');
require_once '../config/database.php';

// Check authentication for protected endpoints
// Session is already started in database.php
$isAuthenticated = isset($_SESSION['is_logged_in']) && $_SESSION['is_logged_in'] === true;
$userRole = $_SESSION['user_role'] ?? 'user';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$bookId = $_GET['id'] ?? null;

switch ($method) {
    case 'GET':
        if ($bookId) {
            getBook($bookId);
        } elseif ($action === 'search') {
            searchBooks();
        } else {
            getBooks();
        }
        break;
        
    case 'POST':
        if (!$isAuthenticated || ($userRole !== 'library_admin' && $userRole !== 'library_moderator')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            break;
        }
        createBook();
        break;
        
    case 'PUT':
        if (!$isAuthenticated || ($userRole !== 'library_admin' && $userRole !== 'library_moderator')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            break;
        }
        if ($bookId) {
            updateBook($bookId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Book ID required']);
        }
        break;
        
    case 'DELETE':
        if (!$isAuthenticated || ($userRole !== 'library_admin' && $userRole !== 'library_moderator')) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            break;
        }
        if ($bookId) {
            deleteBook($bookId);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Book ID required']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}

function getBooks() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            ORDER BY b.created_at DESC 
            LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $limit, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = formatBook($row);
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'books' => $books]);
}

function getBook($id) {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("SELECT b.*, c.name as category_name FROM books b LEFT JOIN categories c ON b.category_id = c.id WHERE b.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Book not found']);
        $stmt->close();
        closeDBConnection($conn);
        return;
    }
    
    $book = formatBook($result->fetch_assoc());
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'book' => $book]);
}

function searchBooks() {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $query = $_GET['q'] ?? '';
    $category = $_GET['category'] ?? '';
    $availability = $_GET['availability'] ?? '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    
    $sql = "SELECT b.*, c.name as category_name 
            FROM books b 
            LEFT JOIN categories c ON b.category_id = c.id 
            WHERE 1=1";
    $params = [];
    $types = "";
    
    if (!empty($query)) {
        $sql .= " AND (b.title LIKE ? OR b.author LIKE ? OR b.isbn LIKE ? OR b.description LIKE ?)";
        $searchTerm = "%$query%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $types .= "ssss";
    }
    
    if (!empty($category)) {
        $sql .= " AND c.name = ?";
        $params[] = $category;
        $types .= "s";
    }
    
    if (!empty($availability)) {
        $sql .= " AND b.availability = ?";
        $params[] = $availability;
        $types .= "s";
    }
    
    $sql .= " ORDER BY b.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $books = [];
    while ($row = $result->fetch_assoc()) {
        $books[] = formatBook($row);
    }
    
    $stmt->close();
    closeDBConnection($conn);
    
    echo json_encode(['success' => true, 'books' => $books, 'count' => count($books)]);
}

function createBook() {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $title = $data['title'] ?? '';
    $author = $data['author'] ?? '';
    $isbn = $data['isbn'] ?? null;
    $categoryId = $data['category_id'] ?? null;
    $genre = $data['genre'] ?? null;
    $description = $data['description'] ?? null;
    $coverImage = $data['cover_image'] ?? null;
    $publisher = $data['publisher'] ?? null;
    $publishYear = $data['publish_year'] ?? null;
    $pages = $data['pages'] ?? null;
    $language = $data['language'] ?? null;
    $location = $data['location'] ?? null;
    $callNumber = $data['call_number'] ?? null;
    $availability = $data['availability'] ?? 'available';
    
    if (empty($title) || empty($author)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and author are required']);
        return;
    }
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    $stmt = $conn->prepare("INSERT INTO books (title, author, isbn, category_id, genre, description, cover_image, publisher, publish_year, pages, language, location, call_number, availability) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssissssiiisss", $title, $author, $isbn, $categoryId, $genre, $description, $coverImage, $publisher, $publishYear, $pages, $language, $location, $callNumber, $availability);
    
    if ($stmt->execute()) {
        $bookId = $conn->insert_id;
        
        // Log activity
        $userId = $_SESSION['user_id'];
        logSystemActivity($conn, $userId, "Book created: $title", 'info');
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Book created successfully', 'book_id' => $bookId]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create book: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function updateBook($id) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Build update query dynamically
    $updates = [];
    $params = [];
    $types = "";
    
    $fields = ['title', 'author', 'isbn', 'category_id', 'genre', 'description', 'cover_image', 
               'publisher', 'publish_year', 'pages', 'language', 'location', 'call_number', 'availability'];
    
    foreach ($fields as $field) {
        if (isset($data[$field])) {
            $updates[] = "$field = ?";
            $params[] = $data[$field];
            $types .= is_int($data[$field]) ? "i" : "s";
        }
    }
    
    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No fields to update']);
        closeDBConnection($conn);
        return;
    }
    
    $params[] = $id;
    $types .= "i";
    $sql = "UPDATE books SET " . implode(", ", $updates) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        // Log activity
        $userId = $_SESSION['user_id'];
        logSystemActivity($conn, $userId, "Book updated: ID $id", 'info');
        
        $stmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Book updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update book: ' . $stmt->error]);
        $stmt->close();
        closeDBConnection($conn);
    }
}

function deleteBook($id) {
    $conn = getDBConnection();
    if (!$conn) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database connection failed']);
        return;
    }
    
    // Get book title for logging
    $stmt = $conn->prepare("SELECT title FROM books WHERE id = ?");
    $stmt->bind_param("i", $id);
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
    $stmt->close();
    
    // Delete book
    $deleteStmt = $conn->prepare("DELETE FROM books WHERE id = ?");
    $deleteStmt->bind_param("i", $id);
    
    if ($deleteStmt->execute()) {
        // Log activity
        $userId = $_SESSION['user_id'];
        logSystemActivity($conn, $userId, "Book deleted: " . $book['title'], 'warning');
        
        $deleteStmt->close();
        closeDBConnection($conn);
        
        echo json_encode(['success' => true, 'message' => 'Book deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete book: ' . $deleteStmt->error]);
        $deleteStmt->close();
        closeDBConnection($conn);
    }
}

function formatBook($row) {
    return [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'author' => $row['author'],
        'isbn' => $row['isbn'],
        'category' => $row['category_name'] ?? null,
        'category_id' => $row['category_id'] ? (int)$row['category_id'] : null,
        'genre' => $row['genre'],
        'description' => $row['description'],
        'cover' => $row['cover_image'],
        'publisher' => $row['publisher'],
        'publish_year' => $row['publish_year'] ? (int)$row['publish_year'] : null,
        'pages' => $row['pages'] ? (int)$row['pages'] : null,
        'language' => $row['language'],
        'location' => $row['location'],
        'call_number' => $row['call_number'],
        'availability' => $row['availability'],
        'rating' => $row['rating'] ? (float)$row['rating'] : 0.0,
        'reviews' => (int)$row['total_reviews'],
        'total_borrows' => (int)$row['total_borrows'],
        'added_date' => $row['added_date'],
        'last_borrowed' => $row['last_borrowed'],
        'created_at' => $row['created_at']
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

