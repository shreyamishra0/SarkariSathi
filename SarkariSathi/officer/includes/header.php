<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../auth/auth-check.php';
requireRole('officer');
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/constants.php';

$unread_count = getUnreadCount($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'Officer Dashboard' ?> - SarkariSathi</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/officer.css">
</head>
<body>
    <nav class="navbar officer-nav">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/officer/dashboard.php">SarkariSathi Officer</a>
            </div>
            <ul class="nav-menu">
                <li><a href="/officer/dashboard.php">Dashboard</a></li>
                <li><a href="/officer/manage-sections.php">Manage Services</a></li>
                <li><a href="/officer/queue-management.php">Queue Management</a></li>
                <li><a href="/officer/applications.php">Applications</a></li>
                <li>
                    <a href="/officer/messages.php">
                        Messages 
                        <?php if ($unread_count > 0): ?>
                            <span class="badge"><?= $unread_count ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="/officer/complaints.php">Complaints</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">👤 <?= htmlspecialchars($_SESSION['name']) ?></a>
                    <ul class="dropdown-menu">
                        <li><a href="/officer/profile.php">Profile</a></li>
                        <li><a href="/auth/logout.php">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </nav>
    <main class="main-content"></main>