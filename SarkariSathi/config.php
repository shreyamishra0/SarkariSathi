<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Database connection parameters
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sarkari_connect');
if (!defined('BASE_URL')) {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Detect project root folder automatically
    $scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

    // If your project folder name is 'sarkariSathi', force it
    // optional: remove trailing /auth, /citizen, /whatever dynamically
    $projectFolder = '/sarkariSathi';
    if (strpos($scriptDir, $projectFolder) !== false) {
        $scriptDir = $projectFolder;
    } else {
        $scriptDir = ''; // fallback to root
    }

    define('BASE_URL', $proto . '://' . $host . $scriptDir);
}

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4 for proper emoji and special character support
$conn->set_charset("utf8mb4");

// Optional: Set timezone
date_default_timezone_set('Asia/Kathmandu');

// Optional: Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);