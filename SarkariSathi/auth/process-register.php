<?php
// auth/process-register.php - Updated with required email validation
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: " . BASE_URL . "/auth/register-citizen.php");
    exit();
}

// --- Collect and sanitize input ---
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';
$confirm = isset($_POST['confirm']) ? $_POST['confirm'] : '';
$role = isset($_POST['role']) ? $_POST['role'] : 'citizen';
$office = isset($_POST['office']) ? trim($_POST['office']) : null;

// --- Validation ---
if ($name === '' || $phone === '' || $email === '' || $password === '' || $confirm === '') {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("All required fields must be filled."));
    exit();
}

// Email validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Please enter a valid email address."));
    exit();
}

if ($password !== $confirm) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Passwords do not match."));
    exit();
}

// Nepalese phone number validation
if (!preg_match('/^98[0-9]{8}$/', $phone)) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Phone number must be a valid Nepali number (98XXXXXXXX)."));
    exit();
}

// Password length validation
if (strlen($password) < 6) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Password must be at least 6 characters long."));
    exit();
}

// --- Check DB connection ---
if (!isset($conn) || !($conn instanceof mysqli)) {
    die("Database connection error. \$conn is not set or is not a mysqli instance.");
}

// --- Check for duplicate phone ---
$checkSql = "SELECT id FROM users WHERE phone = ?";
$checkStmt = $conn->prepare($checkSql);
if ($checkStmt === false) {
    die("Prepare failed (check duplicate phone): " . htmlspecialchars($conn->error));
}
$checkStmt->bind_param("s", $phone);
$checkStmt->execute();
$checkRes = $checkStmt->get_result();
if ($checkRes && $checkRes->num_rows > 0) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Phone number already registered."));
    exit();
}
$checkStmt->close();

// --- Check for duplicate email ---
$checkEmailSql = "SELECT id FROM users WHERE email = ?";
$checkEmailStmt = $conn->prepare($checkEmailSql);
if ($checkEmailStmt === false) {
    die("Prepare failed (check duplicate email): " . htmlspecialchars($conn->error));
}
$checkEmailStmt->bind_param("s", $email);
$checkEmailStmt->execute();
$checkEmailRes = $checkEmailStmt->get_result();
if ($checkEmailRes && $checkEmailRes->num_rows > 0) {
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Email address already registered."));
    exit();
}
$checkEmailStmt->close();

// --- Hash password ---
$hashed = password_hash($password, PASSWORD_DEFAULT);

// --- Verification flag ---
$is_verified = ($role === 'citizen') ? 1 : 0;

// --- Handle optional fields ---
if ($office === '') $office = null;

// --- Insert into users table ---
$insertSql = "INSERT INTO users (role, phone, name, email, password_hash, office_name, is_verified) 
              VALUES (?, ?, ?, ?, ?, ?, ?)";
$insertStmt = $conn->prepare($insertSql);

if ($insertStmt === false) {
    die("Prepare failed (insert): " . htmlspecialchars($conn->error));
}

$insertStmt->bind_param(
    "ssssssi", // role, phone, name, email, password_hash, office_name, is_verified
    $role,
    $phone,
    $name,
    $email,
    $hashed,
    $office,
    $is_verified
);

$execOk = $insertStmt->execute();

if ($execOk) {
    $insertStmt->close();
    if ($role === 'citizen') {
        header("Location: " . BASE_URL . "/auth/login.php?success=" . urlencode("Registration successful! You can now log in."));
        exit();
    } else {
        header("Location: " . BASE_URL . "/auth/login.php?success=" . urlencode("Registration successful! Please wait for admin verification."));
        exit();
    }
} else {
    $err = $insertStmt->error ?: $conn->error;
    $insertStmt->close();
    header("Location: " . BASE_URL . "/auth/register-{$role}.php?error=" . urlencode("Registration failed: " . $err));
    exit();
}