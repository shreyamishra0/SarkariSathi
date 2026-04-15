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

// Check if ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: manage-services.php?error=" . urlencode("No service ID provided."));
    exit();
}

$section_id = (int)$_GET['id'];

// Get section details
$stmt = $conn->prepare("SELECT * FROM sections WHERE id = ? AND officer_id = ?");
$stmt->bind_param("ii", $section_id, $officer_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: manage-services.php?error=" . urlencode("Service not found or you don't have permission to edit it."));
    exit();
}

$section = $result->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
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
        $update_stmt = $conn->prepare("
            UPDATE sections 
            SET name = ?, description = ?, required_docs = ?, 
                estimated_days = ?, fee_amount = ?, is_active = ?
            WHERE id = ? AND officer_id = ?
        ");
        $update_stmt->bind_param(
            "sssidiii", 
            $name, $description, $required_docs, 
            $estimated_days, $fee_amount, $is_active,
            $section_id, $officer_id
        );
        
        if ($update_stmt->execute()) {
            $success = "Service updated successfully!";
            // Refresh section data
            $stmt->execute();
            $section = $stmt->get_result()->fetch_assoc();
        } else {
            $error = "Failed to update service: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Service - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-edit"></i>
                Edit Service
            </h1>
            <p class="page-subtitle">Update service details and settings</p>
            <a href="manage-services.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Services
            </a>
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

        <div class="form-card">
            <h3>
                <i class="fas fa-cogs"></i>
                Service Information
            </h3>
            <form method="POST">
                <input type="hidden" name="update_service" value="1">
                
                <div class="form-group">
                    <label for="name">
                        <i class="fas fa-tag"></i>
                        Service Name *
                    </label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           value="<?php echo htmlspecialchars($section['name']); ?>"
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
                              rows="4"><?php echo htmlspecialchars($section['description']); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="required_docs">
                        <i class="fas fa-file-alt"></i>
                        Required Documents
                    </label>
                    <input type="text" 
                           id="required_docs" 
                           name="required_docs" 
                           value="<?php echo htmlspecialchars($section['required_docs']); ?>"
                           placeholder="e.g., Citizenship Certificate, Passport Photo, Birth Certificate">
                </div>

                <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group">
                        <label for="estimated_days">
                            <i class="fas fa-clock"></i>
                            Estimated Processing Days
                        </label>
                        <input type="number" 
                               id="estimated_days" 
                               name="estimated_days" 
                               value="<?php echo htmlspecialchars($section['estimated_days']); ?>"
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
                               value="<?php echo htmlspecialchars($section['fee_amount']); ?>"
                               step="0.01" 
                               min="0">
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" 
                               id="is_active" 
                               name="is_active" 
                               value="1" 
                               <?php echo $section['is_active'] ? 'checked' : ''; ?>>
                        <label for="is_active" style="margin: 0;">
                            <i class="fas fa-toggle-on"></i>
                            Active Service (Visible to citizens)
                        </label>
                    </div>
                </div>

                <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Update Service
                    </button>
                    <a href="manage-services.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Cancel
                    </a>
                    <a href="delete-section.php?id=<?php echo $section_id; ?>" 
                       class="btn btn-danger" 
                       style="margin-left: auto;"
                       onclick="return confirm('Are you sure you want to delete this service?')">
                        <i class="fas fa-trash"></i>
                        Delete Service
                    </a>
                </div>
            </form>
        </div>

        <!-- Service Statistics (Optional) -->
        <div class="stats-grid" style="margin-top: 2rem;">
            <?php
            // Get statistics for this service
            $app_count = $conn->query("SELECT COUNT(*) as count FROM applications WHERE section_id = $section_id")->fetch_assoc()['count'];
            $queue_count = $conn->query("SELECT COUNT(*) as count FROM queue WHERE section_id = $section_id")->fetch_assoc()['count'];
            $posts_count = $conn->query("SELECT COUNT(*) as count FROM posts WHERE section_id = $section_id")->fetch_assoc()['count'];
            ?>
            <div class="stat-card">
                <h3><?php echo $app_count; ?></h3>
                <p>Total Applications</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $queue_count; ?></h3>
                <p>Queue Bookings</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $posts_count; ?></h3>
                <p>Guide Posts</p>
            </div>
            <div class="stat-card">
                <h3><?php echo date('M d, Y', strtotime($section['created_at'])); ?></h3>
                <p>Created On</p>
            </div>
        </div>
    </div>

    <script>
        // Auto-format fee amount on blur
        document.getElementById('fee_amount').addEventListener('blur', function() {
            if (this.value) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });

        // Confirm before leaving if form is dirty
        let formChanged = false;
        const form = document.querySelector('form');
        const inputs = form.querySelectorAll('input, textarea, select');
        
        inputs.forEach(input => {
            input.addEventListener('change', () => formChanged = true);
        });

        window.addEventListener('beforeunload', (e) => {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        form.addEventListener('submit', () => formChanged = false);
    </script>
</body>
</html>
