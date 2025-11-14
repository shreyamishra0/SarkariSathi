<?php
session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid request data']);
    exit;
}

// Validate required fields
$citizen_id = $_SESSION['user_id'];
$section_id = isset($input['section_id']) ? intval($input['section_id']) : 0;
$visit_type = isset($input['visit_type']) ? $input['visit_type'] : '';
$queue_date = isset($input['queue_date']) ? $input['queue_date'] : '';
$time_slot = isset($input['time_slot']) ? $input['time_slot'] : '';
$tracking_number = isset($input['tracking_number']) ? trim($input['tracking_number']) : null;

// Validation
if ($section_id <= 0 || empty($visit_type) || empty($queue_date) || empty($time_slot)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate visit type
if (!in_array($visit_type, ['submission', 'pickup', 'inquiry'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid visit type']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $queue_date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Validate time format
if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time_slot)) {
    echo json_encode(['success' => false, 'message' => 'Invalid time format']);
    exit;
}

try {
    // Check if section exists
    $section_check = $conn->prepare("SELECT name FROM sections WHERE id = ? AND is_active = TRUE");
    $section_check->bind_param("i", $section_id);
    $section_check->execute();
    $section_result = $section_check->get_result();
    
    if ($section_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Service not found or inactive']);
        exit;
    }
    
    $section_name = $section_result->fetch_assoc()['name'];
    $section_check->close();
    
    // Check slot availability
    $slot_check = $conn->prepare("
        SELECT COUNT(*) as booked 
        FROM queue 
        WHERE section_id = ? AND queue_date = ? AND time_slot = ? 
        AND status NOT IN ('no_show', 'completed')
    ");
    $slot_check->bind_param("iss", $section_id, $queue_date, $time_slot);
    $slot_check->execute();
    $slot_result = $slot_check->get_result();
    $booked = $slot_result->fetch_assoc()['booked'];
    $slot_check->close();
    
    // Max 5 bookings per slot
    $max_per_slot = 5;
    if ($booked >= $max_per_slot) {
        echo json_encode(['success' => false, 'message' => 'This time slot is fully booked. Please choose another time.']);
        exit;
    }
    
    // Generate queue number
    $queue_number = generateQueueNumber($section_id, $queue_date, $conn);
    
    // Get application ID for pickup (if applicable)
    $application_id = null;
    if ($visit_type === 'pickup' && !empty($tracking_number)) {
        $app_stmt = $conn->prepare("SELECT id FROM applications WHERE tracking_number = ? AND citizen_id = ?");
        $app_stmt->bind_param("si", $tracking_number, $citizen_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        
        if ($app_result->num_rows > 0) {
            $application_id = $app_result->fetch_assoc()['id'];
        }
        $app_stmt->close();
    }
    
    // Insert booking
    $insert_stmt = $conn->prepare("
        INSERT INTO queue (citizen_id, section_id, visit_type, queue_date, time_slot, queue_number, application_id, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'booked')
    ");
    
    $insert_stmt->bind_param("iissssi", $citizen_id, $section_id, $visit_type, $queue_date, $time_slot, $queue_number, $application_id);
    
    if ($insert_stmt->execute()) {
        $booking_id = $conn->insert_id;
        $insert_stmt->close();
        
        // Send notification
        $notification_message = "Your queue booking #{$queue_number} is confirmed for {$queue_date} at " . date('h:i A', strtotime($time_slot));
        $notification_stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, created_at) 
            VALUES (?, 'booking', 'Queue Booking Confirmed', ?, NOW())
        ");
        $notification_stmt->bind_param("is", $citizen_id, $notification_message);
        $notification_stmt->execute();
        $notification_stmt->close();
        
        echo json_encode([
            'success' => true, 
            'queue_number' => $queue_number,
            'booking_id' => $booking_id,
            'message' => 'Booking confirmed successfully'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create booking: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    error_log("Queue booking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
}

// Function to generate queue number
function generateQueueNumber($section_id, $date, $conn) {
    // Get the count of bookings for this section on this date
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM queue 
        WHERE section_id = ? AND queue_date = ?
    ");
    $stmt->bind_param("is", $section_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['count'];
    $stmt->close();
    
    // Generate queue number: Q + section_id + count+1
    $number = $count + 1;
    $queue_number = sprintf("Q%d-%03d", $section_id, $number);
    
    // Check if this number already exists (collision check)
    $check_stmt = $conn->prepare("
        SELECT COUNT(*) as exists_count 
        FROM queue 
        WHERE queue_number = ? AND queue_date = ?
    ");
    $check_stmt->bind_param("ss", $queue_number, $date);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $exists = $check_result->fetch_assoc()['exists_count'];
    $check_stmt->close();
    
    // If collision, add timestamp to make unique
    if ($exists > 0) {
        $queue_number = sprintf("Q%d-%03d-%s", $section_id, $number, substr(time(), -4));
    }
    
    return $queue_number;
}
?>