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

// Handle complaint response
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['response'])) {
    $complaint_id = $_POST['complaint_id'];
    $response = $_POST['response'];
    $status = $_POST['status'];
    
    $resolved_at = $status === 'resolved' ? date('Y-m-d H:i:s') : null;
    
    $stmt = $conn->prepare("
        UPDATE complaints 
        SET officer_response = ?, status = ?, resolved_at = ?
        WHERE id = ?
    ");
    $stmt->bind_param("sssi", $response, $status, $resolved_at, $complaint_id);
    
    if ($stmt->execute()) {
        $success = "Response submitted successfully!";
    } else {
        $error = "Failed to submit response. Please try again.";
    }
}

// Get all complaints
$complaints_result = $conn->query("
    SELECT c.*, u.name as citizen_name, u.phone
    FROM complaints c
    JOIN users u ON c.citizen_id = u.id
    ORDER BY 
        CASE 
            WHEN c.status = 'pending' THEN 1
            WHEN c.status = 'in_progress' THEN 2
            ELSE 3
        END,
        c.created_at DESC
");

$complaints = [];
if ($complaints_result) {
    $complaints = $complaints_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Complaints Management - Officer Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: #f8f9fa;
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: #0d1b2a;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 25px;
            border-bottom: 1px solid #1b3a4b;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #00b4d8;
            font-size: 1.6rem;
            margin-bottom: 5px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 15px;
            padding: 10px;
            background: #1b3a4b;
            border-radius: 8px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: #00b4d8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .user-details {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-role {
            font-size: 0.8rem;
            color: #00b4d8;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0 15px;
        }

        .sidebar-menu li {
            margin-bottom: 8px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: #e0e0e0;
            text-decoration: none;
            transition: all 0.3s;
            border-radius: 8px;
            font-size: 0.95rem;
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
            margin-right: 12px;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            color: #0d1b2a;
            font-size: 2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Stats Section */
        .complaints-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 2.5em;
            color: #0d1b2a;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #666;
            font-weight: 600;
            font-size: 1rem;
        }

        /* Complaints List */
        .complaints-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .complaint-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #00b4d8;
        }

        .complaint-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .complaint-header h3 {
            color: #0d1b2a;
            font-size: 1.3rem;
            margin-bottom: 10px;
        }

        .complaint-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
            font-size: 0.9rem;
            color: #666;
        }

        .complaint-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fff3cd; color: #856404; }
        .status-in_progress { background: #d1ecf1; color: #0c5460; }
        .status-resolved { background: #d4edda; color: #155724; }

        .complaint-description {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .complaint-description strong {
            color: #0d1b2a;
            margin-bottom: 8px;
            display: block;
        }

        .complaint-description p {
            color: #333;
            line-height: 1.5;
        }

        .current-response {
            margin-bottom: 20px;
            padding: 15px;
            background: #e3f2fd;
            border-radius: 8px;
            border-left: 4px solid #00b4d8;
        }

        .current-response strong {
            color: #0d1b2a;
            margin-bottom: 8px;
            display: block;
        }

        .current-response p {
            color: #333;
            line-height: 1.5;
            margin-bottom: 8px;
        }

        .current-response small {
            color: #666;
            font-size: 0.8rem;
        }

        /* Form Styles */
        .response-form {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0d1b2a;
        }

        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.3s;
        }

        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #00b4d8;
            color: white;
        }

        .btn-primary:hover {
            background: #0099c3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.3);
        }

        /* Messages */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }
    </style>
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
            <li><a href="manage-services.php"><i class="fas fa-cogs"></i> Manage Services</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="complaints.php" class="active"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-exclamation-circle"></i>
                🚨 Complaints Management
            </h1>
            <p class="page-subtitle">Review and respond to citizen complaints</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="complaints-stats">
            <div class="stat-card">
                <h3><?php echo count(array_filter($complaints, fn($c) => $c['status'] === 'pending')); ?></h3>
                <p>Pending</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($complaints, fn($c) => $c['status'] === 'in_progress')); ?></h3>
                <p>In Progress</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($complaints, fn($c) => $c['status'] === 'resolved')); ?></h3>
                <p>Resolved</p>
            </div>
        </div>

        <div class="complaints-list">
            <?php if (empty($complaints)): ?>
                <div class="empty-state">
                    <i class="fas fa-comments"></i>
                    <p>No complaints found</p>
                    <p style="margin-top: 10px; font-size: 0.9rem;">Complaints from citizens will appear here.</p>
                </div>
            <?php else: ?>
                <?php foreach ($complaints as $complaint): ?>
                    <div class="complaint-card">
                        <div class="complaint-header">
                            <div>
                                <h3><?php echo htmlspecialchars($complaint['title'] ?? 'No Title'); ?></h3>
                                <div class="complaint-meta">
                                    <span class="citizen">
                                        <i class="fas fa-user"></i>
                                        By: <?php echo htmlspecialchars($complaint['citizen_name']); ?> (<?php echo htmlspecialchars($complaint['phone']); ?>)
                                    </span>
                                    <span class="type">
                                        <i class="fas fa-tag"></i>
                                        Category: <?php echo htmlspecialchars($complaint['category'] ?? 'General'); ?>
                                    </span>
                                    <span class="date">
                                        <i class="fas fa-calendar"></i>
                                        Submitted: <?php echo date('M j, Y g:i A', strtotime($complaint['created_at'])); ?>
                                    </span>
                                </div>
                            </div>
                            <span class="status-badge status-<?php echo $complaint['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                            </span>
                        </div>
                        
                        <div class="complaint-description">
                            <strong>Description:</strong>
                            <p><?php echo htmlspecialchars($complaint['description']); ?></p>
                        </div>

                        <?php if ($complaint['officer_response']): ?>
                            <div class="current-response">
                                <strong>Your Response:</strong>
                                <p><?php echo htmlspecialchars($complaint['officer_response']); ?></p>
                                <?php if ($complaint['resolved_at']): ?>
                                    <small>
                                        <i class="fas fa-check-circle"></i>
                                        Resolved on: <?php echo date('M j, Y g:i A', strtotime($complaint['resolved_at'])); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" class="response-form">
                            <input type="hidden" name="complaint_id" value="<?php echo $complaint['id']; ?>">
                            
                            <div class="form-group">
                                <label for="response_<?php echo $complaint['id']; ?>">Your Response</label>
                                <textarea id="response_<?php echo $complaint['id']; ?>" name="response" 
                                          placeholder="Type your response to the citizen..."
                                          rows="4" required><?php echo htmlspecialchars($complaint['officer_response'] ?? ''); ?></textarea>
                            </div>

                            <div class="form-group">
                                <label for="status_<?php echo $complaint['id']; ?>">Update Status</label>
                                <select id="status_<?php echo $complaint['id']; ?>" name="status" required>
                                    <option value="pending" <?php echo $complaint['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $complaint['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="resolved" <?php echo $complaint['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i>
                                Submit Response
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>