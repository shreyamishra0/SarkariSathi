<?php
session_start();
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$role = isset($_POST['role']) ? trim($_POST['role']) : 'citizen';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

// Validation
if (empty($email) || empty($password)) {
    header("Location: " . BASE_URL . "/auth/login.php?error=" . urlencode("Email and password are required."));
    exit();
}

// Fetch user from database
$sql = "SELECT id, role, password_hash, name, email, office_name, phone FROM users WHERE email = ? AND role = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    header("Location: " . BASE_URL . "/auth/login.php?error=" . urlencode("Database error. Please try again."));
    exit();
}

$stmt->bind_param("ss", $email, $role);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    // Verify password
    if (password_verify($password, $user['password_hash'])) {
        // Set all session variables consistently
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['email'] = $user['email'] ?? null;
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['office_name'] = $user['office_name'] ?? null;
        
        // Backward compatibility
        if ($user['role'] === 'officer') {
            $_SESSION['officer_id'] = $user['id'];
        } elseif ($user['role'] === 'citizen') {
            $_SESSION['citizen_id'] = $user['id'];
        }
        
        // Redirect to appropriate dashboard
        header("Location: " . BASE_URL . "/" . $user['role'] . "/dashboard.php");
        exit();
    } else {
        header("Location: " . BASE_URL . "/auth/login.php?error=" . urlencode("Invalid password."));
        exit();
    }
} else {
    header("Location: " . BASE_URL . "/auth/login.php?error=" . urlencode("User not found. Please check your credentials."));
    exit();
}