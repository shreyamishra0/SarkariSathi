<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$citizen_name = $_SESSION['name'];
$user_id = $_SESSION['user_id'];

// Get section ID from URL
$section_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($section_id === 0) {
    header('Location: ' . BASE_URL . '/citizen/sections.php');
    exit();
}

// Fetch section details
$stmt = $conn->prepare("
    SELECT s.*, u.name as officer_name, u.office_name, u.phone as officer_phone
    FROM sections s
    JOIN users u ON s.officer_id = u.id
    WHERE s.id = ? AND s.is_active = TRUE
");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$section) {
    header('Location: ' . BASE_URL . '/citizen/sections.php?error=Section not found');
    exit();
}

// Fetch all posts/guides for this section
$posts_stmt = $conn->prepare("
    SELECT * FROM posts 
    WHERE section_id = ? 
    ORDER BY created_at DESC
");
$posts_stmt->bind_param("i", $section_id);
$posts_stmt->execute();
$posts = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$posts_stmt->close();

// Get unread messages count
$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = $user_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($section['name']) ?> - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ===== GLOBAL STYLES ===== */
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

/* ===== SIDEBAR ===== */
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

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 300px;
    padding: 2rem;
    max-width: calc(100% - 300px);
}

.header {
    background: #ffffff;
    padding: 1rem 1.2rem;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.header h1 {
    color: #0d1b2a;
    font-size: 1.4rem;
    font-weight: 700;
}

.back-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    background: #f0f0f0;
    color: #333;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s;
}

.back-btn:hover {
    background: #e0e0e0;
}

/* ===== SECTION DETAILS ===== */
.section-detail-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    margin-bottom: 2rem;
}

.section-title {
    color: #0d1b2a;
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 1rem;
}

.section-description {
    color: #666;
    font-size: 1.1rem;
    line-height: 1.6;
    margin-bottom: 2rem;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.info-box {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    border-left: 4px solid #00b4d8;
}

.info-box h3 {
    color: #0d1b2a;
    font-size: 0.9rem;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-box p {
    color: #333;
    font-size: 1.1rem;
    font-weight: 600;
}

.required-docs {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.required-docs h3 {
    color: #856404;
    margin-bottom: 1rem;
}

.required-docs ul {
    list-style: none;
    padding: 0;
}

.required-docs li {
    padding: 0.5rem 0;
    color: #333;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.required-docs li:before {
    content: "✓";
    color: #28a745;
    font-weight: bold;
    font-size: 1.2rem;
}

.contact-section {
    background: #d1ecf1;
    border-left: 4px solid #00b4d8;
    padding: 1.5rem;
    border-radius: 10px;
}

.contact-section h3 {
    color: #0c5460;
    margin-bottom: 1rem;
}

.contact-section p {
    color: #333;
    margin-bottom: 0.5rem;
}

/* ===== POSTS SECTION ===== */
.posts-section {
    margin-top: 3rem;
}

.section-heading {
    color: #0d1b2a;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 3px solid #00b4d8;
}

.posts-grid {
    display: grid;
    gap: 1.5rem;
}

.post-card {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    transition: all 0.3s;
}

.post-card:hover {
    box-shadow: 0 5px 20px rgba(0, 180, 216, 0.2);
    transform: translateY(-3px);
}

.post-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 1rem;
    padding-bottom: 1rem;
    border-bottom: 2px solid #f0f0f0;
}

.post-title {
    color: #0d1b2a;
    font-size: 1.5rem;
    font-weight: 700;
}

.post-date {
    color: #666;
    font-size: 0.9rem;
}

.post-content {
    color: #333;
    line-height: 1.8;
    font-size: 1rem;
}

.post-content h1,
.post-content h2,
.post-content h3 {
    color: #0d1b2a;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
}

.post-content ul,
.post-content ol {
    margin-left: 2rem;
    margin-bottom: 1rem;
}

.post-content li {
    margin-bottom: 0.5rem;
}

.post-content p {
    margin-bottom: 1rem;
}

.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 4rem;
    color: #00b4d8;
    margin-bottom: 1rem;
}

.empty-state h3 {
    color: #0d1b2a;
    margin-bottom: 0.5rem;
}

.empty-state p {
    color: #666;
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
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}
    </style>
</head>
<body>
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
        <a href="<?= BASE_URL ?>/citizen/smart-track-status.php">
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

    <div class="main-content">
        <div class="header">
            <h1>Service Details</h1>
            <div style="display: flex; align-items: center; gap: 1rem;">
                <a href="<?= BASE_URL ?>/citizen/sections.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Services
                </a>
                <div style="color:#666;">Welcome, <?= htmlspecialchars($citizen_name) ?></div>
            </div>
        </div>

        <!-- Section Details -->
        <div class="section-detail-card">
            <h1 class="section-title"><?= htmlspecialchars($section['name']) ?></h1>
            <p class="section-description"><?= htmlspecialchars($section['description']) ?></p>

            <div class="info-grid">
                <div class="info-box">
                    <h3>Processing Time</h3>
                    <p><?= htmlspecialchars($section['estimated_days']) ?> Days</p>
                </div>

                <div class="info-box">
                    <h3>Fee Amount</h3>
                    <p>Rs. <?= number_format($section['fee_amount'], 2) ?></p>
                </div>

                <div class="info-box">
                    <h3>Office Location</h3>
                    <p><?= htmlspecialchars($section['office_name']) ?></p>
                </div>

                <div class="info-box">
                    <h3>Total Guides</h3>
                    <p><?= count($posts) ?> Available</p>
                </div>
            </div>

            <?php if (!empty($section['required_docs'])): ?>
                <?php 
                $docs = json_decode($section['required_docs'], true);
                if ($docs && is_array($docs) && count($docs) > 0):
                ?>
                <div class="required-docs">
                    <h3><i class="fas fa-file-alt"></i> Required Documents</h3>
                    <ul>
                        <?php foreach ($docs as $doc): ?>
                            <li><?= htmlspecialchars($doc) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="contact-section">
                <h3><i class="fas fa-user-tie"></i> Contact Officer</h3>
                <p><strong>Officer:</strong> <?= htmlspecialchars($section['officer_name']) ?></p>
                <p><strong>Office:</strong> <?= htmlspecialchars($section['office_name']) ?></p>
                <?php if (!empty($section['officer_phone'])): ?>
                <p><strong>Phone:</strong> <?= htmlspecialchars($section['officer_phone']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Posts/Guides Section -->
        <div class="posts-section">
            <h2 class="section-heading">
                <i class="fas fa-book"></i> Step-by-Step Guides & Instructions
            </h2>

            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <h3>No Guides Available Yet</h3>
                    <p>The officer hasn't created any guides for this service yet.</p>
                    <p style="margin-top: 1rem;">Please check back later or contact the office directly.</p>
                </div>
            <?php else: ?>
                <div class="posts-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="post-card">
                            <div class="post-header">
                                <h3 class="post-title"><?= htmlspecialchars($post['title']) ?></h3>
                                <span class="post-date">
                                    <i class="fas fa-calendar"></i>
                                    <?= date('M d, Y', strtotime($post['created_at'])) ?>
                                </span>
                            </div>
                            <div class="post-content">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>