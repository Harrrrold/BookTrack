<?php
/**
 * Setup Script for Default Users
 * Run this after importing the database schema
 * This will create/update default users with proper password hashes
 */

require_once '../config/database.php';

echo "Setting up default users...\n\n";

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed!\n");
}

// Default users data
$defaultUsers = [
    [
        'first_name' => 'Admin',
        'last_name' => 'User',
        'email' => 'admin@booktrack.com',
        'password' => 'admin123',
        'role' => 'library_admin',
        'status' => 'active'
    ],
    [
        'first_name' => 'Regular',
        'last_name' => 'User',
        'email' => 'user@booktrack.com',
        'password' => 'user123',
        'role' => 'user',
        'status' => 'active'
    ]
];

foreach ($defaultUsers as $userData) {
    // Hash password
    $hashedPassword = password_hash($userData['password'], PASSWORD_DEFAULT);
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param("s", $userData['email']);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing user
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, status = ? WHERE email = ?");
        $updateStmt->bind_param("sss", $hashedPassword, $userData['status'], $userData['email']);
        if ($updateStmt->execute()) {
            echo "✓ Updated user: {$userData['email']}\n";
        } else {
            echo "✗ Failed to update user: {$userData['email']} - {$updateStmt->error}\n";
        }
        $updateStmt->close();
    } else {
        // Insert new user
        $insertStmt = $conn->prepare("INSERT INTO users (first_name, last_name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
        $insertStmt->bind_param("ssssss", 
            $userData['first_name'],
            $userData['last_name'],
            $userData['email'],
            $hashedPassword,
            $userData['role'],
            $userData['status']
        );
        if ($insertStmt->execute()) {
            echo "✓ Created user: {$userData['email']}\n";
        } else {
            echo "✗ Failed to create user: {$userData['email']} - {$insertStmt->error}\n";
        }
        $insertStmt->close();
    }
    $checkStmt->close();
}

closeDBConnection($conn);

echo "\nSetup complete!\n";
echo "Default credentials:\n";
echo "Admin: admin@booktrack.com / admin123\n";
echo "User: user@booktrack.com / user123\n";

?>

