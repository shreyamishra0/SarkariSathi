<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get current user info
$current_user = $_SESSION['name'] ?? 'Officer';
$current_page = basename($_SERVER['PHP_SELF']);

// Helper function to check if current page is active
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<!-- Standardized Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>🏛️ SarkariSathi</h2>
        <p style="color: #e0e0e0; font-size: 0.9rem;">Officer Portal</p>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($current_user, 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
                <div class="user-role">Officer</div>
            </div>
        </div>
    </div>
    <ul class="sidebar-menu">
        <li>
            <a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li>
            <a href="manage-services.php" class="<?php echo isActive('manage-services.php') || isActive('add-section.php') || isActive('edit-section.php') || isActive('delete-section.php'); ?>">
                <i class="fas fa-cogs"></i> Manage Services
            </a>
        </li>
        <li>
            <a href="queue-management.php" class="<?php echo isActive('queue-management.php'); ?>">
                <i class="fas fa-list-ol"></i> Queue Management
            </a>
        </li>
        <li>
            <a href="applications.php" class="<?php echo isActive('applications.php') || isActive('view-application.php'); ?>">
                <i class="fas fa-file-alt"></i> Applications
            </a>
        </li>
        <li>
            <a href="messages.php" class="<?php echo isActive('messages.php'); ?>">
                <i class="fas fa-comments"></i> Messages
            </a>
        </li>
        <li>
            <a href="complaints.php" class="<?php echo isActive('complaints.php'); ?>">
                <i class="fas fa-exclamation-circle"></i> Complaints
            </a>
        </li>
        <li>
            <a href="profile.php" class="<?php echo isActive('profile.php') || isActive('edit-profile.php') || isActive('change-password.php'); ?>">
                <i class="fas fa-user"></i> Profile
            </a>
        </li>
        <li>
            <a href="../auth/logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>