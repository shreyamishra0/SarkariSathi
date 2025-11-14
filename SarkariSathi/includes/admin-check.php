<?php
// includes/admin-check.php - Admin authentication check
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Please login as admin to access this page."));
    exit();
}

// Optional: Verify admin still exists in database (security check)
$admin_id = $_SESSION['user_id'];
$verify_stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
if ($verify_stmt) {
    $verify_stmt->bind_param("i", $admin_id);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Admin account no longer exists, clear session
        session_unset();
        session_destroy();
        header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Your session has expired. Please login again."));
        exit();
    }
    $verify_stmt->close();
}