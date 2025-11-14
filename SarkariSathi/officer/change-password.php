<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

// Handle password change
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate inputs
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "New password must be at least 6 characters long.";
    } else {
        // Get current password hash
        $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param("i", $officer_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user && password_verify($current_password, $user['password_hash'])) {
            // Current password is correct, update to new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $update_stmt->bind_param("si", $new_password_hash, $officer_id);
            
            if ($update_stmt->execute()) {
                $success = "Password changed successfully!";
                // Clear form fields
                $_POST = array();
            } else {
                $error = "Failed to update password. Please try again.";
            }
        } else {
            $error = "Current password is incorrect.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Change Password - Officer Panel</title>
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
            color: white;
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
            padding: 30px;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-title {
            color: #0d1b2a;
            font-size: 2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .page-subtitle {
            color: #666;
            font-size: 1.1rem;
        }

        /* Password Change Form */
        .password-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .password-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .password-header {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 100%);
            padding: 30px;
            color: white;
        }

        .password-header h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .password-header p {
            color: #e0e0e0;
            font-size: 0.95rem;
        }

        .password-body {
            padding: 40px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0d1b2a;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
        }

        .password-strength {
            margin-top: 8px;
            font-size: 0.85rem;
        }

        .strength-weak { color: #dc3545; }
        .strength-medium { color: #fd7e14; }
        .strength-strong { color: #28a745; }

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 20px;
            border-left: 4px solid #00b4d8;
        }

        .password-requirements h4 {
            color: #0d1b2a;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
        }

        .password-requirements li {
            margin-bottom: 5px;
            font-size: 0.85rem;
            color: #666;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .password-requirements li.valid {
            color: #28a745;
        }

        .password-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #00b4d8;
            color: white;
        }

        .btn-primary:hover {
            background: #0099c3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.3);
        }

        /* Messages */
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .password-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏛️ SarkariSathi</h2>
            <p style="color: #e0e0e0; font-size: 0.9rem;">Officer Portal</p>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($current_user, 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($current_user); ?></div>
                    <div class="user-role">Officer</div>
                </div>
            </div>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="manage-services.php"><i class="fas fa-cogs"></i> Manage Services</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="change-password.php" class="active"><i class="fas fa-key"></i> Change Password</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="password-container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-key"></i>
                    Change Password
                </h1>
                <p class="page-subtitle">Update your account password securely</p>
            </div>

            <?php if ($success): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <div class="password-card">
                <div class="password-header">
                    <h2>
                        <i class="fas fa-lock"></i>
                        Security Settings
                    </h2>
                    <p>Keep your account secure with a strong password</p>
                </div>

                <div class="password-body">
                    <form method="POST" id="passwordForm">
                        <div class="form-group">
                            <label for="current_password">
                                <i class="fas fa-lock"></i>
                                Current Password
                            </label>
                            <input type="password" 
                                   id="current_password" 
                                   name="current_password" 
                                   placeholder="Enter your current password"
                                   required
                                   autocomplete="current-password">
                        </div>

                        <div class="form-group">
                            <label for="new_password">
                                <i class="fas fa-key"></i>
                                New Password
                            </label>
                            <input type="password" 
                                   id="new_password" 
                                   name="new_password" 
                                   placeholder="Enter your new password"
                                   required
                                   minlength="6"
                                   autocomplete="new-password">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">
                                <i class="fas fa-check-double"></i>
                                Confirm New Password
                            </label>
                            <input type="password" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   placeholder="Confirm your new password"
                                   required
                                   minlength="6"
                                   autocomplete="new-password">
                            <div class="password-strength" id="passwordMatch"></div>
                        </div>

                        <div class="password-requirements">
                            <h4>Password Requirements:</h4>
                            <ul>
                                <li id="reqLength">At least 6 characters long</li>
                                <li id="reqMatch">Passwords must match</li>
                            </ul>
                        </div>

                        <div class="password-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update Password
                            </button>
                            <a href="profile.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i>
                                Back to Profile
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password strength checker
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordStrength = document.getElementById('passwordStrength');
        const passwordMatch = document.getElementById('passwordMatch');
        const reqLength = document.getElementById('reqLength');
        const reqMatch = document.getElementById('reqMatch');

        newPassword.addEventListener('input', function() {
            const password = this.value;
            
            // Check length
            if (password.length >= 6) {
                reqLength.classList.add('valid');
                reqLength.innerHTML = '<i class="fas fa-check"></i> At least 6 characters long';
            } else {
                reqLength.classList.remove('valid');
                reqLength.innerHTML = 'At least 6 characters long';
            }

            // Check strength
            let strength = 'Weak';
            let strengthClass = 'strength-weak';
            
            if (password.length >= 8) {
                strength = 'Medium';
                strengthClass = 'strength-medium';
            }
            if (password.length >= 10 && /[A-Z]/.test(password) && /[0-9]/.test(password)) {
                strength = 'Strong';
                strengthClass = 'strength-strong';
            }
            
            if (password.length > 0) {
                passwordStrength.innerHTML = `Strength: <span class="${strengthClass}">${strength}</span>`;
            } else {
                passwordStrength.innerHTML = '';
            }

            // Check match
            checkPasswordMatch();
        });

        confirmPassword.addEventListener('input', checkPasswordMatch);

        function checkPasswordMatch() {
            const newPass = newPassword.value;
            const confirmPass = confirmPassword.value;
            
            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    passwordMatch.innerHTML = '<span class="strength-strong">Passwords match</span>';
                    reqMatch.classList.add('valid');
                    reqMatch.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                } else {
                    passwordMatch.innerHTML = '<span class="strength-weak">Passwords do not match</span>';
                    reqMatch.classList.remove('valid');
                    reqMatch.innerHTML = 'Passwords must match';
                }
            } else {
                passwordMatch.innerHTML = '';
                reqMatch.classList.remove('valid');
                reqMatch.innerHTML = 'Passwords must match';
            }
        }

        // Form submission validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPass = newPassword.value;
            const confirmPass = confirmPassword.value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match. Please check your entries.');
                return false;
            }
            
            if (newPass.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long.');
                return false;
            }
        });

        function togglePasswordVisibility(inputId) {
            const input = document.getElementById(inputId);
            const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', type);
        }
    </script>
</body>
</html>