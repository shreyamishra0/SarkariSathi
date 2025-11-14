<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

// Handle actions
if (isset($_GET['checkin'])) {
    $id = (int)$_GET['checkin'];
    $conn->query("UPDATE queue SET status='checked_in', checked_in_at=NOW() WHERE id=$id");
    header("Location: queue-management.php");
    exit;
}

if (isset($_GET['call'])) {
    $id = (int)$_GET['call'];
    $conn->query("UPDATE queue SET status='in_service' WHERE id=$id");
    header("Location: queue-management.php");
    exit;
}

if (isset($_GET['served'])) {
    $id = (int)$_GET['served'];
    $conn->query("UPDATE queue SET status='completed' WHERE id=$id");
    header("Location: queue-management.php");
    exit;
}

$today = date("Y-m-d");
$queue = $conn->query("
    SELECT q.*, u.name as citizen_name, s.name as service_name
    FROM queue q 
    JOIN users u ON q.citizen_id = u.id
    LEFT JOIN sections s ON q.section_id = s.id
    WHERE q.queue_date = '$today'
    ORDER BY q.queue_number ASC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Queue Management - Officer Panel</title>
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/officer.css">
        <link rel="stylesheet" href="../assets/css/officer-v2.css">

    
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-list-ol"></i>
                Queue Management
            </h1>
            <p class="page-subtitle">Manage today's queue and serve citizens efficiently</p>
        </div>

        <?php
        // Get queue statistics
        $stats = $conn->query("
            SELECT 
                COUNT(*) as total,
                SUM(status = 'waiting') as waiting,
                SUM(status = 'checked_in') as checked_in,
                SUM(status = 'in_service') as in_service,
                SUM(status = 'completed') as completed
            FROM queue 
            WHERE queue_date = '$today'
        ")->fetch_assoc();
        ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total in Queue</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['waiting']; ?></h3>
                <p>Waiting</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['checked_in']; ?></h3>
                <p>Checked In</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $stats['completed']; ?></h3>
                <p>Served Today</p>
            </div>
        </div>

        <!-- Queue Table -->
        <div class="queue-section">
            <h3 class="section-title">
                <i class="fas fa-users"></i>
                Today's Queue (<?php echo date('F j, Y', strtotime($today)); ?>)
            </h3>

            <?php if ($queue->num_rows == 0): ?>
                <div class="no-bookings">
                    <i class="fas fa-calendar-times"></i>
                    <h3 style="color: #666; margin-bottom: 10px;">No Queue Bookings Today</h3>
                    <p>There are no queue bookings scheduled for today.</p>
                </div>
            <?php else: ?>
                <table class="queue-table">
                    <thead>
                        <tr>
                            <th>Queue No</th>
                            <th>Citizen Name</th>
                            <th>Service</th>
                            <th>Time Slot</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($row = $queue->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="queue-number"><?php echo htmlspecialchars($row['queue_number']); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($row['citizen_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['service_name'] ?? 'General Service'); ?></td>
                            <td><?php echo date("h:i A", strtotime($row['time_slot'])); ?></td>
                            <td>
                                <span class="badge <?php echo $row['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <?php if ($row['status'] == 'waiting'): ?>
                                        <a href="?checkin=<?php echo $row['id']; ?>" class="btn btn-green">
                                            <i class="fas fa-check"></i> Check-in
                                        </a>
                                    <?php elseif ($row['status'] == 'checked_in'): ?>
                                        <a href="?call=<?php echo $row['id']; ?>" class="btn btn-blue">
                                            <i class="fas fa-bullhorn"></i> Call
                                        </a>
                                    <?php elseif ($row['status'] == 'in_service'): ?>
                                        <a href="?served=<?php echo $row['id']; ?>" class="btn btn-orange">
                                            <i class="fas fa-check-double"></i> Complete
                                        </a>
                                    <?php else: ?>
                                        <span class="btn btn-secondary" style="cursor: default;">
                                            <i class="fas fa-check-circle"></i> Served
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds
        setTimeout(() => {
            window.location.reload();
        }, 30000);
    </script>
</body>
</html>