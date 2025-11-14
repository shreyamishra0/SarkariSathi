<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$booking_id = isset($input['booking_id']) ? intval($input['booking_id']) : 0;

if ($booking_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
    exit;
}

// Verify booking belongs to this citizen
$check = $conn->prepare("SELECT id FROM queue WHERE id = ? AND citizen_id = ? AND status = 'booked'");
$check->bind_param("ii", $booking_id, $_SESSION['user_id']);
$check->execute();

if ($check->get_result()->num_rows == 0) {
    echo json_encode(['success' => false, 'message' => 'Booking not found or cannot be cancelled']);
    exit;
}

// Update status to no_show (cancelled)
$update = $conn->prepare("UPDATE queue SET status = 'no_show' WHERE id = ?");
$update->bind_param("i", $booking_id);

if ($update->execute()) {
    echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel booking']);
}
?>