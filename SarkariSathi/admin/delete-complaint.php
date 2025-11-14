<?php
require_once __DIR__ . '/../includes/admin-check.php';
header('Content-Type: application/json');

// Read JSON input
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid complaint ID']);
    exit;
}

// Delete the complaint
$stmt = $conn->prepare("DELETE FROM complaints WHERE id = ?");
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Complaint deleted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: '.$conn->error]);
}

$stmt->close();
$conn->close();
