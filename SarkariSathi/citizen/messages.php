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
        <link rel="stylesheet" href="../assets/css/officer.css">

    
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <h2>🏛️ SarkariSathi</h2>
        <a href="<?= BASE_URL ?>/citizen/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="<?= BASE_URL ?>/citizen/sections.php"><i class="fas fa-list"></i> Services</a>
        <a href="<?= BASE_URL ?>/citizen/queue-booking.php"><i class="fas fa-calendar-check"></i> Book Queue</a>
        <a href="<?= BASE_URL ?>/citizen/my-queue.php"><i class="fas fa-users"></i> My Queue</a>
        <a href="<?= BASE_URL ?>/citizen/track-status.php"><i class="fas fa-search"></i> Track Status</a>
        <a href="<?= BASE_URL ?>/citizen/complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a>
        <a href="<?= BASE_URL ?>/citizen/messages.php" class="active"><i class="fas fa-envelope"></i> Messages
            <?php if ($unread_messages > 0): ?><span class="badge"><?= $unread_messages ?></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/citizen/profile.php"><i class="fas fa-user"></i> Profile</a>
        <a href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>📬 Messages</h1>
            <p>Chat with Officer <?= htmlspecialchars($officer_name) ?></p>
        </div>

        <div class="content">
            <div class="compose-section">
                <h2>Send Message to <?= htmlspecialchars($officer_name) ?></h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-msg">✅ Message sent successfully!</div>
                <?php endif; ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Message:</label>
                        <textarea name="message" required placeholder="Type your message..."></textarea>
                    </div>
                    <button type="submit" name="send_message" class="btn">Send Message</button>
                </form>
            </div>

            <div class="messages-section">
                <h2>Conversation</h2>
                <?php if (empty($messages)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No messages yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($messages as $msg): ?>
                        <div class="message-item">
                            <div class="message-header">
                                <div class="message-subject">
                                    <?= $msg['sender_id'] == $user_id ? 'You' : htmlspecialchars($msg['sender_name']) ?>
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
</body>
</html>
