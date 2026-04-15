<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_user = $_SESSION['name'] ?? 'Officer';
$current_page = basename($_SERVER['PHP_SELF']);

function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}
?>
<div class="sidebar">
    <div class="sidebar-header">
        <a href="<?= BASE_URL ?>/officer/dashboard.php" style="text-decoration: none; color: inherit;">
            <h2>🏛️ SarkariSathi</h2>
        </a>
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
        <li><a href="dashboard.php" class="<?php echo isActive('dashboard.php'); ?>">
            <i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
        <li><a href="manage-services.php" class="<?php echo isActive('manage-services.php') || isActive('add-section.php'); ?>">
            <i class="fas fa-cogs"></i> Manage Services</a></li>
        <li><a href="improved-queue-management.php" class="<?php echo isActive('improved-queue-management.php'); ?>">
            <i class="fas fa-brain"></i> Smart Queue (AI)</a></li>
</li>
<li>
    <a href="create-application.php" class="<?php echo isActive('create-application.php'); ?>">
        <i class="fas fa-plus-circle"></i> Create Application
    </a>
</li>
        <li><a href="messages.php" class="<?php echo isActive('messages.php'); ?>">
            <i class="fas fa-comments"></i> Messages</a></li>
        <li><a href="complaints.php" class="<?php echo isActive('complaints.php'); ?>">
            <i class="fas fa-exclamation-circle"></i> Complaints</a></li>
        <li><a href="profile.php" class="<?php echo isActive('profile.php') || isActive('change-password.php'); ?>">
            <i class="fas fa-user"></i> Profile</a></li>
        <li><a href="../auth/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout</a></li>
    </ul>
</div>