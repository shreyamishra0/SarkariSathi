<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header('Location: ' . BASE_URL . '/auth/login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    $update_stmt = $conn->prepare("
        UPDATE users 
        SET name = ?, email = ?, phone = ?
        WHERE id = ?
    ");
    $update_stmt->bind_param("sssi", $name, $email, $phone, $user_id);
    
    if ($update_stmt->execute()) {
        $_SESSION['name'] = $name;
        $_SESSION['email'] = $email;
        $_SESSION['phone'] = $phone;
        header('Location: profile.php?success=profile_updated');
        exit();
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    if (password_verify($current_password, $user['password_hash'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $pwd_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $pwd_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($pwd_stmt->execute()) {
                    header('Location: profile.php?success=password_changed');
                    exit();
                }
            } else {
                $error = "Password must be at least 6 characters long";
            }
        } else {
            $error = "New passwords do not match";
        }
    } else {
        $error = "Current password is incorrect";
    }
}

// Get user statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM complaints WHERE citizen_id = ?) as total_complaints,
        (SELECT COUNT(*) FROM complaints WHERE citizen_id = ? AND status = 'resolved') as resolved_complaints,
        (SELECT COUNT(*) FROM applications WHERE citizen_id = ?) as total_applications,
        (SELECT COUNT(*) FROM messages WHERE sender_id = ? OR receiver_id = ?) as total_messages
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

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
    <title>Profile - Citizen Portal</title>
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
    background: white;
    padding: 1.5rem;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.header h1 {
    color: #0d1b2a;
    font-size: 1.75rem;
}

.content {
    display: grid;
    grid-template-columns: 1fr 2fr;
    gap: 20px;
}

/* ===== PROFILE CARD ===== */
.profile-card {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
    text-align: center;
    height: fit-content;
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: linear-gradient(135deg, #00b4d8, #0d1b2a);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
    color: white;
    margin: 0 auto 20px;
}

.profile-name {
    font-size: 1.5rem;
    font-weight: 600;
    color: #0d1b2a;
    margin-bottom: 5px;
}

.profile-email {
    color: #666;
    margin-bottom: 20px;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-top: 20px;
}

.stat-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    text-align: center;
}

.stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #00b4d8;
}

.stat-label {
    font-size: 0.875rem;
    color: #666;
    margin-top: 5px;
}

/* ===== FORMS SECTION ===== */
.forms-section {
    background: white;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.03);
}

.section-title {
    color: #0d1b2a;
    font-size: 1.25rem;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #0d1b2a;
}

.form-group input {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1em;
    transition: border-color 0.3s;
}

.form-group input:focus {
    outline: none;
    border-color: #00b4d8;
}

.btn {
    background: #0d1b2a;
    color: white;
    padding: 12px 30px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 1em;
    font-weight: 600;
    transition: all 0.3s;
}

.btn:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

.success-msg {
    background: #d4edda;
    color: #155724;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #c3e6cb;
}

.error-msg {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    border: 1px solid #f5c6cb;
}

.divider {
    height: 2px;
    background: #f0f0f0;
    margin: 30px 0;
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
        <a href="<?= BASE_URL ?>/citizen/messages.php">
            <i class="fas fa-envelope"></i> Messages
            <?php if ($unread_messages > 0): ?>
                <span class="badge"><?= $unread_messages ?></span>
            <?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/citizen/profile.php" class="active">
            <i class="fas fa-user"></i> Profile
        </a>
        <a href="<?= BASE_URL ?>/auth/logout.php">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h1>👤 My Profile</h1>
        </div>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="success-msg">
                <?php 
                if ($_GET['success'] == 'profile_updated') {
                    echo '✅ Profile updated successfully!';
                } elseif ($_GET['success'] == 'password_changed') {
                    echo '✅ Password changed successfully!';
                }
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="error-msg">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="content">
            <div class="profile-card">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                <div class="profile-email"><?php echo htmlspecialchars($user['email'] ?? $user['phone']); ?></div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_complaints']; ?></div>
                        <div class="stat-label">Complaints</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['resolved_complaints']; ?></div>
                        <div class="stat-label">Resolved</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_applications']; ?></div>
                        <div class="stat-label">Applications</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_messages']; ?></div>
                        <div class="stat-label">Messages</div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                    <div style="color: #666; font-size: 13px;">
                        <strong>Member Since:</strong><br>
                        <?php echo date('F Y', strtotime($user['created_at'])); ?>
                    </div>
                </div>
            </div>
            
            <div class="forms-section">
                <h2 class="section-title">Profile Information</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name:</label>
                        <input type="text" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Email Address:</label>
                        <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="your@email.com">
                    </div>
                    
                    <div class="form-group">
                        <label>Phone Number:</label>
                        <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" required>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn">Update Profile</button>
                </form>
                
                <div class="divider"></div>
                
                <h2 class="section-title">Change Password</h2>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password:</label>
                        <input type="password" name="current_password" required>
                    </div>
                    
                    <div class="form-group">
                        <label>New Password:</label>
                        <input type="password" name="new_password" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password:</label>
                        <input type="password" name="confirm_password" required minlength="6">
                    </div>
                    
                    <button type="submit" name="change_password" class="btn">Change Password</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>