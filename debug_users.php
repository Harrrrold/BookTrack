<?php
/**
 * Debug script to check users in database
 */
require_once 'config/database.php';

header('Content-Type: text/html');

$conn = getDBConnection();
if (!$conn) {
    die("Database connection failed!");
}

echo "<h2>Users in Database</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>ID</th><th>Email</th><th>First Name</th><th>Last Name</th><th>Role</th><th>Status</th><th>Created</th></tr>";

$result = $conn->query("SELECT id, email, first_name, last_name, role, status, created_at FROM users ORDER BY id DESC");
while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . $row['id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . htmlspecialchars($row['first_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['last_name']) . "</td>";
    echo "<td>" . $row['role'] . "</td>";
    echo "<td>" . $row['status'] . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test password verification for a specific user
if (isset($_GET['test_email'])) {
    $testEmail = $_GET['test_email'];
    $stmt = $conn->prepare("SELECT id, email, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $testEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<h3>Password Hash for: " . htmlspecialchars($testEmail) . "</h3>";
        echo "<p>Hash: " . substr($user['password'], 0, 50) . "...</p>";
        echo "<p>Hash Length: " . strlen($user['password']) . "</p>";
    }
    $stmt->close();
}

closeDBConnection($conn);
?>

