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
    
    // Get the directory path
    $scriptName = $_SERVER['SCRIPT_NAME'];
    $scriptDir = dirname($scriptName);
    
    // Remove trailing slashes and normalize
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    
    // If we're in a subdirectory like /citizen or /officer, go up one level
    if (preg_match('#/(citizen|officer|admin|auth|api)$#', $scriptDir)) {
        $scriptDir = dirname($scriptDir);
    }
    
    define('BASE_URL', $proto . '://' . $host . $scriptDir);
}

// Create database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Asia/Kathmandu');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);