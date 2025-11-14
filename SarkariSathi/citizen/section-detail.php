<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$section_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$section_id) {
    header('Location: ' . BASE_URL . '/citizen/sections.php');
    exit();
}

// Fetch section details
$stmt = $conn->prepare("
    SELECT s.*, u.name as officer_name, u.office_name
    FROM sections s
    JOIN users u ON s.officer_id = u.id
    WHERE s.id = ? AND s.is_active = TRUE
");
$stmt->bind_param("i", $section_id);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

if (!$section) {
    header('Location: ' . BASE_URL . '/citizen/sections.php?error=Section not found');
    exit();
}

// Fetch posts/guides for this section
$posts_stmt = $conn->prepare("
    SELECT * FROM posts 
    WHERE section_id = ? 
    ORDER BY created_at DESC
");
$posts_stmt->bind_param("i", $section_id);
$posts_stmt->execute();
$posts = $posts_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

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
    <title><?= htmlspecialchars($section['name']) ?> - Service Details</title>
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

/* ===== SERVICE HEADER ===== */
.service-header {
    background: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.service-header h1 {
    color: #0d1b2a;
    font-size: 2rem;
    margin-bottom: 15px;
}

.service-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.meta-item {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #666;
}

.meta-item i {
    color: #00b4d8;
}

.service-description {
    color: #666;
    line-height: 1.8;
    margin-bottom: 20px;
}

.required-docs {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.required-docs h3 {
    color: #0d1b2a;
    margin-bottom: 15px;
    font-size: 1.1rem;
}

.required-docs ul {
    list-style: none;
    padding: 0;
}

.required-docs li {
    padding: 8px 0;
    padding-left: 25px;
    position: relative;
    color: #666;
}

.required-docs li:before {
    content: "✓";
    position: absolute;
    left: 0;
    color: #00b4d8;
    font-weight: bold;
}

.action-buttons {
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
}

.btn-primary {
    background: #0d1b2a;
    color: white;
}

.btn-primary:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #e0e0e0;
    color: #333;
}

.btn-secondary:hover {
    background: #d0d0d0;
}

/* ===== GUIDES SECTION ===== */
.guides-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.guides-section h2 {
    color: #0d1b2a;
    font-size: 1.5rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.guides-grid {
    display: grid;
    gap: 20px;
}

.guide-card {
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 20px;
    transition: all 0.3s;
}

.guide-card:hover {
    box-shadow: 0 5px 20px rgba(0, 180, 216, 0.1);
    transform: translateY(-2px);
}

.guide-header {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
}

.post-type-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
}

.badge-video {
    background: #cce5ff;
    color: #004085;
}

.badge-photo {
    background: #d4edda;
    color: #155724;
}

.badge-text {
    background: #fff3cd;
    color: #856404;
}

.guide-title {
    color: #0d1b2a;
    font-size: 1.1rem;
    font-weight: 600;
    flex: 1;
}

.guide-content {
    color: #666;
    line-height: 1.6;
    margin-bottom: 15px;
}

.guide-media {
    margin-top: 15px;
}

.guide-media img {
    max-width: 100%;
    border-radius: 8px;
}

.guide-media video {
    max-width: 100%;
    border-radius: 8px;
}

.guide-date {
    color: #999;
    font-size: 0.9rem;
    margin-top: 10px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #999;
}

.empty-state i {
    font-size: 3rem;
    margin-bottom: 15px;
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
    
    .service-meta {
        flex-direction: column;
    }
    
    .action-buttons {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
        text-align: center;
    }
}
    </style>
</head>
<body>
    <!-- Sidebar -->
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
        <!-- Service Header -->
        <div class="service-header">
            <h1><?= htmlspecialchars($section['name']) ?></h1>
            
            <div class="service-meta">
                <div class="meta-item">
                    <i class="fas fa-building"></i>
                    <span><?= htmlspecialchars($section['office_name']) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-clock"></i>
                    <span><?= $section['estimated_days'] ?> days processing</span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-money-bill-wave"></i>
                    <span>Rs. <?= number_format($section['fee_amount'], 2) ?></span>
                </div>
                <div class="meta-item">
                    <i class="fas fa-user-tie"></i>
                    <span>By <?= htmlspecialchars($section['officer_name']) ?></span>
                </div>
            </div>

            <div class="service-description">
                <?= nl2br(htmlspecialchars($section['description'])) ?>
            </div>

            <?php if (!empty($section['required_docs'])): ?>
                <div class="required-docs">
                    <h3>📋 Required Documents</h3>
                    <ul>
                        <?php 
                        $docs = json_decode($section['required_docs'], true);
                        if ($docs && is_array($docs)):
                            foreach ($docs as $doc): ?>
                                <li><?= htmlspecialchars($doc) ?></li>
                            <?php endforeach;
                        endif;
                        ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="action-buttons">
                <a href="<?= BASE_URL ?>/citizen/queue-booking.php?section=<?= $section['id'] ?>" class="btn btn-primary">
                    <i class="fas fa-calendar-check"></i> Book Appointment
                </a>
                <a href="<?= BASE_URL ?>/citizen/sections.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Services
                </a>
            </div>
        </div>

        <!-- Guides Section -->
        <div class="guides-section">
            <h2>📚 Step-by-Step Guides</h2>
            
            <?php if (empty($posts)): ?>
                <div class="empty-state">
                    <i class="fas fa-book-open"></i>
                    <p>No guides available yet for this service</p>
                </div>
            <?php else: ?>
                <div class="guides-grid">
                    <?php foreach ($posts as $post): ?>
                        <div class="guide-card">
                            <div class="guide-header">
                                <span class="post-type-badge badge-<?= $post['post_type'] ?>">
                                    <?php
                                    $icons = ['video' => '🎥', 'photo' => '📷', 'text' => '📝'];
                                    echo $icons[$post['post_type']] . ' ' . ucfirst($post['post_type']);
                                    ?>
                                </span>
                                <h3 class="guide-title"><?= htmlspecialchars($post['title']) ?></h3>
                            </div>
                            
                            <div class="guide-content">
                                <?= nl2br(htmlspecialchars($post['content'])) ?>
                            </div>

                            <?php if ($post['media_url']): ?>
                                <div class="guide-media">
                                    <?php if ($post['post_type'] === 'video'): ?>
                                        <video controls>
                                            <source src="<?= htmlspecialchars($post['media_url']) ?>" type="video/mp4">
                                            Your browser does not support the video tag.
                                        </video>
                                    <?php elseif ($post['post_type'] === 'photo'): ?>
                                        <img src="<?= htmlspecialchars($post['media_url']) ?>" alt="Guide image">
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="guide-date">
                                <i class="fas fa-calendar"></i> 
                                Posted on <?= date('F d, Y', strtotime($post['created_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html> 