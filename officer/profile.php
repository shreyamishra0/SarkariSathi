<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$officer_result = $conn->query("SELECT * FROM users WHERE id = $officer_id");
$officer = $officer_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile - Officer Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
        <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
    
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-user-circle"></i>
                    Profile
                </h1>
                <p class="page-subtitle">Manage your officer profile information</p>
            </div>

            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($officer['name'], 0, 1)); ?>
                    </div>
                    <h2 class="profile-name"><?php echo htmlspecialchars($officer['name']); ?></h2>
                    <p class="profile-role"><?php echo ucfirst($officer['role']); ?></p>
                </div>

                <div class="profile-body">
                    <div class="profile-section">
                        <h3 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Personal Information
                        </h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-user"></i>
                                    Full Name
                                </span>
                                <div class="info-value"><?php echo htmlspecialchars($officer['name']); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-envelope"></i>
                                    Email Address
                                </span>
                                <div class="info-value"><?php echo htmlspecialchars($officer['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-phone"></i>
                                    Phone Number
                                </span>
                                <div class="info-value"><?php echo htmlspecialchars($officer['phone']); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-briefcase"></i>
                                    Role
                                </span>
                                <div class="info-value"><?php echo ucfirst($officer['role']); ?></div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-building"></i>
                                    Office
                                </span>
                                <div class="info-value <?php echo empty($officer['office_name']) ? 'empty' : ''; ?>">
                                    <?php echo !empty($officer['office_name']) ? htmlspecialchars($officer['office_name']) : 'Not specified'; ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <span class="info-label">
                                    <i class="fas fa-calendar-alt"></i>
                                    Member Since
                                </span>
                                <div class="info-value">
                                    <?php echo date('F j, Y', strtotime($officer['created_at'])); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-actions">
                        <a href="edit-profile.php" class="btn btn-primary">
                            <i class="fas fa-edit"></i>
                            Edit Profile
                        </a>
                        <a href="change-password.php" class="btn btn-secondary">
                            <i class="fas fa-key"></i>
                            Change Password
                        </a>
                    </div>
                </div>
            </div>

            <!-- Optional: Add some statistics -->
            <div class="profile-stats">
                <div class="stat-card">
                    <h3>12</h3>
                    <p>Applications Processed</p>
                </div>
                <div class="stat-card">
                    <h3>8</h3>
                    <p>Complaints Resolved</p>
                </div>
                <div class="stat-card">
                    <h3>15</h3>
                    <p>Posts Created</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>