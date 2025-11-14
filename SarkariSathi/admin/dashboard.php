<?php
require_once __DIR__ . '/../includes/admin-check.php';

$admin_name = $_SESSION['name'];

// Get statistics
$total_users_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role IN ('citizen', 'officer')");
$total_users = $total_users_query->fetch_assoc()['count'];

$total_citizens_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'citizen'");
$total_citizens = $total_citizens_query->fetch_assoc()['count'];

$total_officers_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'officer'");
$total_officers = $total_officers_query->fetch_assoc()['count'];

$pending_officers_query = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'officer' AND is_verified = 0");
$pending_officers = $pending_officers_query->fetch_assoc()['count'];

$total_applications_query = $conn->query("SELECT COUNT(*) as count FROM applications");
$total_applications = $total_applications_query->fetch_assoc()['count'];

$pending_applications_query = $conn->query("SELECT COUNT(*) as count FROM applications WHERE status NOT IN ('completed', 'rejected')");
$pending_applications = $pending_applications_query->fetch_assoc()['count'];

$total_complaints_query = $conn->query("SELECT COUNT(*) as count FROM complaints");
$total_complaints = $total_complaints_query->fetch_assoc()['count'];

$pending_complaints_query = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'");
$pending_complaints = $pending_complaints_query->fetch_assoc()['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🛡️ Admin Panel</h2>
    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="active">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/admin/verify-officers.php">
        <i class="fas fa-user-check"></i> Verify Officers
        <?php if ($pending_officers > 0): ?>
            <span class="badge"><?= $pending_officers ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-users.php">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php">
        <i class="fas fa-exclamation-triangle"></i> Complaints
        <?php if ($pending_complaints > 0): ?>
            <span class="badge"><?= $pending_complaints ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/admin/profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Welcome, <?= htmlspecialchars($admin_name) ?>! 👋</h1>
        <p>System Administration & Oversight</p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_users ?></h3>
            <p>Total Users</p>
            <small><?= $total_citizens ?> Citizens, <?= $total_officers ?> Officers</small>
        </div>
        <div class="stat-card alert">
            <h3><?= $pending_officers ?></h3>
            <p>Pending Officer Verifications</p>
            <a href="<?= BASE_URL ?>/admin/verify-officers.php" class="mini-btn">Review Now</a>
        </div>
        <div class="stat-card">
            <h3><?= $total_applications ?></h3>
            <p>Total Applications</p>
            <small><?= $pending_applications ?> Pending</small>
        </div>
        <div class="stat-card">
            <h3><?= $total_complaints ?></h3>
            <p>Total Complaints</p>
            <small><?= $pending_complaints ?> Pending</small>
        </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="section-title">Quick Actions</h2>
    <div class="quick-actions-grid">
        <a href="<?= BASE_URL ?>/admin/verify-officers.php" class="action-card">
            <i class="fas fa-user-check"></i>
            <span>Verify Officers</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/manage-users.php" class="action-card">
            <i class="fas fa-users-cog"></i>
            <span>Manage Users</span>
        </a>
        <a href="<?= BASE_URL ?>/admin/manage-complaints.php" class="action-card">
            <i class="fas fa-exclamation-circle"></i>
            <span>Handle Complaints</span>
        </a>
    </div>

    <!-- Recent Pending Officers -->
    <?php
    $recent_officers = $conn->query("
        SELECT * FROM users 
        WHERE role = 'officer' AND is_verified = 0 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    
    if ($recent_officers && $recent_officers->num_rows > 0):
    ?>
    <h2 class="section-title" style="margin-top: 3rem;">Recent Pending Officers</h2>
    <div class="form-card">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Office</th>
                    <th>Email</th>
                    <th>Registered</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($officer = $recent_officers->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($officer['name']) ?></td>
                    <td><?= htmlspecialchars($officer['phone']) ?></td>
                    <td><?= htmlspecialchars($officer['office_name']) ?></td>
                    <td><?= htmlspecialchars($officer['email'] ?? 'N/A') ?></td>
                    <td><?= date('M d, Y', strtotime($officer['created_at'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/admin/verify-officers.php?id=<?= $officer['id'] ?>">
                            <button>Review</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Recent Activity -->
    <h2 class="section-title" style="margin-top: 3rem;">Recent System Activity</h2>
    <div class="form-card">
        <?php
        $recent_activity = $conn->query("
            SELECT 'application' as type, tracking_number as ref, created_at, 
                   (SELECT name FROM users WHERE id = applications.citizen_id) as user_name
            FROM applications 
            ORDER BY created_at DESC LIMIT 5
        ");
        
        if ($recent_activity && $recent_activity->num_rows > 0):
        ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Activity</th>
                    <th>Reference</th>
                    <th>User</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($activity = $recent_activity->fetch_assoc()): ?>
                <tr>
                    <td>
                        <i class="fas fa-file-alt"></i> 
                        <?= ucfirst($activity['type']) ?> Created
                    </td>
                    <td><?= htmlspecialchars($activity['ref']) ?></td>
                    <td><?= htmlspecialchars($activity['user_name']) ?></td>
                    <td><?= date('M d, Y H:i', strtotime($activity['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 2rem;">No recent activity.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>