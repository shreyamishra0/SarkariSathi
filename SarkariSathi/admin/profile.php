<?php
require_once __DIR__ . '/../includes/admin-check.php';

// Get current admin ID
$admin_id = $_SESSION['user_id'];

// Handle messages
$success = '';
$error = '';

// Fetch admin data
$stmt = $conn->prepare("SELECT name, email, phone, office_name FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param("i", $admin_id);
$stmt->execute();
$result = $stmt->get_result();
$admin = $result->fetch_assoc();
$stmt->close();

// Update profile info
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $office_name = trim($_POST['office_name']);

    $stmt = $conn->prepare("UPDATE users SET name=?, email=?, phone=?, office_name=? WHERE id=?");
    $stmt->bind_param("ssssi", $name, $email, $phone, $office_name, $admin_id);
    if ($stmt->execute()) {
        $success = "Profile updated successfully!";
        $_SESSION['name'] = $name;
    } else {
        $error = "Error updating profile.";
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $stmt->bind_result($password_hash);
    $stmt->fetch();
    $stmt->close();

    if (!password_verify($current_pass, $password_hash)) {
        $error = "Current password is incorrect.";
    } elseif ($new_pass !== $confirm_pass) {
        $error = "New passwords do not match.";
    } else {
        $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash=? WHERE id=?");
        $stmt->bind_param("si", $new_hash, $admin_id);
        if ($stmt->execute()) {
            $success = "Password changed successfully!";
        } else {
            $error = "Failed to change password.";
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Profile</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin:0; padding:0; font-family:'Inter', sans-serif; }
body { display:flex; min-height:100vh; background:#f4f7fb; }

/* --- SIDEBAR --- */
.sidebar { width:250px; background:#0d1b2a; display:flex; flex-direction:column; padding:2rem 1rem; gap:1rem; position:fixed; top:0; left:0; height:100vh; }
.sidebar h2 { color:#00b4d8; font-size:1.5rem; margin-bottom:2rem; text-align:center; }
.sidebar a { display:flex; align-items:center; gap:0.75rem; padding:0.75rem 1rem; border-radius:10px; color:#fff; text-decoration:none; transition:all 0.3s; }
.sidebar a i { width:20px; text-align:center; }
.sidebar a:hover, .sidebar a.active { background:#00b4d8; color:#0d1b2a; transform:translateX(5px); }

/* --- MAIN CONTENT --- */
.main-content { flex:1; margin-left:250px; padding:2rem; min-height:100vh; overflow-y:auto; }
.profile-card { background:#fff; padding:2rem; border-radius:15px; box-shadow:0 0 15px rgba(0,0,0,0.05); max-width:700px; margin:auto; }
h1.section-title { margin-bottom:1.5rem; color:#0d1b2a; text-align:center; border-bottom:3px solid #00b4d8; display:inline-block; padding-bottom:0.5rem; }

/* --- FORMS --- */
form { margin-bottom:2rem; }
input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:10px; margin-bottom:1rem; border:1px solid #ccc; border-radius:5px; }
button { padding:10px 20px; border:none; border-radius:6px; background:#00b4d8; color:white; cursor:pointer; font-weight:600; transition:all 0.3s; }
button:hover { background:#0d1b2a; transform:translateY(-2px); }

/* --- ALERT MESSAGES --- */
.message { padding:10px; border-radius:5px; margin-bottom:1rem; text-align:center; font-weight:600; }
.success { background:#d4edda; color:#155724; }
.error { background:#f8d7da; color:#721c24; }

/* --- RESPONSIVE --- */
@media (max-width:900px) {
    body { flex-direction:column; }
    .sidebar { width:100%; height:auto; flex-direction:row; padding:1rem; position:relative; }
    .sidebar h2 { margin-bottom:0; font-size:1.2rem; }
    .sidebar a { flex-direction:column; min-width:80px; font-size:0.85rem; padding:0.5rem; }
    .main-content { margin-left:0; padding:1rem; }
}
</style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🛡️ Admin Panel</h2>
    <a href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="<?= BASE_URL ?>/admin/verify-officers.php"><i class="fas fa-user-check"></i> Verify Officers</a>
    <a href="<?= BASE_URL ?>/admin/manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php"><i class="fas fa-exclamation-triangle"></i> Complaints</a> 
    <a href="<?= BASE_URL ?>/admin/profile.php" class="active"><i class="fas fa-user"></i> Profile</a>
    <a href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <div class="profile-card">
        <h1 class="section-title">Admin Profile</h1>

        <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <!-- Profile Update Form -->
        <form method="POST">
            <input type="hidden" name="update_profile" value="1">

            <label>Full Name:</label>
            <input type="text" name="name" value="<?= htmlspecialchars($admin['name']) ?>" required>

            <label>Email:</label>
            <input type="email" name="email" value="<?= htmlspecialchars($admin['email']) ?>" required>

            <label>Phone:</label>
            <input type="text" name="phone" value="<?= htmlspecialchars($admin['phone']) ?>">

            <label>Office Name:</label>
            <input type="text" name="office_name" value="<?= htmlspecialchars($admin['office_name']) ?>">

            <button type="submit">Update Profile</button>
        </form>

        <!-- Change Password Form -->
        <h3>Change Password</h3>
        <form method="POST">
            <input type="hidden" name="change_password" value="1">
            <label>Current Password:</label>
            <input type="password" name="current_password" required>
            <label>New Password:</label>
            <input type="password" name="new_password" required>
            <label>Confirm New Password:</label>
            <input type="password" name="confirm_password" required>
            <button type="submit">Change Password</button>
        </form>
    </div>
</div>

</body>
</html>
