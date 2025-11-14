<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];

// Fetch applications for this officer's sections
$applications = $conn->query("
    SELECT a.*, s.name as section_name, u.name as citizen_name
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    JOIN users u ON a.citizen_id = u.id
    WHERE s.officer_id = $officer_id
    ORDER BY a.created_at DESC
");

if (!$applications) {
    die("Database error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Applications - Officer Panel</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/officer.css">
    <link rel="stylesheet" href="../assets/css/application.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏛️ Officer Panel</h2>
            <small style="color: #00b4d8;"><?php echo htmlspecialchars($_SESSION['name']); ?></small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php" class="active"><i class="fas fa-file-alt"></i> Applications</a></li>

            <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
                        <li><a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>

            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">Applications</h1>
            <p class="page-subtitle">Manage citizen applications for your services</p>
        </div>

        <?php if ($applications->num_rows == 0): ?>
            <div class="no-applications">
                <i class="fas fa-file-alt"></i>
                <p>No applications found for your sections.</p>
                <p style="margin-top: 10px; font-size: 0.9rem;">Applications will appear here when citizens apply to your services.</p>
            </div>
        <?php else: ?>
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
                    <?php while ($app = $applications->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span class="tracking-number">
                                <?php echo htmlspecialchars($app['tracking_number']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($app['citizen_name']); ?></td>
                        <td><?php echo htmlspecialchars($app['section_name']); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $app['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $app['status'])); ?>
                            </span>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($app['submitted_date'])); ?></td>
                        <td>
                            <a href="view-application.php?id=<?php echo $app['id']; ?>">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>