<?php
require_once __DIR__ . '/../includes/admin-check.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$id = $_POST['id'] ?? null;
$status = $_POST['status'] ?? '';
$officer_response = $_POST['officer_response'] ?? '';

if (!$id || !$status || !$officer_response) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Prepare resolved_at
$resolved_at = ($status === 'resolved') ? date('Y-m-d H:i:s') : null;

// Update the complaint
$stmt = $conn->prepare("UPDATE complaints SET officer_response = ?, status = ?, resolved_at = ? WHERE id = ?");
$stmt->bind_param("sssi", $officer_response, $status, $resolved_at, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Complaint updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: '.$conn->error]);
}

$stmt->close();
$conn->close();
