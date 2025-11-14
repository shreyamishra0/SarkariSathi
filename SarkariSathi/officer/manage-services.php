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

// Get sections/services for this officer
$sections_result = $conn->query("SELECT * FROM sections WHERE officer_id = $officer_id");
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
            <div>
                <h1 class="page-title">
                    <i class="fas fa-cogs"></i>
                    Manage Services
                </h1>
                <p class="page-subtitle">Create and manage government services under your department</p>
            </div>
            <a href="add-section.php" class="btn-primary">
                <i class="fas fa-plus"></i>
                Add New Service
            </a>
        </div>

        <?php if (empty($sections)): ?>
            <div class="empty-state">
                <i class="fas fa-cogs"></i>
                <h3>No Services Found</h3>
                <p>You haven't created any services yet. Get started by adding your first service!</p>
                <a href="add-section.php" class="btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-plus"></i>
                    Create Your First Service
                </a>
            </div>
        <?php else: ?>
            <!-- View Toggle -->
            <div class="view-toggle">
                <button class="view-btn active" onclick="toggleView('table')">
                    <i class="fas fa-table"></i> Table View
                </button>
                <button class="view-btn" onclick="toggleView('grid')">
                    <i class="fas fa-th-large"></i> Grid View
                </button>
            </div>

            <!-- Table View -->
            <div id="tableView">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Description</th>
                            <th>Required Docs</th>
                            <th>Estimated Days</th>
                            <th>Fee</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sections as $section): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($section['name']); ?></strong>
                            </td>
                            <td><?php echo htmlspecialchars($section['description'] ?? 'No description'); ?></td>
                            <td><?php echo htmlspecialchars($section['required_docs'] ?? 'Not specified'); ?></td>
                            <td><?php echo $section['estimated_days'] ?? 'N/A'; ?> days</td>
                            <td>Rs. <?php echo number_format($section['fee_amount'] ?? 0, 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $section['is_active'] ? 'active' : 'inactive'; ?>">
                                    <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <a href="edit-section.php?id=<?php echo $section['id']; ?>">
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <a href="delete-section.php?id=<?php echo $section['id']; ?>" 
                                   class="delete"
                                   onclick="return confirm('Are you sure you want to delete this service?')">
                                    <i class="fas fa-trash"></i> Delete
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Grid View -->
            <div id="gridView" style="display: none;">
                <div class="services-grid">
                    <?php foreach ($sections as $section): ?>
                    <div class="service-card">
                        <div class="service-header">
                            <h3 class="service-name"><?php echo htmlspecialchars($section['name']); ?></h3>
                            <span class="status-badge status-<?php echo $section['is_active'] ? 'active' : 'inactive'; ?>">
                                <?php echo $section['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <p class="service-description">
                            <?php echo htmlspecialchars($section['description'] ?? 'No description provided'); ?>
                        </p>
                        <div class="service-meta">
                            <span>
                                <i class="fas fa-clock"></i>
                                <?php echo $section['estimated_days'] ?? 'N/A'; ?> days
                            </span>
                            <span>
                                <i class="fas fa-money-bill"></i>
                                Rs. <?php echo number_format($section['fee_amount'] ?? 0, 2); ?>
                            </span>
                        </div>
                        <div class="service-actions">
                            <a href="edit-section.php?id=<?php echo $section['id']; ?>" class="btn-small btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="delete-section.php?id=<?php echo $section['id']; ?>" 
                               class="btn-small btn-delete"
                               onclick="return confirm('Are you sure you want to delete this service?')">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function toggleView(viewType) {
            const tableView = document.getElementById('tableView');
            const gridView = document.getElementById('gridView');
            const viewBtns = document.querySelectorAll('.view-btn');
            
            // Update button states
            viewBtns.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Show/hide views
            if (viewType === 'table') {
                tableView.style.display = 'block';
                gridView.style.display = 'none';
            } else {
                tableView.style.display = 'none';
                gridView.style.display = 'block';
            }
        }

        // Confirm delete
        function confirmDelete() {
            return confirm('Are you sure you want to delete this service? This action cannot be undone.');
        }
    </script>
</body>
</html>