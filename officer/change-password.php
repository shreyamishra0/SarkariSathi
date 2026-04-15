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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">

</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

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