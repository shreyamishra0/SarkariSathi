<?php
session_start();
if (!isset($_SESSION['officer_id'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and is an officer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: /auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$officer_name = $_SESSION['name'];
$office_name = $_SESSION['office_name'] ?? 'Government Office';

// Get statistics
$total_sections_query = $conn->query("SELECT COUNT(*) as count FROM sections WHERE officer_id = $officer_id");
$total_sections = $total_sections_query->fetch_assoc()['count'];

$total_applications_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    WHERE s.officer_id = $officer_id
");
$total_applications = $total_applications_query->fetch_assoc()['count'];

$pending_applications_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    WHERE s.officer_id = $officer_id 
    AND a.status IN ('submitted', 'document_verification')
");
$pending_applications = $pending_applications_query->fetch_assoc()['count'];

$today = date('Y-m-d');
$today_queue_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM queue q
    JOIN sections s ON q.section_id = s.id
    WHERE s.officer_id = $officer_id 
    AND q.queue_date = '$today'
");
$today_queue = $today_queue_query->fetch_assoc()['count'];

$pending_complaints_query = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'");
$pending_complaints = $pending_complaints_query->fetch_assoc()['count'];

$unread_messages = getUnreadCount($officer_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Dashboard - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/officer.css">
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🏛️ Officer Panel</h2>
    <a href="<?= BASE_URL ?>/officer/dashboard.php" class="active">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/officer/manage-services.php">
        <i class="fas fa-folder"></i> Manage Services
    </a>
    <a href="<?= BASE_URL ?>/officer/queue-management.php">
        <i class="fas fa-users"></i> Queue Management
    </a>
    <a href="<?= BASE_URL ?>/officer/applications.php">
        <i class="fas fa-file-alt"></i> Applications
    </a>
    <a href="<?= BASE_URL ?>/officer/messages.php">
        <i class="fas fa-envelope"></i> Messages
        <?php if ($unread_messages > 0): ?>
            <span class="badge"><?= $unread_messages ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/officer/complaints.php">
        <i class="fas fa-exclamation-circle"></i> Complaints
        <?php if ($pending_complaints > 0): ?>
            <span class="badge"><?= $pending_complaints ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/officer/profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Welcome, <?= htmlspecialchars($officer_name) ?>! 👋</h1>
        <p>Manage your services, applications, queues, and citizen inquiries</p>
        <p style="color: #00b4d8; font-weight: 600; margin-top: 0.5rem;">
            📍 <?= htmlspecialchars($office_name) ?>
        </p>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <h3><?= $total_sections ?></h3>
            <p>Active Services</p>
        </div>
        <div class="stat-card">
            <h3><?= $total_applications ?></h3>
            <p>Total Applications</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending_applications ?></h3>
            <p>Pending Applications</p>
        </div>
        <div class="stat-card">
            <h3><?= $today_queue ?></h3>
            <p>Today's Queue</p>
        </div>
        <div class="stat-card">
            <h3><?= $pending_complaints ?></h3>
            <p>Pending Complaints</p>
        </div>
        <div class="stat-card">
            <h3><?= $unread_messages ?></h3>
            <p>Unread Messages</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="section-title">Quick Actions</h2>
    <div class="quick-actions-grid">
        <a href="<?= BASE_URL ?>/officer/manage-services.php" class="action-card">
            <i class="fas fa-plus-circle"></i>
            <span>Add Service</span>
        
        </a>
        <a href="<?= BASE_URL ?>/officer/queue-management.php" class="action-card">
            <i class="fas fa-list-ol"></i>
            <span>Manage Queue</span>
        </a>
        <a href="<?= BASE_URL ?>/officer/applications.php" class="action-card">
            <i class="fas fa-tasks"></i>
            <span>Process Applications</span>
        </a>
        <a href="<?= BASE_URL ?>/officer/complaints.php" class="action-card">
            <i class="fas fa-comment-alt"></i>
            <span>Handle Complaints</span>
        </a>
        <a href="<?= BASE_URL ?>/officer/messages.php" class="action-card">
            <i class="fas fa-inbox"></i>
            <span>View Messages</span>
        </a>
    </div>

    <!-- Recent Applications Section -->
    <h2 class="section-title" style="margin-top: 3rem;">Recent Applications</h2>
    <div class="form-card">
        <?php
        $recent_apps = $conn->query("
            SELECT a.*, s.name as section_name, u.name as citizen_name
            FROM applications a
            JOIN sections s ON a.section_id = s.id
            JOIN users u ON a.citizen_id = u.id
            WHERE s.officer_id = $officer_id
            ORDER BY a.created_at DESC
            LIMIT 5
        ");
        
        if ($recent_apps->num_rows > 0):
        ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tracking Number</th>
                    <th>Citizen</th>
                    <th>Service</th>
                    <th>Status</th>
                    <th>Submitted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($app = $recent_apps->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($app['tracking_number']) ?></strong></td>
                    <td><?= htmlspecialchars($app['citizen_name']) ?></td>
                    <td><?= htmlspecialchars($app['section_name']) ?></td>
                    <td>
                        <span class="status-badge <?= strtolower($app['status']) ?>">
                            <?= ucwords(str_replace('_', ' ', $app['status'])) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['submitted_date'])) ?></td>
                    <td>
                        <a href="/officer/applications.php?id=<?= $app['id'] ?>">
                            <button>View</button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 2rem;">
            No applications yet. Applications will appear here once citizens submit documents.
        </p>
        <?php endif; ?>
    </div>

    <!-- Today's Queue Section -->
    <h2 class="section-title" style="margin-top: 3rem;">Today's Queue</h2>
    <div class="form-card">
        <?php
        $today_bookings = $conn->query("
            SELECT q.*, s.name as section_name, u.name as citizen_name
            FROM queue q
            JOIN sections s ON q.section_id = s.id
            JOIN users u ON q.citizen_id = u.id
            WHERE s.officer_id = $officer_id 
            AND q.queue_date = '$today'
            ORDER BY q.time_slot ASC
        ");
        
        if ($today_bookings->num_rows > 0):
        ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Queue Number</th>
                    <th>Citizen</th>
                    <th>Service</th>
                    <th>Time Slot</th>
                    <th>Visit Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($booking = $today_bookings->fetch_assoc()): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($booking['queue_number']) ?></strong></td>
                    <td><?= htmlspecialchars($booking['citizen_name']) ?></td>
                    <td><?= htmlspecialchars($booking['section_name']) ?></td>
                    <td><?= date('h:i A', strtotime($booking['time_slot'])) ?></td>
                    <td><?= ucfirst($booking['visit_type']) ?></td>
                    <td>
                        <span class="status-badge <?= $booking['status'] ?>">
                            <?= ucwords(str_replace('_', ' ', $booking['status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align: center; color: #666; padding: 2rem;">
            No queue bookings for today.
        </p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>