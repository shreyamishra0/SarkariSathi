<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

$error = '';
$success = '';

// Check if section ID is provided
if (!isset($_GET['id'])) {
    header("Location: manage-services.php?error=No service specified");
    exit();
}

$section_id = (int)$_GET['id'];

// Get section details for confirmation
$section_stmt = $conn->prepare("SELECT * FROM sections WHERE id = ? AND officer_id = ?");
$section_stmt->bind_param("ii", $section_id, $officer_id);
$section_stmt->execute();
$section_result = $section_stmt->get_result();
$section = $section_result->fetch_assoc();

if (!$section) {
    header("Location: manage-services.php?error=Service not found or you don't have permission to delete it");
    exit();
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_delete'])) {
        
        $check_dependencies = true;
        $dependency_errors = [];
        
        // Check applications
        $app_stmt = $conn->prepare("SELECT COUNT(*) as app_count FROM applications WHERE section_id = ?");
        $app_stmt->bind_param("i", $section_id);
        $app_stmt->execute();
        $app_result = $app_stmt->get_result();
        $app_count = $app_result->fetch_assoc()['app_count'];
        
        if ($app_count > 0) {
            $dependency_errors[] = "There are $app_count application(s) associated with this service.";
            $check_dependencies = false;
        }
        
        
        // Check queue entries
        $queue_stmt = $conn->prepare("SELECT COUNT(*) as queue_count FROM queue WHERE section_id = ?");
        $queue_stmt->bind_param("i", $section_id);
        $queue_stmt->execute();
        $queue_result = $queue_stmt->get_result();
        $queue_count = $queue_result->fetch_assoc()['queue_count'];
        
        if ($queue_count > 0) {
            $dependency_errors[] = "There are $queue_count queue booking(s) associated with this service.";
            $check_dependencies = false;
        }
        
        if ($check_dependencies) {
            // No dependencies, proceed with deletion
            $delete_stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
            $delete_stmt->bind_param("i", $section_id);
            
            if ($delete_stmt->execute()) {
                $success = "Service deleted successfully!";
                // Redirect after 2 seconds
                header("Refresh: 2; URL=manage-services.php");
            } else {
                $error = "Failed to delete service: " . $conn->error;
            }
        } else {
            $error = "Cannot delete service because it has dependencies:<br>" . implode("<br>", $dependency_errors);
        }
        
    } elseif (isset($_POST['cancel'])) {
        // User cancelled deletion
        header("Location: manage-services.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Delete Service - Officer Panel</title>
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

        /* Warning Card */
        .warning-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            margin: 0 auto;
            border-left: 6px solid #dc3545;
        }

        .warning-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .warning-icon {
            width: 80px;
            height: 80px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5rem;
            color: white;
        }

        .warning-header h2 {
            color: #dc3545;
            margin-bottom: 10px;
            font-size: 1.8rem;
        }

        .warning-header p {
            color: #666;
            font-size: 1.1rem;
        }

        /* Service Details */
        .service-details {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .service-details h4 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .detail-item {
            display: flex;
            margin-bottom: 8px;
        }

        .detail-label {
            font-weight: 600;
            color: #856404;
            min-width: 150px;
        }

        .detail-value {
            color: #333;
            flex: 1;
        }

        /* Dependency Warnings */
        .dependency-warning {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }

        .dependency-warning h4 {
            color: #721c24;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dependency-list {
            list-style: none;
            padding: 0;
        }

        .dependency-list li {
            padding: 8px 0;
            border-bottom: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dependency-list li:last-child {
            border-bottom: none;
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-width: 140px;
            justify-content: center;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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

        /* Success Message */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #c3e6cb;
            text-align: center;
            margin-bottom: 20px;
        }

        .success-message i {
            font-size: 2rem;
            margin-bottom: 10px;
            display: block;
        }

        /* Error Message */
        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            border: 1px solid #f5c6cb;
            margin-bottom: 20px;
        }

        .error-message i {
            margin-right: 10px;
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
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
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
                    <?php echo strtoupper(substr($current_user, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
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
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-trash"></i>
                Delete Service
            </h1>
            <p class="page-subtitle">Permanently remove a government service</p>
        </div>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <h3>Service Deleted Successfully!</h3>
                <p>Redirecting you back to services management...</p>
            </div>
        <?php else: ?>
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="warning-card">
                <div class="warning-header">
                    <div class="warning-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <h2>Confirm Deletion</h2>
                    <p>This action cannot be undone. Please review the service details below.</p>
                </div>

                <!-- Service Details -->
                <div class="service-details">
                    <h4>Service to be deleted:</h4>
                    <div class="detail-item">
                        <span class="detail-label">Service Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($section['name']); ?></span>
                    </div>
                    <?php if (!empty($section['description'])): ?>
                        <div class="detail-item">
                            <span class="detail-label">Description:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($section['description']); ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="detail-item">
                        <span class="detail-label">Status:</span>
                        <span class="detail-value">
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; background: <?php echo $section['is_active'] ? '#d4edda' : '#f8d7da'; ?>; color: <?php echo $section['is_active'] ? '#155724' : '#721c24'; ?>;">
                                <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Created:</span>
                        <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($section['created_at'])); ?></span>
                    </div>
                </div>

                <!-- Dependency Check -->
                <?php
                // Check for dependencies
                $has_dependencies = false;
                $dependency_messages = [];
                
                // Check applications
                $app_stmt = $conn->prepare("SELECT COUNT(*) as count FROM applications WHERE section_id = ?");
                $app_stmt->bind_param("i", $section_id);
                $app_stmt->execute();
                $app_count = $app_stmt->get_result()->fetch_assoc()['count'];
                
                if ($app_count > 0) {
                    $has_dependencies = true;
                    $dependency_messages[] = "<i class='fas fa-file-alt'></i> $app_count application(s) are associated with this service";
                }
                
                
                // Check queue entries
                $queue_stmt = $conn->prepare("SELECT COUNT(*) as count FROM queue WHERE section_id = ?");
                $queue_stmt->bind_param("i", $section_id);
                $queue_stmt->execute();
                $queue_count = $queue_stmt->get_result()->fetch_assoc()['count'];
                
                if ($queue_count > 0) {
                    $has_dependencies = true;
                    $dependency_messages[] = "<i class='fas fa-list-ol'></i> $queue_count queue booking(s) are associated with this service";
                }
                ?>

                <?php if ($has_dependencies && empty($error)): ?>
                    <div class="dependency-warning">
                        <h4><i class="fas fa-exclamation-circle"></i> Warning: Dependencies Found</h4>
                        <p>This service cannot be deleted because it has the following dependencies:</p>
                        <ul class="dependency-list">
                            <?php foreach ($dependency_messages as $message): ?>
                                <li><?php echo $message; ?></li>
                            <?php endforeach; ?>
                        </ul>
                        <p style="margin-top: 10px; font-size: 0.9rem; color: #721c24;">
                            <strong>Solution:</strong> You must first remove or reassign these dependencies before deleting the service.
                        </p>
                    </div>
                <?php elseif (empty($error)): ?>
                    <div style="text-align: center; margin: 25px 0; padding: 15px; background: #e7f3ff; border-radius: 8px; border-left: 4px solid #00b4d8;">
                        <i class="fas fa-info-circle" style="color: #00b4d8;"></i>
                        <strong>No dependencies found.</strong> This service can be safely deleted.
                    </div>
                <?php endif; ?>

                <!-- Action Buttons -->
                <form method="POST">
                    <div class="action-buttons">
                        <?php if (!$has_dependencies || !empty($error)): ?>
                            <button type="submit" name="confirm_delete" class="btn btn-danger" onclick="return confirm('Are you absolutely sure you want to delete \"<?php echo addslashes($section['name']); ?>\"? This action is permanent and cannot be undone.')">
                                <i class="fas fa-trash"></i>
                                Delete Permanently
                            </button>
                        <?php endif; ?>
                        <button type="submit" name="cancel" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Additional confirmation for deletion
        function confirmDeletion() {
            const serviceName = "<?php echo addslashes($section['name']); ?>";
            return confirm(`WARNING: You are about to permanently delete the service "${serviceName}".\n\nThis action cannot be undone and all associated data will be lost.\n\nAre you absolutely sure?`);
        }

        // Add confirmation to delete button
        document.querySelector('button[name="confirm_delete"]')?.addEventListener('click', function(e) {
            if (!confirmDeletion()) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>