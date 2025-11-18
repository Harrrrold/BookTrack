<?php
/**
 * Database Configuration File
 * BookTrack Application
 * 
 * For production, create config/database.local.php or set environment variables
 */

// Check for environment variables (for production deployment)
$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';
$dbName = getenv('DB_NAME') ?: 'booktrack';

// Try to load local config file (not in git)
if (file_exists(__DIR__ . '/database.local.php')) {
    require_once __DIR__ . '/database.local.php';
} else {
    // Use environment variables or defaults
    define('DB_HOST', $dbHost);
    define('DB_USER', $dbUser);
    define('DB_PASS', $dbPass);
    define('DB_NAME', $dbName);
}

// Create database connection
function getDBConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, 3307);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        // Set charset to utf8mb4
        $conn->set_charset("utf8mb4");
        
        return $conn;
    } catch (Exception $e) {
        error_log("Database connection error: " . $e->getMessage());
        return null;
    }
}

// Close database connection
function closeDBConnection($conn) {
    if ($conn) {
        $conn->close();
    }
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings for production
    if (getenv('APP_ENV') === 'production') {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.use_only_cookies', 1);
    }
    session_start();
}

?>
