<?php
require_once __DIR__ . '/../includes/admin-check.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = intval($_POST['id'] ?? 0);
$name = trim($_POST['name'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$email = trim($_POST['email'] ?? '');
$office_name = trim($_POST['office_name'] ?? '');

// Validate input
if (!$id || !$name || !$phone || !$email) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Update DB
$stmt = $conn->prepare("UPDATE users SET name = ?, phone = ?, email = ?, office_name = ? WHERE id = ?");
$stmt->bind_param("ssssi", $name, $phone, $email, $office_name, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update user']);
}

$stmt->close();
