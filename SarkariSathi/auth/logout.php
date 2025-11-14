<?php
session_start();
require_once __DIR__ . '/../config.php';

// Destroy all session data
$_SESSION = array();
session_destroy();

// Redirect to login page
header("Location: " . BASE_URL . "/auth/login.php?success=" . urlencode("You have been logged out successfully."));
exit();
?>