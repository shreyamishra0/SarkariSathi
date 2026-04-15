<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in as citizen
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$citizen_name = $_SESSION['name'] ?? 'Citizen';

$officer_stmt = $conn->prepare("SELECT id, name FROM users WHERE role = 'officer' LIMIT 1");
$officer_stmt->execute();
$officer_result = $officer_stmt->get_result();
$officer_data = $officer_result->fetch_assoc();
$officer_id = $officer_data['id'] ?? 1;
$officer_name = $officer_data['name'] ?? 'Officer';
$officer_stmt->close();

// Fetch all messages between citizen and officer
$sql = "
    SELECT m.*, u.name as sender_name, u.role as sender_role
    FROM messages m
    LEFT JOIN users u ON m.sender_id = u.id
    WHERE (m.receiver_id = ? AND m.sender_id = ?)
       OR (m.receiver_id = ? AND m.sender_id = ?)
    ORDER BY m.sent_at ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $user_id, $officer_id, $officer_id, $user_id);
$stmt->execute();
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Mark received messages as read
$update_stmt = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ?");
$update_stmt->bind_param("i", $user_id);
$update_stmt->execute();
$update_stmt->close();

// Handle message sending
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message_text = trim($_POST['message']);
    if (!empty($message_text)) {
        $insert_stmt = $conn->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $insert_stmt->bind_param("iis", $user_id, $officer_id, $message_text);
        $insert_stmt->execute();
        $insert_stmt->close();
        header('Location: messages.php?success=1');
        exit();
    }
}

// Unread messages count for sidebar
$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count FROM messages WHERE receiver_id = $user_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Citizen Portal</title>
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
    padding: 1.5rem 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.header h1 {
    color: #0d1b2a;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.header p {
    color: #666;
    font-size: 1rem;
}

/* ===== CONTENT GRID ===== */
.content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
}

/* ===== COMPOSE SECTION ===== */
.compose-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    height: fit-content;
}

.compose-section h2 {
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

.form-group textarea {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
    background: #fff;
    color: #0d1b2a;
    transition: border 0.3s;
    resize: vertical;
    min-height: 120px;
    font-family: 'Inter', sans-serif;
}

.form-group textarea:focus {
    outline: none;
    border-color: #00b4d8;
}

.btn {
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
}

.btn:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

/* ===== MESSAGES SECTION ===== */
.messages-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.03);
    max-height: 700px;
    overflow-y: auto;
}

.messages-section h2 {
    color: #0d1b2a;
    margin-bottom: 20px;
    font-size: 1.25rem;
    font-weight: 700;
    position: sticky;
    top: 0;
    background: white;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
    z-index: 10;
}

.message-item {
    background: #f8f9fa;
    border-left: 4px solid #00b4d8;
    border-radius: 8px;
    padding: 18px;
    margin-bottom: 18px;
    transition: all 0.3s;
}

.message-item:hover {
    box-shadow: 0 3px 15px rgba(0, 180, 216, 0.12);
    transform: translateX(5px);
}

.message-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.message-subject {
    font-weight: 600;
    color: #0d1b2a;
    font-size: 1.05rem;
}

.badge-sent,
.badge-received {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 0.75em;
    font-weight: 600;
}

.badge-sent {
    background: #cce5ff;
    color: #004085;
}

.badge-received {
    background: #d4edda;
    color: #155724;
}

.message-meta {
    display: flex;
    gap: 15px;
    font-size: 0.85em;
    color: #666;
    margin-bottom: 12px;
}

.message-body {
    background: #ffffff;
    padding: 15px;
    border-radius: 8px;
    line-height: 1.6;
    color: #333;
    border: 1px solid #e0e0e0;
}

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
    padding: 60px 30px;
    color: #999;
}

.empty-state i {
    font-size: 4rem;
    color: #00b4d8;
    margin-bottom: 1rem;
    opacity: 0.5;
}

.empty-state p {
    font-size: 1.1rem;
    color: #666;
}

/* ===== SCROLLBAR STYLING ===== */
.messages-section::-webkit-scrollbar {
    width: 8px;
}

.messages-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 10px;
}

.messages-section::-webkit-scrollbar-thumb {
    background: #00b4d8;
    border-radius: 10px;
}

.messages-section::-webkit-scrollbar-thumb:hover {
    background: #0d1b2a;
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
    
    .content {
        grid-template-columns: 1fr;
    }
    
    .messages-section {
        max-height: 500px;
    }
}

@media (max-width: 768px) {
    .header h1 {
        font-size: 1.5rem;
    }
    
    .compose-section,
    .messages-section {
        padding: 20px;
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
        <a href="<?= BASE_URL ?>/citizen/messages.php" class="active">
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
        <div class="header">
            <h1>📬 Messages</h1>
            <p>Chat with Officer <?= htmlspecialchars($officer_name) ?></p>
        </div>

        <div class="content">
            <div class="compose-section">
                <h2>Send Message</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-msg">✅ Message sent successfully!</div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Message to <?= htmlspecialchars($officer_name) ?>:</label>
                        <textarea name="message" required placeholder="Type your message here..."></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>

            <div class="messages-section">
                <h2>Conversation History</h2>
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No messages yet. Start the conversation!</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <div class="message-subject">
                                    <?= $msg['sender_id'] == $user_id ? '👤 You' : '👔 ' . htmlspecialchars($msg['sender_name']) ?>
                                </div>
                                <span class="<?= $msg['sender_id'] == $user_id ? 'badge-sent' : 'badge-received' ?>">
                                    <?= $msg['sender_id'] == $user_id ? 'Sent' : 'Received' ?>
                                </span>
                            </div>
                            <div class="message-meta">
                                <span>📅 <?= date('M d, Y h:i A', strtotime($msg['sent_at'])) ?></span>
                            </div>
                            <div class="message-body">
                                <?= nl2br(htmlspecialchars($msg['message'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-scroll to bottom of messages on load
        window.addEventListener('load', function() {
            const messagesSection = document.querySelector('.messages-section');
            if (messagesSection) {
                messagesSection.scrollTop = messagesSection.scrollHeight;
            }
        });

        // Reload page every 30 seconds to get new messages (simple polling)
        setInterval(function() {
            if (!document.querySelector('textarea:focus')) {
                location.reload();
            }
        }, 30000);
    </script>
</body>
</html>