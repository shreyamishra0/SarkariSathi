<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

// Handle service deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $section_id = (int)$_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM sections WHERE id = ? AND officer_id = ?");
    $stmt->bind_param("ii", $section_id, $officer_id);
    if ($stmt->execute()) {
        $success_msg = "Service deleted successfully!";
    } else {
        $error_msg = "Failed to delete service.";
    }
}

// Get services
$sections = $conn->query("SELECT * FROM sections WHERE officer_id = $officer_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">

</head>
<body>
    <?php include 'includes/sidebar.php'; ?>    

    <div class="main-content">
        <div class="page-header">
            <div>
                <h1 class="page-title"><i class="fas fa-cogs"></i> Manage Services</h1>
                <p class="page-subtitle">Create and manage government services</p>
            </div>
            <a href="add-section.php" class="btn-primary">
                <i class="fas fa-plus"></i> Add New Service
            </a>
        </div>

        <?php if (isset($success_msg)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= $success_msg ?>
        </div>
        <?php endif; ?>

        <?php if (isset($error_msg)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i> <?= $error_msg ?>
        </div>
        <?php endif; ?>

        <?php if ($sections->num_rows == 0): ?>
        <div class="empty-state">
            <i class="fas fa-cogs"></i>
            <h3>No Services Found</h3>
            <p>You haven't created any services yet. Get started by adding your first service!</p>
            <a href="add-section.php" class="btn-primary">
                <i class="fas fa-plus"></i> Create Your First Service
            </a>
        </div>
        <?php else: ?>
        <div class="services-grid">
            <?php while ($section = $sections->fetch_assoc()): ?>
            <div class="service-card">
                <div class="service-header">
                    <h3 class="service-name"><?= htmlspecialchars($section['name']) ?></h3>
                    <span class="status-badge status-<?= $section['is_active'] ? 'active' : 'inactive' ?>">
                        <?= $section['is_active'] ? 'Active' : 'Inactive' ?>
                    </span>
                </div>
                
                <p class="service-description">
                    <?= htmlspecialchars($section['description'] ?? 'No description provided') ?>
                </p>

                <div class="service-meta">
                    <div class="meta-item">
                        <i class="fas fa-clock"></i>
                        <span><?= $section['estimated_days'] ?? 'N/A' ?> days</span>
                    </div>
                    <div class="meta-item">
                        <i class="fas fa-money-bill"></i>
                        <span>Rs. <?= number_format($section['fee_amount'] ?? 0, 2) ?></span>
                    </div>
                </div>

                <div class="service-actions">
                    <a href="edit-section.php?id=<?= $section['id'] ?>" class="btn-small btn-edit">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="?delete=<?= $section['id'] ?>" class="btn-small btn-delete" 
                       onclick="return confirm('Delete this service?')">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>