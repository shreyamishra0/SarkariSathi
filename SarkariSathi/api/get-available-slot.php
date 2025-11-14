<?php
/**
 * API: Get Available Time Slots
 * Returns available booking slots for a section on a specific date
 */

session_start();
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Get parameters
$section_id = isset($_GET['section_id']) ? intval($_GET['section_id']) : 0;
$date = isset($_GET['date']) ? $_GET['date'] : '';

// Validate inputs
if ($section_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid section ID']);
    exit;
}

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    echo json_encode(['success' => false, 'message' => 'Invalid date format']);
    exit;
}

// Check if date is in the past
$today = date('Y-m-d');
if ($date < $today) {
    echo json_encode(['success' => false, 'message' => 'Cannot book dates in the past']);
    exit;
}

// Check if date is too far in the future (max 7 days)
$max_date = date('Y-m-d', strtotime('+7 days'));
if ($date > $max_date) {
    echo json_encode(['success' => false, 'message' => 'Cannot book more than 7 days in advance']);
    exit;
}

// Define time slots (9 AM to 5 PM, 30-minute intervals)
$time_slots = [
    '09:00:00' => '9:00 AM - 9:30 AM',
    '09:30:00' => '9:30 AM - 10:00 AM',
    '10:00:00' => '10:00 AM - 10:30 AM',
    '10:30:00' => '10:30 AM - 11:00 AM',
    '11:00:00' => '11:00 AM - 11:30 AM',
    '11:30:00' => '11:30 AM - 12:00 PM',
    '12:00:00' => '12:00 PM - 12:30 PM',
    '12:30:00' => '12:30 PM - 1:00 PM',
    '13:00:00' => '1:00 PM - 1:30 PM',
    '13:30:00' => '1:30 PM - 2:00 PM',
    '14:00:00' => '2:00 PM - 2:30 PM',
    '14:30:00' => '2:30 PM - 3:00 PM',
    '15:00:00' => '3:00 PM - 3:30 PM',
    '15:30:00' => '3:30 PM - 4:00 PM',
    '16:00:00' => '4:00 PM - 4:30 PM',
    '16:30:00' => '4:30 PM - 5:00 PM',
];

// Maximum bookings per slot
$max_per_slot = 5;

try {
    // Get existing bookings for this date and section
    $stmt = $conn->prepare("
        SELECT time_slot, COUNT(*) as booked_count 
        FROM queue 
        WHERE section_id = ? 
        AND queue_date = ? 
        AND status NOT IN ('no_show', 'completed')
        GROUP BY time_slot
    ");
    
    if ($stmt === false) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("is", $section_id, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $booked_slots = [];
    while ($row = $result->fetch_assoc()) {
        $booked_slots[$row['time_slot']] = intval($row['booked_count']);
    }
    $stmt->close();
    
    // Calculate availability for each slot
    $available_slots = [];
    
    foreach ($time_slots as $time => $display) {
        $booked = isset($booked_slots[$time]) ? $booked_slots[$time] : 0;
        $available = $max_per_slot - $booked;
        
        // If booking for today, skip past time slots
        if ($date === $today) {
            $current_time = date('H:i:s');
            if ($time < $current_time) {
                continue; // Skip past time slots
            }
        }
        
        $available_slots[] = [
            'time_slot' => $time,
            'time_display' => $display,
            'booked' => $booked,
            'available' => max(0, $available), // Ensure non-negative
            'max' => $max_per_slot,
            'is_available' => $available > 0
        ];
    }
    
    echo json_encode([
        'success' => true,
        'date' => $date,
        'section_id' => $section_id,
        'slots' => $available_slots,
        'total_slots' => count($available_slots)
    ]);
    
} catch (Exception $e) {
    error_log("Get slots error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching time slots'
    ]);
}
?>