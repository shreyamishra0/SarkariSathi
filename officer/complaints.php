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
        SET admin_response = ?, status = ?, resolved_at = ?
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
</head>
<body>
   <?php include 'includes/sidebar.php'; ?>

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

                        <?php if ($complaint['admin_response']): ?>
                            <div class="current-response">
                                <strong>Your Response:</strong>
                                <p><?php echo htmlspecialchars($complaint['admin_response']); ?></p>
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
                                          rows="4" required><?php echo htmlspecialchars($complaint['admin_response'] ?? ''); ?></textarea>
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