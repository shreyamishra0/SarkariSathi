<?php
require_once __DIR__ . '/../includes/admin-check.php';

// Handle verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $officer_id = $_POST['officer_id'];
    $action = $_POST['action'];
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE users SET is_verified = 1 WHERE id = ? AND role = 'officer'");
        $stmt->bind_param("i", $officer_id);
        
        if ($stmt->execute()) {
            // Send notification to officer
            $officer_query = $conn->query("SELECT phone, name FROM users WHERE id = $officer_id");
            $officer = $officer_query->fetch_assoc();
            
            // You can add SMS/Email notification here
            $success = "Officer {$officer['name']} has been approved successfully!";
        } else {
            $error = "Failed to approve officer.";
        }
    } elseif ($action === 'reject') {
        $reason = $_POST['reason'] ?? 'Not specified';
        
        // You might want to store rejection reason in a separate table
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'officer'");
        $stmt->bind_param("i", $officer_id);
        
        if ($stmt->execute()) {
            $success = "Officer registration has been rejected.";
        } else {
            $error = "Failed to reject officer.";
        }
    }
}

// Get all pending officers
$pending_officers = $conn->query("
    SELECT * FROM users 
    WHERE role = 'officer' AND is_verified = 0 
    ORDER BY created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Officers - Admin Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🛡️ Admin Panel</h2>
    <a href="<?= BASE_URL ?>/admin/dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/admin/verify-officers.php" class="active">
        <i class="fas fa-user-check"></i> Verify Officers
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-users.php">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php">
        <i class="fas fa-exclamation-triangle"></i> Complaints
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
        <h1>Officer Verification</h1>
        <p>Review and approve pending officer registrations</p>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($pending_officers && $pending_officers->num_rows > 0): ?>
    <div class="officers-grid">
        <?php while ($officer = $pending_officers->fetch_assoc()): ?>
        <div class="officer-card">
            <div class="officer-header">
                <div class="officer-avatar">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="officer-info">
                    <h3><?= htmlspecialchars($officer['name']) ?></h3>
                    <p class="office-name"><?= htmlspecialchars($officer['office_name']) ?></p>
                </div>
            </div>
            
            <div class="officer-details">
                <div class="detail-row">
                    <span class="label"><i class="fas fa-phone"></i> Phone:</span>
                    <span class="value"><?= htmlspecialchars($officer['phone']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-envelope"></i> Email:</span>
                    <span class="value"><?= htmlspecialchars($officer['email'] ?? 'Not provided') ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar"></i> Registered:</span>
                    <span class="value"><?= date('M d, Y H:i', strtotime($officer['created_at'])) ?></span>
                </div>
            </div>

            <div class="officer-actions">
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this officer?')">
                    <input type="hidden" name="officer_id" value="<?= $officer['id'] ?>">
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="btn-approve">
                        <i class="fas fa-check"></i> Approve
                    </button>
                </form>
                
                <button class="btn-reject" onclick="showRejectModal(<?= $officer['id'] ?>, '<?= htmlspecialchars($officer['name']) ?>')">
                    <i class="fas fa-times"></i> Reject
                </button>
            </div>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-check-circle"></i>
        <h3>No Pending Verifications</h3>
        <p>All officer registrations have been processed.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Reject Modal -->
<div id="rejectModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2>Reject Officer Registration</h2>
        <p>Are you sure you want to reject <strong id="officerName"></strong>?</p>
        
        <form method="POST">
            <input type="hidden" name="officer_id" id="rejectOfficerId">
            <input type="hidden" name="action" value="reject">
            
            <div class="form-group">
                <label for="reason">Rejection Reason:</label>
                <textarea name="reason" id="reason" rows="4" placeholder="Enter reason for rejection..." required></textarea>
            </div>
            
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeRejectModal()">Cancel</button>
                <button type="submit" class="btn-danger">Reject Registration</button>
            </div>
        </form>
    </div>
</div>

<style>
.officers-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.officer-card {
    background: white;
    border-radius: 15px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.officer-card:hover {
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
    transform: translateY(-5px);
}

.officer-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.officer-avatar {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #00b4d8, #0d1b2a);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.5rem;
}

.officer-info h3 {
    margin: 0;
    color: #0d1b2a;
    font-size: 1.25rem;
}

.office-name {
    color: #666;
    margin: 0.25rem 0 0 0;
    font-size: 0.9rem;
}

.officer-details {
    margin-bottom: 1.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #f5f5f5;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    color: #666;
    font-weight: 500;
}

.detail-row .label i {
    margin-right: 0.5rem;
    color: #00b4d8;
}

.detail-row .value {
    color: #333;
    font-weight: 600;
}

.officer-actions {
    display: flex;
    gap: 0.75rem;
}

.btn-approve, .btn-reject {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    font-size: 0.95rem;
}

.btn-approve {
    background: #28a745;
    color: white;
}

.btn-approve:hover {
    background: #218838;
    transform: translateY(-2px);
}

.btn-reject {
    background: #dc3545;
    color: white;
}

.btn-reject:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 15px;
    margin-top: 2rem;
}

.empty-state i {
    font-size: 4rem;
    color: #28a745;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #0d1b2a;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
}

.alert {
    padding: 1rem 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 15px;
    max-width: 500px;
    width: 90%;
}

.modal-content h2 {
    margin-bottom: 1rem;
    color: #0d1b2a;
}

.form-group {
    margin: 1.5rem 0;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #0d1b2a;
}

.form-group textarea {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-family: inherit;
    resize: vertical;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.btn-secondary, .btn-danger {
    flex: 1;
    padding: 0.75rem;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-danger {
    background: #dc3545;
    color: white;
}

.btn-danger:hover {
    background: #c82333;
}
</style>

<script>
function showRejectModal(officerId, officerName) {
    document.getElementById('rejectOfficerId').value = officerId;
    document.getElementById('officerName').textContent = officerName;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        closeRejectModal();
    }
}
</script>

</body>
</html>