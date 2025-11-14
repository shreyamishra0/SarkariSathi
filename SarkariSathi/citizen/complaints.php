<?php
session_start();
require_once __DIR__ . '/../config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

// Ensure name exists in session
if (!isset($_SESSION['name'])) {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$citizen_name = $_SESSION['name'];

// Handle new complaint submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_complaint'])) {
    $title = trim($_POST['title']);
    $category = $_POST['category'];
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $priority = $_POST['priority'];

    $image_path = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $upload_dir = __DIR__ . '/uploads/complaints/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid() . '.' . $file_extension;
        $image_path = 'uploads/complaints/' . $file_name; // store relative path for browser
        move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $file_name);
    }

    $stmt = $conn->prepare("
        INSERT INTO complaints (citizen_id, title, category, description, location, priority, image_path, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
    ");
    $stmt->bind_param("issssss", $citizen_id, $title, $category, $description, $location, $priority, $image_path);

    if ($stmt->execute()) {
        header('Location: ' . BASE_URL . '/citizen/complaints.php?success=1');
        exit();
    }
}

// Fetch user's complaints
$stmt = $conn->prepare("
    SELECT * FROM complaints 
    WHERE citizen_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $citizen_id);
$stmt->execute();
$complaints = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver  _id = $citizen_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complaints - Citizen Portal</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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

.sidebar a i { color: #00b4d8; min-width: 18px; }

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

/* main area shifted to the right of the sidebar */
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

.content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
}

@media (max-width: 992px) {
    .main-content { margin-left: 0; max-width: 100%; padding: 1rem; }
    .content { grid-template-columns: 1fr; }
    .sidebar { position: relative; width: 100%; flex-direction: row; gap: 0.5rem; padding: 0.75rem; overflow-x: auto; }
    .sidebar h2 { display: none; }
    .sidebar a { white-space: nowrap; }
}

.form-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(6px);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    height: fit-content;
}

.form-section h2 {
    color: #0d1b2a;
    margin-bottom: 20px;
    font-size: 1.25rem;
    font-weight: 700;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #0d1b2a;
}

.form-group input,
.form-group textarea,
.form-group select {
    width: 100%;
    padding: 12px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    background: #fff;
    color: #0d1b2a;
    transition: box-shadow 0.3s;
}

.form-group input:focus,
.form-group textarea:focus,
.form-group select:focus {
    outline: none;
    box-shadow: 0 0 0 2px #00b4d8;
}

.form-group textarea {
    resize: vertical;
    min-height: 100px;
}

.btn {
    display: inline-block;
    width: 100%;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1em;
    font-weight: 600;
    cursor: pointer;
    background: #0d1b2a;
    color: #fff;
    transition: all 0.3s;
    text-align: center;
}

.btn:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

.complaints-section {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(6px);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
}

.complaints-section h2 {
    color: #0d1b2a;
    margin-bottom: 20px;
    font-size: 1.25rem;
    font-weight: 700;
}

.complaint-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    margin-bottom: 18px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.04);
    transition: transform 0.3s, box-shadow 0.3s;
}

.complaint-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 180, 216, 0.12);
}

.complaint-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 8px;
}

.complaint-title {
    font-weight: 600;
    color: #0d1b2a;
    font-size: 1.05rem;
}

.complaint-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 0.9em;
    color: #666;
    margin-bottom: 12px;
}

.complaint-description {
    background: #f4f7fb;
    padding: 12px;
    border-radius: 10px;
    line-height: 1.6;
    color: #333;
    margin-bottom: 12px;
}

.complaint-image {
    max-width: 100%;
    border-radius: 8px;
    margin-top: 10px;
}

.badge {
    display: inline-block;
    padding: 6px 12px;
    border-radius: 10px;
    font-size: 0.8em;
    font-weight: 600;
}

