<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$officer_name = $_SESSION['name'];
$office_name = $_SESSION['office_name'] ?? 'Government Office';

// Get statistics
$total_sections = $conn->query("SELECT COUNT(*) as count FROM sections WHERE officer_id = $officer_id")->fetch_assoc()['count'];
$total_applications = $conn->query("SELECT COUNT(*) as count FROM applications a JOIN sections s ON a.section_id = s.id WHERE s.officer_id = $officer_id")->fetch_assoc()['count'];
$pending_applications = $conn->query("SELECT COUNT(*) as count FROM applications a JOIN sections s ON a.section_id = s.id WHERE s.officer_id = $officer_id AND a.status IN ('submitted', 'document_verification')")->fetch_assoc()['count'];
$today = date('Y-m-d');
$today_queue = $conn->query("SELECT COUNT(*) as count FROM queue q JOIN sections s ON q.section_id = s.id WHERE s.officer_id = $officer_id AND q.queue_date = '$today'")->fetch_assoc()['count'];
$pending_complaints = $conn->query("SELECT COUNT(*) as count FROM complaints WHERE status = 'pending'")->fetch_assoc()['count'];

$status_data = [];

$status_query = $conn->query("
    SELECT a.status, COUNT(*) as count 
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    WHERE s.officer_id = $officer_id
    GROUP BY a.status
");

while ($row = $status_query->fetch_assoc()) {
    $status_data[$row['status']] = $row['count'];
}

$service_data = [];

$service_query = $conn->query("
    SELECT s.name as service, COUNT(a.id) as count
    FROM sections s
    LEFT JOIN applications a ON a.section_id = s.id
    WHERE s.officer_id = $officer_id
    GROUP BY s.id
");

while ($row = $service_query->fetch_assoc()) {
    $service_data[$row['service']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - SarkariSathi Officer Panel</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            background: #0d1b2a;
            color: white;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar-header {
            padding: 25px;
            border-bottom: 1px solid #1b3a4b;
        }

        .sidebar-header h2 {
            color: #00b4d8;
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 20px;
            padding: 12px;
            background: #1b3a4b;
            border-radius: 10px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #00b4d8, #0096c7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.85rem;
            color: #00b4d8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 15px;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e0e0e0;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.95rem;
            font-weight: 500;
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
            width: 20px;
            margin-right: 12px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .welcome-section {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 100%);
            color: white;
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .welcome-section h1 {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .welcome-section p {
            opacity: 0.9;
            font-size: 1.1rem;
        }

        .office-name {
            color: #00b4d8;
            font-weight: 600;
            margin-top: 8px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,180,216,0.15);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: #0d1b2a;
            margin-bottom: 8px;
        }

        .stat-card p {
            color: #666;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Quick Actions */
        .section-title {
            font-size: 1.5rem;
            color: #0d1b2a;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .action-card {
            background: white;
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: inherit;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,180,216,0.15);
        }

        .action-card i {
            font-size: 2.5rem;
            color: #00b4d8;
            margin-bottom: 15px;
        }

        .action-card span {
            display: block;
            font-weight: 600;
            color: #0d1b2a;
        }

        /* Recent Items */
        .content-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #0d1b2a;
            border-bottom: 2px solid #e0e0e0;
        }

        table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-submitted { background: #d1ecf1; color: #0c5460; }
        .status-document_verification { background: #fff3cd; color: #856404; }
        .status-waiting { background: #fff3cd; color: #856404; }
        .status-checked_in { background: #d1ecf1; color: #0c5460; }
        .status-in_service { background: #cce5ff; color: #004085; }
        .status-completed { background: #d4edda; color: #155724; }

        .btn-view {
            background: #00b4d8;
            color: white;
            padding: 6px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-view:hover {
            background: #0096c7;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
   <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h1>Welcome, <?= htmlspecialchars($officer_name) ?>! 👋</h1>
            <p>Manage your services, applications, queues, and citizen inquiries</p>
            <div class="office-name">📍 <?= htmlspecialchars($office_name) ?></div>
        </div>

        <!-- Statistics -->
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
        </div>

        <h2 class="section-title">Analytics Overview</h2>

<div class="stats-grid">
    <div class="content-card">
        <canvas id="statusChart"></canvas>
    </div>

    <div class="content-card">
        <canvas id="serviceChart"></canvas>
    </div>
</div>

        <!-- Quick Actions -->
        <h2 class="section-title">Quick Actions</h2>
        <div class="actions-grid">
            <a href="manage-services.php" class="action-card">
                <i class="fas fa-plus-circle"></i>
                <span>Add Service</span>
            </a>
            <a href="improved-queue-management.php" class="action-card">
                <i class="fas fa-list-ol"></i>
                <span>Manage Queue</span>
            </a>
            <a href="complaints.php" class="action-card">
                <i class="fas fa-comment-alt"></i>
                <span>Handle Complaints</span>
            </a>
            <a href="messages.php" class="action-card">
                <i class="fas fa-inbox"></i>
                <span>View Messages</span>
            </a>
        </div>

        <!-- Recent Applications -->
        <h2 class="section-title">Recent Applications</h2>
        <div class="content-card">
            <div class="table-wrapper">
                <?php
                $recent_apps = $conn->query("SELECT a.*, s.name as section_name, u.name as citizen_name FROM applications a JOIN sections s ON a.section_id = s.id JOIN users u ON a.citizen_id = u.id WHERE s.officer_id = $officer_id ORDER BY a.created_at DESC LIMIT 5");
                if ($recent_apps->num_rows > 0):
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Citizen</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = $recent_apps->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($app['tracking_number']) ?></strong></td>
                            <td><?= htmlspecialchars($app['citizen_name']) ?></td>
                            <td><?= htmlspecialchars($app['section_name']) ?></td>
                            <td><span class="status-badge status-<?= $app['status'] ?>"><?= ucwords(str_replace('_', ' ', $app['status'])) ?></span></td>
                            <td><?= date('M d, Y', strtotime($app['submitted_date'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">No applications yet</div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Today's Queue -->
        <h2 class="section-title">Today's Queue</h2>
        <div class="content-card">
            <div class="table-wrapper">
                <?php
                $today_bookings = $conn->query("SELECT q.*, s.name as service_name, u.name as citizen_name FROM queue q JOIN sections s ON q.section_id = s.id JOIN users u ON q.citizen_id = u.id WHERE s.officer_id = $officer_id AND q.queue_date = '$today' ORDER BY q.time_slot ASC");
                if ($today_bookings->num_rows > 0):
                ?>
                <table>
                    <thead>
                        <tr>
                            <th>Queue Number</th>
                            <th>Citizen</th>
                            <th>Service</th>
                            <th>Time Slot</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($booking = $today_bookings->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($booking['queue_number']) ?></strong></td>
                            <td><?= htmlspecialchars($booking['citizen_name']) ?></td>
                            <td><?= htmlspecialchars($booking['service_name']) ?></td>
                            <td><?= date('h:i A', strtotime($booking['time_slot'])) ?></td>
                            <td><span class="status-badge status-<?= $booking['status'] ?>"><?= ucwords(str_replace('_', ' ', $booking['status'])) ?></span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">No queue bookings for today</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script>
const statusData = <?php echo json_encode($status_data); ?>;
const serviceData = <?php echo json_encode($service_data); ?>;

// PIE CHART (Status)
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: Object.keys(statusData),
        datasets: [{
            data: Object.values(statusData),
        }]
    }
});

// BAR CHART (Services)
new Chart(document.getElementById('serviceChart'), {
    type: 'bar',
    data: {
        labels: Object.keys(serviceData),
        datasets: [{
            label: 'Applications',
            data: Object.values(serviceData),
        }]
    }
});
</script>
</body>
</html>