<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$search_result = null;
$search_error = null;

// Handle search by tracking number
if (isset($_GET['tracking']) && !empty($_GET['tracking'])) {
    $tracking_number = trim($_GET['tracking']);
    
    $stmt = $conn->prepare("
        SELECT a.*, s.name as section_name, s.office_name
        FROM applications a
        JOIN sections s ON a.section_id = s.id
        WHERE a.tracking_number = ? AND a.citizen_id = ?
    ");
    
    if ($stmt === false) {
        $search_error = "Database error. Please contact support.";
    } else {
        $stmt->bind_param("si", $tracking_number, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $search_result = $result->fetch_assoc();
        } else {
            $search_error = "No application found with this tracking number.";
        }
        $stmt->close();
    }
}

// Get all applications for this citizen
$all_applications = [];
$stmt = $conn->prepare("
    SELECT a.*, s.name as section_name, s.office_name
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    WHERE a.citizen_id = ?
    ORDER BY a.created_at DESC
");

if ($stmt === false) {
    $db_error = "Database error: " . htmlspecialchars($conn->error);
} else {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $all_applications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Application Status - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/citizen.css">
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🏛️ SarkariSathi</h2>
    <a href="<?= BASE_URL ?>/citizen/dashboard.php">
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
    <a href="<?= BASE_URL ?>/citizen/track-status.php" class="active">
        <i class="fas fa-search"></i> Track Status
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Track Application Status</h1>
        <p>Monitor your application progress in real-time</p>
    </div>

    <?php if (isset($db_error)): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>Database Error</h3>
            <p><?= $db_error ?></p>
            <p>Please make sure you have run the database.sql file to create all required tables.</p>
        </div>
    <?php else: ?>

    <!-- Search Section -->
    <div class="search-section">
        <h2>Search by Tracking Number</h2>
        <form method="GET" class="search-form">
            <div class="search-input-group">
                <input type="text" 
                       name="tracking" 
                       placeholder="Enter tracking number (e.g., PAS-2025-0123)"
                       value="<?= htmlspecialchars($_GET['tracking'] ?? '') ?>"
                       required>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Track
                </button>
            </div>
        </form>
        
        <?php if ($search_error): ?>
            <div class="error-message"><?= htmlspecialchars($search_error) ?></div>
        <?php endif; ?>
    </div>

    <!-- Display search result if found -->
    <?php if ($search_result): ?>
        <div class="application-detail">
            <div class="application-header">
                <h3>Application Details</h3>
                <span class="tracking-number"><?= htmlspecialchars($search_result['tracking_number']) ?></span>
            </div>
            
            <div class="application-info">
                <div class="info-row">
                    <span class="label">Service:</span>
                    <span class="value"><?= htmlspecialchars($search_result['section_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Office:</span>
                    <span class="value"><?= htmlspecialchars($search_result['office_name']) ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status:</span>
                    <span class="value">
                        <span class="status-badge <?= strtolower($search_result['status']) ?>">
                            <?= ucwords(str_replace('_', ' ', $search_result['status'])) ?>
                        </span>
                    </span>
                </div>
                <div class="info-row">
                    <span class="label">Submitted Date:</span>
                    <span class="value"><?= date('F d, Y', strtotime($search_result['submitted_date'])) ?></span>
                </div>
                <?php if ($search_result['ready_date']): ?>
                <div class="info-row">
                    <span class="label">Ready Date:</span>
                    <span class="value"><?= date('F d, Y', strtotime($search_result['ready_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($search_result['officer_notes']): ?>
            <div class="officer-notes">
                <strong>Officer Notes:</strong>
                <p><?= nl2br(htmlspecialchars($search_result['officer_notes'])) ?></p>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- All Applications Section -->
    <div class="applications-section">
        <h2>My Applications</h2>
        
        <?php if (empty($all_applications)): ?>
            <div class="empty-state">
                <i class="fas fa-file-alt" style="font-size: 4rem; color: #00b4d8; margin-bottom: 1rem;"></i>
                <h3>No applications found</h3>
                <p>Applications will appear here after you submit documents at the office.</p>
                <p style="color: #666; font-size: 0.9rem; margin-top: 1rem;">
                    Note: Officers create applications on your behalf when you visit the office with your documents.
                </p>
                <a href="<?= BASE_URL ?>/citizen/sections.php" class="btn btn-primary" style="margin-top: 20px;">
                    <i class="fas fa-folder-open"></i> Browse Services
                </a>
            </div>
        <?php else: ?>
            <div class="applications-grid">
                <?php foreach ($all_applications as $app): ?>
                    <div class="application-card">
                        <div class="card-header">
                            <strong><?= htmlspecialchars($app['tracking_number']) ?></strong>
                            <span class="status-badge <?= strtolower($app['status']) ?>">
                                <?= ucwords(str_replace('_', ' ', $app['status'])) ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <p class="service-name"><?= htmlspecialchars($app['section_name']) ?></p>
                            <p class="office-name"><?= htmlspecialchars($app['office_name']) ?></p>
                            <p class="submit-date">
                                <i class="fas fa-calendar"></i> 
                                Submitted: <?= date('M d, Y', strtotime($app['submitted_date'])) ?>
                            </p>
                        </div>
                        
                        <div class="card-footer">
                            <a href="?tracking=<?= $app['tracking_number'] ?>" class="btn-view">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php endif; ?>
</div>

<style>
.search-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.search-section h2 {
    color: #0d1b2a;
    margin-bottom: 20px;
}

.search-form {
    max-width: 600px;
}

.search-input-group {
    display: flex;
    gap: 10px;
}

.search-input-group input {
    flex: 1;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
}

.search-input-group input:focus {
    outline: none;
    border-color: #00b4d8;
}

.search-input-group button {
    padding: 12px 24px;
    white-space: nowrap;
}

.error-message {
    background: #f8d7da;
    color: #721c24;
    padding: 12px;
    border-radius: 8px;
    margin-top: 15px;
    border: 1px solid #f5c6cb;
}

.error-box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
    margin: 20px 0;
    border-left: 5px solid #dc3545;
}

.error-box i {
    font-size: 3rem;
    color: #dc3545;
    margin-bottom: 20px;
}

.error-box h3 {
    color: #0d1b2a;
    margin-bottom: 10px;
}

.error-box p {
    color: #666;
    margin-bottom: 10px;
}

.application-detail {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 30px;
}

.application-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.application-header h3 {
    color: #0d1b2a;
}

.tracking-number {
    background: linear-gradient(135deg, #00b4d8, #0d1b2a);
    color: white;
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: bold;
}

.application-info {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-row .label {
    color: #666;
    font-weight: 500;
}

.info-row .value {
    color: #333;
    font-weight: 600;
}

.officer-notes {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-top: 20px;
}

.officer-notes strong {
    display: block;
    color: #333;
    margin-bottom: 10px;
}

.officer-notes p {
    color: #666;
    line-height: 1.6;
}

.applications-section {
    margin-top: 40px;
}

.applications-section h2 {
    color: #0d1b2a;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.applications-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.application-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    overflow: hidden;
    transition: all 0.3s;
}

.application-card:hover {
    box-shadow: 0 5px 20px rgba(0, 180, 216, 0.2);
    transform: translateY(-3px);
}

.card-header {
    background: #f8f9fa;
    padding: 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-body {
    padding: 20px;
}

.service-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: #0d1b2a;
    margin-bottom: 8px;
}

.office-name {
    color: #666;
    margin-bottom: 12px;
}

.submit-date {
    color: #666;
    font-size: 0.9rem;
}

.card-footer {
    padding: 15px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.btn-view {
    display: block;
    text-align: center;
    padding: 10px;
    background: #0d1b2a;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    transition: all 0.3s;
}

.btn-view:hover {
    background: #00b4d8;
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

.status-badge.ready_for_pickup {
    background: #d4edda;
    color: #155724;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.rejected {
    background: #f8d7da;
    color: #721c24;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
}

.empty-state h3 {
    color: #0d1b2a;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .search-input-group {
        flex-direction: column;
    }
    
    .applications-grid {
        grid-template-columns: 1fr;
    }
    
    .application-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

</body>
</html>