/* small variations for status badges (fallbacks) */
.badge-pending { background: #fff3cd; color: #856404; }
.badge-in_progress { background: #cce5ff; color: #004085; }
.badge-resolved { background: #d4edda; color: #155724; }
.badge-rejected { background: #f8d7da; color: #721c24; }

.priority-high { color: #dc3545; font-weight: 600; }
.priority-medium { color: #ffc107; font-weight: 600; }
.priority-low { color: #28a745; font-weight: 600; }

.success-msg {
    background: #d4edda;
    color: #155724;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 16px;
    border: 1px solid #c3e6cb;
}

.empty-state {
    text-align: center;
    padding: 30px;
    color: #999;
    font-size: 1em;
}
    </style>
</head>
<body>
    <div class="sidebar">
        <h2>🏛️ SarkariSathi</h2>
        <a href="<?= BASE_URL ?>/citizen/dashboard.php" class="active">
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

    <div class="main-content">
        <div class="header">
            <h1>📋 Complaints</h1>
            <div style="color:#666;">Welcome, <?= htmlspecialchars($citizen_name) ?></div>
        </div>

        <div class="content">
            <div class="form-section">
                <h2>Submit New Complaint</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-msg">Complaint submitted successfully!</div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label>Title:</label>
                        <input type="text" name="title" required placeholder="Brief title of your complaint">
                    </div>

                    <div class="form-group">
                        <label>Category:</label>
                        <select name="category" required>
                            <option value="">Select Category</option>
                            <option value="infrastructure">Infrastructure</option>
                            <option value="sanitation">Sanitation</option>
                            <option value="electricity">Electricity</option>
                            <option value="water">Water Supply</option>
                            <option value="roads">Roads</option>
                            <option value="noise">Noise Pollution</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Priority:</label>
                        <select name="priority" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Location:</label>
                        <input type="text" name="location" required placeholder="Where is the issue?">
                    </div>

                    <div class="form-group">
                        <label>Description:</label>
                        <textarea name="description" required placeholder="Describe the issue in detail..."></textarea>
                    </div>

                    <div class="form-group">
                        <label>Upload Image (Optional):</label>
                        <input type="file" name="image" accept="image/*">
                    </div>

                    <button type="submit" name="submit_complaint" class="btn">Submit Complaint</button>
                </form>
            </div>

            <div class="complaints-section">
                <h2>My Complaints</h2>

                <?php if (empty($complaints)): ?>
                    <div class="empty-state">
                        <p>📭 No complaints submitted yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($complaints as $complaint): ?>
                        <div class="complaint-card">
                            <div class="complaint-header">
                                <div class="complaint-title"><?= htmlspecialchars($complaint['title']); ?></div>
                                <?php
                                    // Normalize status to be a valid class token
                                    $status_class = 'badge-' . str_replace(' ', '_', strtolower($complaint['status']));
                                ?>
                                <span class="badge <?= htmlspecialchars($status_class); ?>">
                                    <?= ucfirst(str_replace('_', ' ', $complaint['status'])); ?>
                                </span>
                            </div>

                            <div class="complaint-meta">
                                <span>🏷️ <?= ucfirst(htmlspecialchars($complaint['category'])); ?></span>
                                <span>📍 <?= htmlspecialchars($complaint['location']); ?></span>
                                <span class="priority-<?= htmlspecialchars($complaint['priority']); ?>">
                                    ⚠️ <?= ucfirst(htmlspecialchars($complaint['priority'])); ?> Priority
                                </span>
                                <span>📅 <?= date('M d, Y', strtotime($complaint['created_at'])); ?></span>
                            </div>

                            <div class="complaint-description">
                                <?= nl2br(htmlspecialchars($complaint['description'])); ?>
                            </div>

                            <?php if (!empty($complaint['image_path'])): ?>
                                <img src="<?= htmlspecialchars($complaint['image_path']); ?>"
                                     alt="Complaint Image" class="complaint-image">
                            <?php endif; ?>

                            <?php if (!empty($complaint['admin_response'])): ?>
                                <div style="margin-top: 12px; padding: 12px; background: #e8f4f8; border-left: 4px solid #667eea; border-radius: 5px;">
                                    <strong>📢 Admin Response:</strong>
                                    <p style="margin-top: 6px; color: #555;">
                                        <?= nl2br(htmlspecialchars($complaint['admin_response'])); ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>