<?php
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/auth/admin-login.php");
    exit();
}

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation
if (empty($email) || empty($password)) {
    header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Email and password are required."));
    exit();
}

// Fetch admin user from database
$sql = "SELECT id, role, password_hash, name, email FROM users WHERE email = ? AND role = 'admin'";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Database error. Please try again."));
    exit();
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        // Set session variables for admin
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = 'admin';
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'];
        
        // Log admin login (optional but recommended for security)
        $log_stmt = $conn->prepare("INSERT INTO admin_logs (admin_id, action, ip_address, created_at) VALUES (?, 'login', ?, NOW())");
        if ($log_stmt) {
            $action = 'login';
            $ip = $_SERVER['REMOTE_ADDR'];
            $log_stmt->bind_param("is", $user['id'], $ip);
            $log_stmt->execute();
            $log_stmt->close();
        }
        
        // Redirect to admin dashboard
        header("Location: " . BASE_URL . "/admin/dashboard.php");
        exit();
    } else {
        header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Invalid password."));
        exit();
    }
} else {
    header("Location: " . BASE_URL . "/auth/admin-login.php?error=" . urlencode("Admin account not found."));
    exit();
}