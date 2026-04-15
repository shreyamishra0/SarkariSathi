<?php
session_start();
require_once __DIR__ . '/../config.php';

// Only allow officers to access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$phone = $input['phone'] ?? '';

if (empty($phone)) {
    echo json_encode(['exists' => false]);
    exit();
}

// Check if citizen exists
$stmt = $conn->prepare("SELECT id, name FROM users WHERE phone = ? AND role = 'citizen'");
$stmt->bind_param("s", $phone);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $citizen = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'id' => $citizen['id'],
        'name' => $citizen['name']
    ]);
} else {
    echo json_encode(['exists' => false]);
}
