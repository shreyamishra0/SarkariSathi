<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../auth/auth-check.php';
requireRole('citizen');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';

$unread_count = getUnreadCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Citizen Dashboard' ?> - SarkariSathi</title>
    <link rel="stylesheet" href="/assets/css/style-v2.css">
    <link rel="stylesheet" href="/assets/css/citizen.css">
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/citizen/dashboard.php">🏛️ SarkariSathi</a>
            </div>
            <ul class="nav-menu">
                <li><a href="/citizen/dashboard.php">Dashboard</a></li>
                <li><a href="/citizen/sections.php">Services</a></li>
                <li><a href="/citizen/queue-booking.php">Book Queue</a></li>
                <li><a href="/citizen/my-queue.php">My Bookings</a></li>
                <li><a href="/citizen/track-status.php">Track Status</a></li>
                <li>
                    <a href="/citizen/messages.php">
                        Messages 
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="/citizen/complaints.php">Complaints</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">👤 <?= htmlspecialchars($_SESSION['name']) ?></a>
                    <ul class="dropdown-menu">
                        <li><a href="/citizen/profile.php">Profile</a></li>
                        <li><a href="/auth/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <main class="main-content"></main>