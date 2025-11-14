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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f8f9fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: #0d1b2a;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid #1b3a4b;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #00b4d8;
            font-size: 1.6rem;
            margin-bottom: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background: #1b3a4b;
            border-radius: 8px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #00b4d8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #00b4d8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e0e0e0;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 8px;
            font-size: 0.95rem;
        }

        .sidebar-menu a:hover {
            background: #1b3a4b;
            color: #00b4d8;
            transform: translateX(5px);
        }

        .sidebar-menu a.active {
            background: #00b4d8;
            color: white;
        }

        .sidebar-menu i {
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            color: #0d1b2a;
            font-size: 2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Profile Card */
        .profile-container {
            max-width: 800px;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 100%);
            padding: 40px;
            text-align: center;
            color: white;
            position: relative;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: #00b4d8;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: white;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .profile-role {
            color: #00b4d8;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .profile-body {
            padding: 40px;
        }

        .profile-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #0d1b2a;
            font-size: 1.3rem;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #00b4d8;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-label {
            font-weight: 600;
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-value {
            color: #0d1b2a;
            font-size: 1.1rem;
            font-weight: 500;
            padding: 12px 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #00b4d8;
        }

        .info-value.empty {
            color: #999;
            font-style: italic;
        }

        /* Action Buttons */
        .profile-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #00b4d8;
            color: white;
        }

        .btn-primary:hover {
            background: #0099c3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        /* Stats Section */
        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 2em;
            color: #0d1b2a;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .profile-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏛️ SarkariSathi</h2>
            <p style="color: #e0e0e0; font-size: 0.9rem;">Officer Portal</p>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($officer['name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($officer['name']); ?></div>
                    <div class="user-role">Officer</div>
                </div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage-services.php"><i class="fas fa-cogs"></i> Manage Services</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>
            <li><a href="profile.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

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