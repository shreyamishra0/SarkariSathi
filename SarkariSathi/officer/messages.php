<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check officer login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

// Get conversations (citizens who messaged this officer)
$conversations_query = $conn->prepare("
    SELECT DISTINCT u.id as citizen_id, u.name as citizen_name, 
           MAX(m.sent_at) as last_message_time
    FROM messages m
    JOIN users u ON (m.sender_id = u.id OR m.receiver_id = u.id)
    WHERE (m.sender_id = ? OR m.receiver_id = ?)
      AND u.role = 'citizen'
      AND u.id != ?
    GROUP BY u.id, u.name
    ORDER BY last_message_time DESC
");
$conversations_query->bind_param("iii", $officer_id, $officer_id, $officer_id);
$conversations_query->execute();
$conversations_result = $conversations_query->get_result();

// Selected citizen chat
$selected_citizen = isset($_GET['citizen_id']) ? (int)$_GET['citizen_id'] : null;
$messages = [];

if ($selected_citizen) {
    $messages_query = $conn->prepare("
        SELECT m.*, s.name as sender_name
        FROM messages m
        JOIN users s ON m.sender_id = s.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?)
            OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.sent_at ASC
    ");
    $messages_query->bind_param("iiii", $officer_id, $selected_citizen, $selected_citizen, $officer_id);
    $messages_query->execute();
    $messages = $messages_query->get_result()->fetch_all(MYSQLI_ASSOC);
    $messages_query->close();

    // Mark messages as read
    $mark_read = $conn->prepare("UPDATE messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ?");
    $mark_read->bind_param("ii", $officer_id, $selected_citizen);
    $mark_read->execute();
    $mark_read->close();
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['message']) && $selected_citizen) {
    $message = trim($_POST['message']);
    $insert_stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message, sent_at)
        VALUES (?, ?, ?, NOW())
    ");
    $insert_stmt->bind_param("iis", $officer_id, $selected_citizen, $message);
    $insert_stmt->execute();
    $insert_stmt->close();
    header("Location: messages.php?citizen_id=" . $selected_citizen);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages - Officer Panel</title>
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
            padding: 0;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        /* Messages Container */
        .messages-container {
            display: flex;
            flex: 1;
            height: calc(100vh - 80px);
        }

        /* Conversations Sidebar */
        .conversations-sidebar {
            width: 350px;
            background: white;
            border-right: 1px solid #e0e0e0;
            display: flex;
            flex-direction: column;
        }

        .conversations-header {
            padding: 25px;
            border-bottom: 1px solid #e0e0e0;
            background: #f8f9fa;
        }

        .conversations-header h3 {
            color: #0d1b2a;
            font-size: 1.4rem;
            margin-bottom: 8px;
        }

        .conversations-header p {
            color: #666;
            font-size: 0.95rem;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
        }

        .conversation-item {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
        }

        .conversation-item:hover {
            background: #f8f9fa;
            border-color: #00b4d8;
        }

        .conversation-item.active {
            background: #e3f2fd;
            border-color: #00b4d8;
        }

        .conversation-avatar {
            width: 50px;
            height: 50px;
            background: #00b4d8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            margin-right: 15px;
            font-size: 1.1rem;
        }

        .conversation-info {
            flex: 1;
        }

        .conversation-name {
            font-weight: 600;
            color: #0d1b2a;
            margin-bottom: 4px;
        }

        .conversation-time {
            font-size: 0.8rem;
            color: #666;
        }

        /* Chat Area */
        .chat-area {
            flex: 1;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
        }

        .chat-header {
            padding: 20px 25px;
            background: white;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .chat-header-avatar {
            width: 45px;
            height: 45px;
            background: #00b4d8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }

        .chat-header-info h4 {
            color: #0d1b2a;
            margin-bottom: 2px;
        }

        .chat-header-info p {
            color: #666;
            font-size: 0.9rem;
        }

        .messages-area {
            flex: 1;
            padding: 25px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .no-conversation {
            text-align: center;
            color: #666;
            padding: 60px 25px;
        }

        .no-conversation i {
            font-size: 4rem;
            margin-bottom: 20px;
            color: #ccc;
        }

        .no-conversation h3 {
            margin-bottom: 10px;
            color: #0d1b2a;
        }

        .message {
            max-width: 70%;
            padding: 15px 20px;
            border-radius: 18px;
            position: relative;
            line-height: 1.4;
        }

        .message.sent {
            align-self: flex-end;
            background: #00b4d8;
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message.received {
            align-self: flex-start;
            background: white;
            color: #333;
            border: 1px solid #e0e0e0;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 0.75rem;
            margin-top: 5px;
            opacity: 0.8;
            text-align: right;
        }

        .message-input-area {
            padding: 20px 25px;
            background: white;
            border-top: 1px solid #e0e0e0;
        }

        .message-form {
            display: flex;
            gap: 12px;
            align-items: flex-end;
        }

        .message-input {
            flex: 1;
            padding: 12px 18px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            resize: none;
            font-size: 0.95rem;
            line-height: 1.4;
            max-height: 120px;
            outline: none;
            transition: border-color 0.3s;
        }

        .message-input:focus {
            border-color: #00b4d8;
        }

        .send-btn {
            background: #00b4d8;
            color: white;
            border: none;
            border-radius: 50%;
            width: 45px;
            height: 45px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .send-btn:hover {
            background: #0099c3;
            transform: scale(1.05);
        }

        .send-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        /* Footer */
        .footer {
            background: #0d1b2a;
            color: white;
            padding: 20px;
            text-align: center;
            margin-top: auto;
        }

        .footer p {
            margin: 0;
            font-size: 0.9rem;
            color: #e0e0e0;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏛️ SarkariSathi</h2>
            <p style="color:#e0e0e0;">Officer Portal</p>
            <div class="user-info">
                <div class="user-avatar"><?= strtoupper(substr($current_user, 0, 1)) ?></div>
                <div class="user-details">
                    <div class="user-name"><?= htmlspecialchars($current_user) ?></div>
                    <div class="user-role">Officer</div>
                </div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage-services.php"><i class="fas fa-cogs"></i> Manage Services</a></li>
            <li><a href="my-posts.php"><i class="fas fa-newspaper"></i> My Posts</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php" class="active"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="messages-container">
            <!-- Conversations -->
            <div class="conversations-sidebar">
                <div class="conversations-header">
                    <h3>💬 Citizen Messages</h3>
                    <p>Respond to citizen inquiries and provide support</p>
                </div>
                <div class="conversations-list">
                    <?php if ($conversations_result->num_rows > 0): ?>
                        <?php while ($conv = $conversations_result->fetch_assoc()): ?>
                            <div class="conversation-item <?= $selected_citizen == $conv['citizen_id'] ? 'active' : '' ?>"
                                onclick="window.location.href='messages.php?citizen_id=<?= $conv['citizen_id'] ?>'">
                                <div class="conversation-avatar"><?= strtoupper(substr($conv['citizen_name'], 0, 1)) ?></div>
                                <div class="conversation-info">
                                    <div class="conversation-name"><?= htmlspecialchars($conv['citizen_name']) ?></div>
                                    <div class="conversation-time">
                                        <?= date('M j, g:i A', strtotime($conv['last_message_time'])) ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:40px; color:#666;">
                            <i class="fas fa-comments" style="font-size:3rem; color:#ccc;"></i>
                            <p>No conversations yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Chat Area -->
            <div class="chat-area">
                <?php if ($selected_citizen): 
                    $citizen_info = $conn->query("SELECT name FROM users WHERE id = $selected_citizen")->fetch_assoc(); ?>
                    <div class="chat-header">
                        <div class="chat-header-avatar"><?= strtoupper(substr($citizen_info['name'], 0, 1)) ?></div>
                        <div class="chat-header-info">
                            <h4><?= htmlspecialchars($citizen_info['name']) ?></h4>
                            <p>Citizen</p>
                        </div>
                    </div>
                    <div class="messages-area" id="messagesArea">
                        <?php foreach ($messages as $msg): ?>
                            <div class="message <?= $msg['sender_id'] == $officer_id ? 'sent' : 'received' ?>">
                                <div class="message-text"><?= htmlspecialchars($msg['message']) ?></div>
                                <div class="message-time"><?= date('g:i A', strtotime($msg['sent_at'])) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="message-input-area">
                        <form method="POST" class="message-form">
                            <textarea name="message" class="message-input" placeholder="Type your message..." rows="1" required></textarea>
                            <button type="submit" class="send-btn"><i class="fas fa-paper-plane"></i></button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="no-conversation">
                        <i class="fas fa-comments"></i>
                        <h3>Select a conversation</h3>
                        <p>Choose a citizen from the list to view messages</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="footer">
            <p>&copy; <?= date('Y') ?> SarkariSathi. All rights reserved.</p>
        </div>
    </div>

    <script>
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) messagesArea.scrollTop = messagesArea.scrollHeight;

        const textarea = document.querySelector('.message-input');
        if (textarea) {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        }
    </script>
</body>
</html>
