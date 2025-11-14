<?php
session_start();

require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

if (!isset($_SESSION['name'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$citizen_name = $_SESSION['name'];

// Get statistics
$pending_applications_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM applications 
    WHERE citizen_id = $citizen_id 
    AND status NOT IN ('completed', 'rejected')
");
$pending_applications = $pending_applications_query ? $pending_applications_query->fetch_assoc()['count'] : 0;

$today = date('Y-m-d');
$upcoming_queues_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM queue 
    WHERE citizen_id = $citizen_id 
    AND queue_date >= '$today'
    AND status != 'completed'
");
$upcoming_queues = $upcoming_queues_query ? $upcoming_queues_query->fetch_assoc()['count'] : 0;

// Get unread messages count
$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = $citizen_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Citizen Dashboard - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: #f4f7fb;
    min-height: 100vh;
    color: #333;
}

.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 260px;
    background: #0d1b2a;
    color: #fff;
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    z-index: 1000;
}

.sidebar h2 {
    font-size: 1.25rem;
    color: #00b4d8;
    margin-bottom: 0.5rem;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    text-decoration: none;
    color: #cfe8f3;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.2s, color 0.2s;
}

.sidebar a i {
    color: #00b4d8;
    min-width: 18px;
}

.sidebar a.active,
.sidebar a:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}

.sidebar .badge {
    background: #ff4d4f;
    color: white;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    margin-left: auto;
}

.main-content {
    margin-left: 300px;
    padding: 2rem;
    max-width: calc(100% - 300px);
}

.welcome-header {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.welcome-header h1 {
    color: #0d1b2a;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.welcome-header p {
    color: #666;
    font-size: 1rem;
}

.alerts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.alert-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    transition: all 0.3s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.alert-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,180,216,0.2);
}

.alert-card i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #00b4d8;
}

.alert-card strong {
    color: #0d1b2a;
    font-size: 1.1rem;
}

.section-title {
    color: #0d1b2a;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 3px solid #00b4d8;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-bottom: 3rem;
}

.action-card {
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    text-align: center;
    transition: all 0.3s;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    min-height: 120px;
}

.action-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,180,216,0.2);
}

.action-card i {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: #00b4d8;
}

.action-card span {
    font-weight: 600;
    color: #0d1b2a;
}

.action-card small {
    margin-top: 0.5rem;
    color: #666;
}

.form-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 2rem;
}

.queue-table {
    width: 100%;
    border-collapse: collapse;
}

.queue-table th,
.queue-table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e0e0e0;
}

.queue-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #0d1b2a;
}

.queue-table tr:hover {
    background: #f8f9fa;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    display: inline-block;
}

.status-badge.submitted {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.document_verification {
    background: #fff3cd;
    color: #856404;
}

.status-badge.ready_for_pickup,
.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .sidebar {
        position: relative;
        width: 100%;
        flex-direction: row;
        gap: 0.5rem;
        padding: 0.75rem;
        overflow-x: auto;
    }
    
    .sidebar h2 {
        display: none;
    }
    
    .sidebar a {
        white-space: nowrap;
    }
    
    .main-content {
        margin-left: 0;
        max-width: 100%;
        padding: 1rem;
    }
    
    .alerts-grid,
    .quick-actions-grid {
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    }
}

