<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Get all active sections
$stmt = $conn->prepare("
    SELECT s.*, u.name as officer_name, u.office_name,
           (SELECT COUNT(*) FROM posts WHERE section_id = s.id) as post_count
    FROM sections s 
    JOIN users u ON s.officer_id = u.id 
    WHERE s.is_active = TRUE 
    ORDER BY s.created_at DESC
");
$stmt->execute();
$sections = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Available Services - SarkariSathi</title>
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
    <a href="<?= BASE_URL ?>/citizen/sections.php" class="active">
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
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Available Services</h1>
        <p>Browse all government services and view detailed guides</p>
    </div>

    <?php if (empty($sections)): ?>
        <div class="empty-state">
            <i class="fas fa-folder-open" style="font-size: 4rem; color: #00b4d8; margin-bottom: 1rem;"></i>
            <h3>No services available</h3>
            <p>Services will appear here once officers create them</p>
        </div>
    <?php else: ?>
        <div class="service-grid">
            <?php foreach ($sections as $section): ?>
                <div class="service-card">
                    <div class="service-header">
                        <h3><?= htmlspecialchars($section['name']) ?></h3>
                        <span class="post-count"><?= $section['post_count'] ?> guides</span>
                    </div>
                    
                    <div class="service-details">
                        <p class="description"><?= htmlspecialchars($section['description']) ?></p>
                        
                        <div class="service-info">
                            <div class="info-item">
                                <span class="label">Processing Time:</span>
                                <span class="value"><?= $section['estimated_days'] ?> days</span>
                            </div>
                            
                            <div class="info-item">
                                <span class="label">Fee:</span>
                                <span class="value">Rs. <?= number_format($section['fee_amount'], 2) ?></span>
                            </div>
                            
                            <div class="info-item">
                                <span class="label">Office:</span>
                                <span class="value"><?= htmlspecialchars($section['office_name']) ?></span>
                            </div>
                        </div>

                        <?php if (!empty($section['required_docs'])): ?>
                            <div class="required-docs">
                                <strong>Required Documents:</strong>
                                <?php 
                                $docs = json_decode($section['required_docs'], true);
                                if ($docs && is_array($docs)):
                                ?>
                                    <ul>
                                        <?php foreach ($docs as $doc): ?>
                                            <li><?= htmlspecialchars($doc) ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="service-actions">
                        <a href="<?= BASE_URL ?>/citizen/section-detail.php?id=<?= $section['id'] ?>" class="btn btn-primary">
                            View Details & Guides
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.service-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
}

.service-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.service-card:hover {
    box-shadow: 0 5px 20px rgba(0,180,216,0.2);
    transform: translateY(-5px);
}

.service-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.service-header h3 {
    color: #0d1b2a;
    font-size: 1.25rem;
}

.post-count {
    background: #667eea;
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.875rem;
}

.description {
    color: #666;
    line-height: 1.6;
    margin-bottom: 20px;
}

.service-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 20px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
}

.info-item .label {
    color: #666;
    font-weight: 500;
}

.info-item .value {
    color: #333;
    font-weight: 600;
}

.required-docs {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.required-docs strong {
    display: block;
    color: #333;
    margin-bottom: 10px;
}

.required-docs ul {
    margin-left: 20px;
    color: #666;
}

.required-docs li {
    margin-bottom: 5px;
}

.service-actions .btn {
    width: 100%;
    padding: 0.75rem;
    text-align: center;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    display: block;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.empty-state h3 {
    color: #0d1b2a;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
}
</style>

</body>
</html>