<?php
// fix-user-emails.php
require_once __DIR__ . '/config.php';

// Add missing emails to all users
$users = [
    ['id' => 1, 'email' => 'admin@sarkarisathi.gov.np'],
    ['id' => 2, 'email' => 'ram.sharma@government.gov.np'],
    ['id' => 3, 'email' => 'sita.devi@government.gov.np']
];

foreach ($users as $user) {
    $sql = "UPDATE users SET email = ? WHERE id = ? AND (email IS NULL OR email = '')";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $user['email'], $user['id']);
    
    if ($stmt->execute()) {
        echo "Updated email for user ID {$user['id']}<br>";
    } else {
        echo "Failed to update user ID {$user['id']}: " . $conn->error . "<br>";
    }
    $stmt->close();
}

echo "Email update complete!";
?>