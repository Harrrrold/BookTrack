<?php
/**
 * Dashboard API Endpoints
 * Provides statistics and data for user and admin dashboards
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
$type = $_GET['type'] ?? 'user';

if ($type === 'admin' && $userRole !== 'admin' && $userRole !== 'library_admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$conn = getDBConnection();
if (!$conn) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($type === 'admin') {
    getAdminDashboard($conn);
} else {
    getUserDashboard($conn);
}

function getUserDashboard($conn) {
    global $userId;
    
    // Get user's borrowing stats
    $stmt = $conn->prepare("SELECT 
        COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as reading_books,
        COUNT(CASE WHEN status = 'returned' THEN 1 END) as completed_books
        FROM borrowings WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $borrowStats = $result->fetch_assoc();
    $stmt->close();
    
    // Get wishlist count (bookmarks)
    $stmt = $conn->prepare("SELECT COUNT(*) as wishlist_books FROM bookmarks WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $wishlistStats = $result->fetch_assoc();
    $stmt->close();
    
    // Get total books count
    $totalBooks = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];
    
    // Get recent activity (borrowings and reservations)
    $stmt = $conn->prepare("SELECT 
        'borrow' as type,
        b.borrowed_date as date,
        bk.title,
        bk.id as book_id,
        CONCAT('Borrowed ', bk.title) as description
        FROM borrowings b
        JOIN books bk ON b.book_id = bk.id
        WHERE b.user_id = ? AND b.borrowed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        UNION ALL
        
        SELECT 
        'reservation' as type,
        DATE(r.reserved_date) as date,
        bk.title,
        bk.id as book_id,
        CONCAT('Reserved ', bk.title) as description
        FROM reservations r
        JOIN books bk ON r.book_id = bk.id
        WHERE r.user_id = ? AND r.status != 'cancelled' AND r.reserved_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        
        ORDER BY date DESC
        LIMIT 10");
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $recentActivity = [];
    while ($row = $result->fetch_assoc()) {
        $recentActivity[] = [
            'type' => $row['type'],
            'date' => $row['date'],
            'title' => $row['title'],
            'book_id' => (int)$row['book_id'],
            'description' => $row['description']
        ];
    }
    $stmt->close();
    
    // Get overdue books
    $stmt = $conn->prepare("SELECT COUNT(*) as overdue_count 
                           FROM borrowings 
                           WHERE user_id = ? 
                           AND status = 'borrowed' 
                           AND due_date < CURDATE()");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $overdue = $result->fetch_assoc();
    $stmt->close();
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'total_books' => (int)$totalBooks,
            'reading_books' => (int)$borrowStats['reading_books'],
            'completed_books' => (int)$borrowStats['completed_books'],
            'wishlist_books' => (int)$wishlistStats['wishlist_books'],
            'overdue_count' => (int)$overdue['overdue_count'],
            'recent_activity' => $recentActivity
        ]
    ]);
}

function getAdminDashboard($conn) {
    // Total users
    $totalUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'user'")->fetch_assoc()['total'];
    
    // Active users (logged in within last 30 days)
    $activeUsers = $conn->query("SELECT COUNT(*) as total FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND role = 'user'")->fetch_assoc()['total'];
    
    // Total books
    $totalBooks = $conn->query("SELECT COUNT(*) as total FROM books")->fetch_assoc()['total'];
    
    // Pending requests (reservations pending)
    $pendingRequests = $conn->query("SELECT COUNT(*) as total FROM reservations WHERE status = 'pending'")->fetch_assoc()['total'];
    
    // Active borrowings
    $activeBorrowings = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'borrowed'")->fetch_assoc()['total'];
    
    // Overdue books
    $overdueBooks = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'borrowed' AND due_date < CURDATE()")->fetch_assoc()['total'];
    
    // Recent system activity (logs)
    $stmt = $conn->prepare("SELECT sl.*, u.first_name, u.last_name, u.email 
                           FROM system_logs sl 
                           LEFT JOIN users u ON sl.user_id = u.id 
                           ORDER BY sl.created_at DESC 
                           LIMIT 10");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $systemActivity = [];
    while ($row = $result->fetch_assoc()) {
        $systemActivity[] = [
            'id' => (int)$row['id'],
            'user_id' => $row['user_id'] ? (int)$row['user_id'] : null,
            'user_name' => $row['first_name'] ? trim($row['first_name'] . ' ' . $row['last_name']) : 'System',
            'user_email' => $row['email'],
            'action' => $row['action'],
            'details' => $row['details'],
            'level' => $row['level'],
            'timestamp' => $row['created_at'],
            'ip_address' => $row['ip_address']
        ];
    }
    $stmt->close();
    
    // Get categories distribution
    $categoriesStmt = $conn->query("SELECT c.name, COUNT(b.id) as count 
                                    FROM categories c 
                                    LEFT JOIN books b ON c.id = b.category_id 
                                    GROUP BY c.id, c.name 
                                    ORDER BY count DESC");
    $categories = [];
    while ($row = $categoriesStmt->fetch_assoc()) {
        $categories[] = [
            'name' => $row['name'],
            'count' => (int)$row['count']
        ];
    }
    
    closeDBConnection($conn);
    
    echo json_encode([
        'success' => true,
        'dashboard' => [
            'total_users' => (int)$totalUsers,
            'active_users' => (int)$activeUsers,
            'total_books' => (int)$totalBooks,
            'active_borrowings' => (int)$activeBorrowings,
            'pending_requests' => (int)$pendingRequests,
            'overdue_books' => (int)$overdueBooks,
            'system_activity' => $systemActivity,
            'categories' => $categories
        ]
    ]);
}

?>

