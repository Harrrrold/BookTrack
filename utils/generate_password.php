<?php
/**
 * Password Generator Utility
 * Use this to generate password hashes for default users
 */

// Generate password hash
function generatePasswordHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Default passwords
$passwords = [
    'admin123' => generatePasswordHash('admin123'),
    'user123' => generatePasswordHash('user123')
];

echo "Password Hashes:\n";
echo "================\n\n";
echo "admin123: " . $passwords['admin123'] . "\n";
echo "user123: " . $passwords['user123'] . "\n\n";
echo "Update these in database/schema.sql before importing!\n";

?>

