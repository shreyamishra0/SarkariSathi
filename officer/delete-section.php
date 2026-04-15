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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Section - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

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