@media (max-width: 768px) {
    .alerts-grid,
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🏛️ SarkariSathi</h2>
    <a href="<?= BASE_URL ?>/citizen/dashboard.php" class="active">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/citizen/sections.php">
        <i class="fas fa-list"></i> Services
    </a>
    <a href="<?= BASE_URL ?>/citizen/queue-booking.php">
        <i class="fas fa-calendar-check"></i> Book Queue
    </a>
    <a href="<?= BASE_URL ?>/citizen/my-queue.php">
        <i class="fas fa-users"></i> My Queue
    </a>
    <a href="<?= BASE_URL ?>/citizen/track-status.php">
        <i class="fas fa-search"></i> Track Status
    </a>
    <a href="<?= BASE_URL ?>/citizen/complaints.php">
        <i class="fas fa-exclamation-circle"></i> Complaints
    </a>
    <a href="<?= BASE_URL ?>/citizen/messages.php">
        <i class="fas fa-envelope"></i> Messages
        <?php if ($unread_messages > 0): ?>
            <span class="badge"><?= $unread_messages ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/citizen/profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Welcome, <?= htmlspecialchars($citizen_name) ?>! 👋</h1>
        <p>Manage your applications, queues, and inquiries efficiently</p>
    </div>

    <!-- Stats Cards -->
    <div class="alerts-grid">
        <div class="alert-card">
            <i class="fas fa-file-alt"></i>
            <p><strong><?= $pending_applications ?></strong></p>
            <p>Pending Applications</p>
        </div>
        <div class="alert-card">
            <i class="fas fa-users"></i>
            <p><strong><?= $upcoming_queues ?></strong></p>
            <p>Upcoming Queues</p>
        </div>
        <div class="alert-card">
            <i class="fas fa-envelope"></i>
            <p><strong><?= $unread_messages ?></strong></p>
            <p>Unread Messages</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <h2 class="section-title">Quick Actions</h2>
    <div class="quick-actions-grid">
        <a href="<?= BASE_URL ?>/sections.php" class="action-card">
            <i class="fas fa-folder-open"></i>
            <span>Browse Services</span>
        </a>
        <a href="<?= BASE_URL ?>/queue-booking.php" class="action-card">
            <i class="fas fa-calendar-check"></i>
            <span>Book Queue</span>
        </a>
        <a href="<?= BASE_URL ?>/track-status.php" class="action-card">
            <i class="fas fa-search-location"></i>
            <span>Track Application</span>
        </a>
        <a href="<?= BASE_URL ?>/complaints.php" class="action-card">
            <i class="fas fa-comment-dots"></i>
            <span>File Complaint</span>
        </a>
    </div>

    <!-- Available Services -->
    <h2 class="section-title" style="margin-top: 3rem;">Available Services</h2>
    <div class="quick-actions-grid">
        <?php
        $services = $conn->query("
            SELECT s.*, u.office_name,
                   (SELECT COUNT(*) FROM posts WHERE section_id = s.id) as post_count
            FROM sections s 
            JOIN users u ON s.officer_id = u.id 
            WHERE s.is_active = TRUE 
            ORDER BY s.created_at DESC
            LIMIT 6
        ");
        
        if ($services && $services->num_rows > 0):
            while ($service = $services->fetch_assoc()):
        ?>
        <a href="<?= BASE_URL ?>/citizen/section-detail.php?id=<?= $service['id'] ?>" class="action-card">
            <i class="fas fa-file-alt"></i>
            <span><?= htmlspecialchars($service['name']) ?></span>
            <small><?= $service['post_count'] ?> guides available</small>
        </a>
        <?php 
            endwhile;
        else:
        ?>
        <p style="color: #666; grid-column: 1/-1; text-align: center; padding: 2rem;">
            No services available at the moment.
        </p>
        <?php endif; ?>
    </div>

    <!-- Recent Applications -->
    <?php
    $recent_apps = $conn->query("
        SELECT a.*, s.name as section_name
        FROM applications a
        JOIN sections s ON a.section_id = s.id
        WHERE a.citizen_id = $citizen_id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    
    if ($recent_apps && $recent_apps->num_rows > 0):
    ?>
    <h2 class="section-title" style="margin-top: 3rem;">My Recent Applications</h2>
    <div class="form-card">
        <table class="queue-table">
            <thead>
                <tr>
                    <th>Tracking Number</th>
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
                    <td><?= htmlspecialchars($app['section_name']) ?></td>
                    <td>
                        <span class="status-badge <?= strtolower($app['status']) ?>">
                            <?= ucwords(str_replace('_', ' ', $app['status'])) ?>
                        </span>
                    </td>
                    <td><?= date('M d, Y', strtotime($app['submitted_date'])) ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/citizen/track-status.php?tracking=<?= $app['tracking_number'] ?>">
                            <button style="padding: 0.5rem 1rem; background: #0d1b2a; color: white; border: none; border-radius: 6px; cursor: pointer; transition: all 0.3s;">
                                View
                            </button>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

</body>
</html>