<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/functions.php';
 
$officer_id = $_SESSION['user_id'];
$page_title = "Messages";
include 'includes/header.php';
 
// Get conversations
$stmt = $conn->prepare("
    SELECT DISTINCT 
        u.id as citizen_id,
        u.name as citizen_name,
        u.phone,
        s.name as section_name,
        (SELECT message FROM messages 
         WHERE (sender_id = ? AND receiver_id = u.id) 
         OR (sender_id = u.id AND receiver_id = ?) 
         ORDER BY sent_at DESC LIMIT 1) as last_message,
        (SELECT COUNT(*) FROM messages 
         WHERE receiver_id = ? AND sender_id = u.id AND is_read = 0) as unread_count
    FROM users u
    LEFT JOIN messages m ON (m.sender_id = u.id OR m.receiver_id = u.id)
    LEFT JOIN sections s ON m.section_id = s.id
    WHERE u.role = 'citizen' 
    AND (m.sender_id = ? OR m.receiver_id = ?)
    GROUP BY u.id
    ORDER BY (SELECT sent_at FROM messages 
              WHERE (sender_id = ? AND receiver_id = u.id) 
              OR (sender_id = u.id AND receiver_id = ?) 
              ORDER BY sent_at DESC LIMIT 1) DESC
");
$stmt->bind_param("iiiiiii", $officer_id, $officer_id, $officer_id, $officer_id, $officer_id, $officer_id, $officer_id);
$stmt->execute();
$conversations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
 
// Get messages for selected conversation
$selected_citizen = $_GET['citizen_id'] ?? null;
$messages = [];
if ($selected_citizen) {
    $stmt = $conn->prepare("
        SELECT m.*, u.name as sender_name, u.role as sender_role
        FROM messages m
        JOIN users u ON m.sender_id = u.id
        WHERE ((m.sender_id = ? AND m.receiver_id = ?) 
               OR (m.sender_id = ? AND m.receiver_id = ?))
        ORDER BY m.sent_at ASC
    ");
    $stmt->bind_param("iiii", $officer_id, $selected_citizen, $selected_citizen, $officer_id);
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages SET is_read = 1 
        WHERE receiver_id = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->bind_param("ii", $officer_id, $selected_citizen);
    $stmt->execute();
}
?>
 
<div class="dashboard-container">
    <div class="page-header">
        <h1>💬 Citizen Messages</h1>
        <p>Respond to citizen inquiries and provide support</p>
    </div>
 
    <div class="messaging-container">
        <div class="conversations-sidebar">
            <h3>Citizen Conversations</h3>
            <div class="conversations-list">
                <?php if (empty($conversations)): ?>
                    <div class="empty-state">
                        <p>No conversations yet</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($conversations as $conv): ?>
                        <a href=''citizen_id'] ?>" 
                           class="conversation-item <?= $selected_citizen == $conv['citizen_id'] ? 'active' : '' ?>">
                            <div class="conversation-header">
                                <h4><?= htmlspecialchars($conv['citizen_name']) ?></h4>
                                <?php if ($conv['unread_count'] > 0): ?>
                                    <span class="unread-badge"><?= $conv['unread_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="phone">📱 <?= htmlspecialchars($conv['phone']) ?></p>
                            <p class="last-message"><?= htmlspecialchars($conv['last_message'] ?? 'No messages yet') ?></p>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
 
        <div class="chat-area">
            <?php if ($selected_citizen): ?>
                <?php 
                $citizen_stmt = $conn->prepare("SELECT name, phone FROM users WHERE id = ?");
                $citizen_stmt->bind_param("i", $selected_citizen);
                $citizen_stmt->execute();
                $citizen = $citizen_stmt->get_result()->fetch_assoc();
                ?>
                
                <div class="chat-header">
                    <h3><?= htmlspecialchars($citizen['name']) ?></h3>
                    <p>📱 <?= htmlspecialchars($citizen['phone']) ?></p>
                </div>
 
                <div class="messages-container" id="messagesContainer">
                    <?php foreach ($messages as $msg): ?>
                        <div class="message <?= $msg['sender_id'] == $officer_id ? 'sent' : 'received' ?>">
                            <div class="message-content">
                                <p><?= htmlspecialchars($msg['message']) ?></p>
                                <span class="message-time">
                                    <?= date('h:i A', strtotime($msg['sent_at'])) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
 
                <div class="message-input">
                    <form id="sendMessageForm" class="message-form">
                        <input type="hidden" name="receiver_id" value="<?= $selected_citizen ?>">
                        <div class="input-group">
                            <input type="text" name="message" placeholder="Type your response..." required>
                            <button type="submit" class="btn btn-primary">Send</button>
                        </div>
                    </form>
                </div>
            <?php else: ?>
                <div class="no-conversation">
                    <div class="empty-chat">
                        <h3>Select a conversation</h3>
                        <p>Choose a citizen from the list to view messages</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
 
<script>
// Same JavaScript as citizen version
document.getElementById('sendMessageForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const message = formData.get('message');
    
    if (!message.trim()) return;
    
    try {
        const response = await fetch('/api/send-message.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            this.reset();
            location.reload();
        } else {
            alert('Failed to send message: ' + data.message);
        }
    } catch (error) {
        console.error('Error sending message:', error);
        alert('Failed to send message');
    }
});
 
const messagesContainer = document.getElementById('messagesContainer');
if (messagesContainer) {
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}
</script>
 
<?php include 'includes/footer.php'; ?>
 


