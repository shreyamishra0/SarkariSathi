<?php
function generateUniqueTrackingNumber($section_id, $section_name) {
    global $conn;
    
    $prefix = strtoupper(substr(preg_replace('/[^A-Z]/i', '', $section_name), 0, 3));
    $year = date('Y');
    
    $max_attempts = 10;
    $attempt = 0;
    
    while ($attempt < $max_attempts) {
        // Get next sequential number
        $stmt = $conn->prepare("
            SELECT MAX(CAST(SUBSTRING_INDEX(tracking_number, '-', -1) AS UNSIGNED)) as max_num
            FROM applications 
            WHERE tracking_number LIKE CONCAT(?, '-', ?, '-%')
        ");
        $search_pattern = $prefix;
        $stmt->bind_param("ss", $search_pattern, $year);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $next_number = ($result['max_num'] ?? 0) + 1;
        $sequential = str_pad($next_number, 5, '0', STR_PAD_LEFT);
        $tracking_number = "{$prefix}-{$year}-{$sequential}";
        
        // Check if this tracking number already exists
        $check_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE tracking_number = ?");
        $check_stmt->bind_param("s", $tracking_number);
        $check_stmt->execute();
        $exists = $check_stmt->get_result()->fetch_assoc()['count'];
        
        if ($exists == 0) {
            return $tracking_number;
        }
        
        $attempt++;
    }
    
    // Fallback: use timestamp-based unique ID
    return "{$prefix}-{$year}-" . time() . rand(10, 99);
}

function sendNotification($user_id, $type, $title, $message, $link = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, type, title, message, link, created_at) 
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    return $stmt->execute();
}

function createApplication($citizen_id, $section_id, $data) {
    global $conn;
    
    // Get section details
    $stmt = $conn->prepare("SELECT name FROM sections WHERE id = ?");
    $stmt->bind_param("i", $section_id);
    $stmt->execute();
    $section = $stmt->get_result()->fetch_assoc();
    
    if (!$section) {
        return ['success' => false, 'message' => 'Section not found'];
    }
    
    // Generate unique tracking number
    $tracking_number = generateUniqueTrackingNumber($section_id, $section['name']);
    
    // Insert application
    $stmt = $conn->prepare("
        INSERT INTO applications 
        (citizen_id, section_id, tracking_number, status, submitted_date, created_by) 
        VALUES (?, ?, ?, 'submitted', CURDATE(), ?)
    ");
    $stmt->bind_param("iisi", $citizen_id, $section_id, $tracking_number, $citizen_id);
    
    if ($stmt->execute()) {
        $application_id = $conn->insert_id;
        
        // Send notification to citizen
        sendNotification(
            $citizen_id, 
            'application_created',
            'Application Submitted',
            "Your application has been submitted. Tracking number: {$tracking_number}",
            "/citizen/track-status.php?tracking={$tracking_number}"
        );
        
        return [
            'success' => true, 
            'tracking_number' => $tracking_number,
            'application_id' => $application_id
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to create application'];
}

function isValidTrackingNumber($tracking_number) {
    // Format: ABC-YYYY-XXXXX (3 letters, 4 digit year, 5 digit sequence)
    return preg_match('/^[A-Z]{3}-\d{4}-\d{5}$/', $tracking_number);
}

function getApplicationByTracking($tracking_number, $citizen_id = null) {
    global $conn;
    
    if (!isValidTrackingNumber($tracking_number)) {
        return null;
    }
    
    if ($citizen_id) {
        // Citizen can only view their own applications
        $stmt = $conn->prepare("
            SELECT a.*, s.name as section_name, s.office_name 
            FROM applications a
            JOIN sections s ON a.section_id = s.id
            WHERE a.tracking_number = ? AND a.citizen_id = ?
        ");
        $stmt->bind_param("si", $tracking_number, $citizen_id);
    } else {
        // Officer can view all applications
        $stmt = $conn->prepare("
            SELECT a.*, s.name as section_name, s.office_name,
                   c.name as citizen_name, c.phone as citizen_phone
            FROM applications a
            JOIN sections s ON a.section_id = s.id
            JOIN users c ON a.citizen_id = c.id
            WHERE a.tracking_number = ?
        ");
        $stmt->bind_param("s", $tracking_number);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0 ? $result->fetch_assoc() : null;
}

function getUnreadCount($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM messages 
        WHERE receiver_id = ? AND is_read = 0
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result['count'] ?? 0;
}
?>