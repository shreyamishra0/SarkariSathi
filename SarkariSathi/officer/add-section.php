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

$success = '';
$error = '';

// Handle form submission for adding new service
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $required_docs = trim($_POST['required_docs']);
    $estimated_days = (int)$_POST['estimated_days'];
    $fee_amount = floatval($_POST['fee_amount']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate inputs
    if (empty($name)) {
        $error = "Service name is required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO sections (officer_id, name, description, required_docs, estimated_days, fee_amount, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssidi", $officer_id, $name, $description, $required_docs, $estimated_days, $fee_amount, $is_active);
        
        if ($stmt->execute()) {
            $success = "Service created successfully!";
            // Clear form fields
            $_POST['name'] = $_POST['description'] = $_POST['required_docs'] = '';
            $_POST['estimated_days'] = $_POST['fee_amount'] = '';
        } else {
            $error = "Failed to create service: " . $conn->error;
        }
    }
}

// Handle service deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Verify the service belongs to the current officer
    $check_stmt = $conn->prepare("SELECT id FROM sections WHERE id = ? AND officer_id = ?");
    $check_stmt->bind_param("ii", $delete_id, $officer_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $delete_stmt = $conn->prepare("DELETE FROM sections WHERE id = ?");
        $delete_stmt->bind_param("i", $delete_id);
        
        if ($delete_stmt->execute()) {
            $success = "Service deleted successfully!";
        } else {
            $error = "Failed to delete service: " . $conn->error;
        }
    } else {
        $error = "Service not found or you don't have permission to delete it.";
    }
}

// Get existing services for this officer
$sections_result = $conn->query("SELECT * FROM sections WHERE officer_id = $officer_id ORDER BY created_at DESC");
$sections = [];
if ($sections_result) {
    $sections = $sections_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Services - Officer Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/officer.css">
    <link rel="stylesheet" href="../assets/css/officer-v2.css">    
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
            <li><a href="manage-services.php" class="active"><i class="fas fa-cogs"></i> Manage Services</a></li>
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
                <i class="fas fa-cogs"></i>
                Manage Services
            </h1>
            <p class="page-subtitle">Add new services and manage existing ones</p>
        </div>

        <?php if ($success): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="services-container">
            <!-- Add Service Form -->
            <div class="form-card">
                <h3>
                    <i class="fas fa-plus-circle"></i>
                    Add New Service
                </h3>
                <form method="POST">
                    <input type="hidden" name="add_service" value="1">
                    
                    <div class="form-group">
                        <label for="name">
                            <i class="fas fa-tag"></i>
                            Service Name *
                        </label>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>"
                               placeholder="e.g., Citizenship Certificate, Passport Renewal"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="description">
                            <i class="fas fa-align-left"></i>
                            Description
                        </label>
                        <textarea id="description" 
                                  name="description" 
                                  placeholder="Describe the service and its purpose..."
                                  rows="3"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label for="required_docs">
                            <i class="fas fa-file-alt"></i>
                            Required Documents
                        </label>
                        <input type="text" 
                               id="required_docs" 
                               name="required_docs" 
                               value="<?php echo htmlspecialchars($_POST['required_docs'] ?? ''); ?>"
                               placeholder="e.g., Citizenship Certificate, Passport Photo, Birth Certificate">
                    </div>

                    <div class="form-group">
                        <label for="estimated_days">
                            <i class="fas fa-clock"></i>
                            Estimated Processing Days
                        </label>
                        <input type="number" 
                               id="estimated_days" 
                               name="estimated_days" 
                               value="<?php echo htmlspecialchars($_POST['estimated_days'] ?? '7'); ?>"
                               min="1" 
                               max="365">
                    </div>

                    <div class="form-group">
                        <label for="fee_amount">
                            <i class="fas fa-money-bill"></i>
                            Service Fee (Rs.)
                        </label>
                        <input type="number" 
                               id="fee_amount" 
                               name="fee_amount" 
                               value="<?php echo htmlspecialchars($_POST['fee_amount'] ?? '0'); ?>"
                               step="0.01" 
                               min="0">
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="is_active" 
                                   name="is_active" 
                                   value="1" 
                                   checked>
                            <label for="is_active" style="margin: 0;">
                                Active Service (Visible to citizens)
                            </label>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Create Service
                    </button>
                </form>
            </div>

            <!-- Existing Services List -->
            <div class="services-list">
                <h3>
                    <i class="fas fa-list"></i>
                    Your Services (<?php echo count($sections); ?>)
                </h3>

                <?php if (empty($sections)): ?>
                    <div class="empty-state">
                        <i class="fas fa-cogs"></i>
                        <p>No services created yet</p>
                        <p style="margin-top: 10px; font-size: 0.9rem;">Services you create will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($sections as $section): ?>
                        <div class="service-item">
                            <div class="service-header">
                                <div class="service-name"><?php echo htmlspecialchars($section['name']); ?></div>
                                <span class="service-status status-<?php echo $section['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <?php if (!empty($section['description'])): ?>
                                <div class="service-details">
                                    <?php echo htmlspecialchars($section['description']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="service-meta">
                                <?php if (!empty($section['estimated_days'])): ?>
                                    <span>
                                        <i class="fas fa-clock"></i>
                                        <?php echo $section['estimated_days']; ?> days
                                    </span>
                                <?php endif; ?>
                                
                                <?php if (!empty($section['fee_amount'])): ?>
                                    <span>
                                        <i class="fas fa-money-bill"></i>
                                        Rs. <?php echo number_format($section['fee_amount'], 2); ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="service-actions">
                                <a href="edit-section.php?id=<?php echo $section['id']; ?>" class="btn btn-primary btn-small">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="?delete_id=<?php echo $section['id']; ?>" 
                                   class="btn btn-delete"
                                   onclick="return confirm('Are you sure you want to delete the service \"<?php echo addslashes($section['name']); ?>\"? This action cannot be undone.')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Confirm delete with service name
        function confirmDelete(serviceName) {
            return confirm('Are you sure you want to delete the service "' + serviceName + '"? This action cannot be undone.');
        }

        // Auto-format fee amount
        document.getElementById('fee_amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    </script>
</body>
</html>