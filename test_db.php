<?php
/**
 * Quick Database Connection Test
 * Use this to verify your database setup is working
 */

require_once 'config/database.php';

echo "<h2>BookTrack Database Connection Test</h2>";
echo "<pre>";

$conn = getDBConnection();
if ($conn) {
    echo "✓ Database connection successful!\n";
    echo "Database: " . DB_NAME . "\n\n";
    
    // Test query - check tables
    $tables = ['users', 'books', 'categories', 'borrowings', 'reservations', 'bookmarks', 'notifications', 'system_logs'];
    echo "Checking tables...\n";
    foreach ($tables as $table) {
        $result = $conn->query("SHOW TABLES LIKE '$table'");
        if ($result && $result->num_rows > 0) {
            echo "  ✓ Table '$table' exists\n";
        } else {
            echo "  ✗ Table '$table' missing\n";
        }
    }
    
    echo "\n";
    
    // Check categories
    $result = $conn->query("SELECT COUNT(*) as count FROM categories");
    $row = $result->fetch_assoc();
    echo "Categories: " . $row['count'] . "\n";
    
    // Check books
    $result = $conn->query("SELECT COUNT(*) as count FROM books");
    $row = $result->fetch_assoc();
    echo "Books: " . $row['count'] . "\n";
    
    // Check users
    $result = $conn->query("SELECT COUNT(*) as count FROM users");
    $row = $result->fetch_assoc();
    echo "Users: " . $row['count'] . "\n";
    
    echo "\n";
    echo "Database setup looks good! Now run the setup script to create default users.\n";
    echo "Visit: <a href='setup/setup_default_users.php'>setup/setup_default_users.php</a>\n";
    
    closeDBConnection($conn);
} else {
    echo "✗ Database connection failed!\n";
    echo "Please check:\n";
    echo "1. MySQL is running in XAMPP\n";
    echo "2. Database credentials in config/database.php\n";
    echo "3. Database 'booktrack' exists\n";
}

echo "</pre>";

?>

