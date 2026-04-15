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

$success = '';
$error = '';

// Get current officer data
$officer_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$officer_stmt->bind_param("i", $officer_id);
$officer_stmt->execute();
$officer_result = $officer_stmt->get_result();
$officer = $officer_result->fetch_assoc();

if (!$officer) {
    header("Location: profile.php?error=User not found");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $office_name = trim($_POST['office_name']);
    
    // Validate inputs
    if (empty($name) || empty($email)) {
        $error = "Name and email are required fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } elseif (!empty($phone) && !preg_match('/^98[0-9]{8}$/', $phone)) {
        $error = "Phone number must be a valid Nepali number (98XXXXXXXX).";
    } else {
        // Check if email already exists (excluding current user)
        $email_check = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $email_check->bind_param("si", $email, $officer_id);
        $email_check->execute();
        $email_result = $email_check->get_result();
        
        if ($email_result->num_rows > 0) {
            $error = "Email address is already registered by another user.";
        } else {
            // Update profile
            $update_stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, phone = ?, office_name = ? WHERE id = ?");
            $update_stmt->bind_param("ssssi", $name, $email, $phone, $office_name, $officer_id);
            
            if ($update_stmt->execute()) {
                // Update session variables
                $_SESSION['name'] = $name;
                $_SESSION['email'] = $email;
                
                $success = "Profile updated successfully!";
                
                // Refresh officer data
                $officer_stmt->execute();
                $officer_result = $officer_stmt->get_result();
                $officer = $officer_result->fetch_assoc();
            } else {
                $error = "Failed to update profile: " . $conn->error;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="profile-container">
            <div class="page-header">
                <h1 class="page-title">
                    <i class="fas fa-user-edit"></i>
                    Edit Profile
                </h1>
                <p class="page-subtitle">Update your personal and professional information</p>
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

            <div class="profile-card">
                <div class="profile-header">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($officer['name'], 0, 1)); ?>
                    </div>
                    <h2>Update Your Profile</h2>
                    <p>Keep your information current and accurate</p>
                </div>

                <div class="profile-body">
                    <form method="POST" id="profileForm">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="name">
                                    <i class="fas fa-user"></i>
                                    Full Name <span class="required">*</span>
                                </label>
                                <input type="text" 
                                       id="name" 
                                       name="name" 
                                       value="<?php echo htmlspecialchars($officer['name']); ?>"
                                       placeholder="Enter your full name"
                                       required>
                            </div>

                            <div class="form-group">
                                <label for="email">
                                    <i class="fas fa-envelope"></i>
                                    Email Address <span class="required">*</span>
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       value="<?php echo htmlspecialchars($officer['email']); ?>"
                                       placeholder="your@email.com"
                                       required>
                                <div class="form-help">This will be used for login and notifications</div>
                            </div>

                            <div class="form-group">
                                <label for="phone">
                                    <i class="fas fa-phone"></i>
                                    Phone Number
                                </label>
                                <input type="tel" 
                                       id="phone" 
                                       name="phone" 
                                       value="<?php echo htmlspecialchars($officer['phone']); ?>"
                                       placeholder="98XXXXXXXX"
                                       pattern="98[0-9]{8}">
                                <div class="form-help">10-digit Nepali number starting with 98</div>
                            </div>

                            <div class="form-group">
                                <label for="office_name">
                                    <i class="fas fa-building"></i>
                                    Office Name
                                </label>
                                <input type="text" 
                                       id="office_name" 
                                       name="office_name" 
                                       value="<?php echo htmlspecialchars($officer['office_name'] ?? ''); ?>"
                                       placeholder="e.g., District Administration Office, Kathmandu">
                            </div>

                            <div class="form-group full-width">
                                <label>
                                    <i class="fas fa-shield-alt"></i>
                                    Account Information
                                </label>
                                <div class="read-only-field">
                                    <strong>Role:</strong> <?php echo ucfirst($officer['role']); ?> | 
                                    <strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($officer['created_at'])); ?> | 
                                    <strong>Status:</strong> 
                                    <span style="color: <?php echo $officer['is_verified'] ? '#28a745' : '#dc3545'; ?>;">
                                        <?php echo $officer['is_verified'] ? 'Verified' : 'Pending Verification'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="profile-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update Profile
                            </button>
                            <a href="profile.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                            <a href="change-password.php" class="btn btn-danger">
                                <i class="fas fa-key"></i>
                                Change Password
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Additional Information -->
            <div style="margin-top: 30px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <h3 style="color: #0d1b2a; margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-info-circle"></i>
                    Profile Update Guidelines
                </h3>
                <ul style="color: #666; line-height: 1.6; padding-left: 20px;">
                    <li>Your email address will be used for all system communications and login</li>
                    <li>Ensure your phone number is correct for important notifications</li>
                    <li>Office name helps citizens identify your department</li>
                    <li>Profile picture can be updated in the next version</li>
                    <li>Contact admin if you need to change your role or verification status</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const phone = document.getElementById('phone').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('Please enter your full name.');
                document.getElementById('name').focus();
                return false;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address.');
                document.getElementById('email').focus();
                return false;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                document.getElementById('email').focus();
                return false;
            }
            
            // Phone validation (if provided)
            if (phone && !/^98[0-9]{8}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid Nepali phone number (98XXXXXXXX).');
                document.getElementById('phone').focus();
                return false;
            }
        });

        // Real-time phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.startsWith('98')) {
                value = value.substring(0, 10);
            } else if (value.length > 0) {
                value = '98' + value;
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });

        // Character counter for office name
        const officeInput = document.getElementById('office_name');
        officeInput.addEventListener('input', function() {
            const maxLength = 100;
            const currentLength = this.value.length;
            let helpText = this.parentElement.querySelector('.form-help');
            
            if (!helpText) {
                helpText = document.createElement('div');
                helpText.className = 'form-help';
                this.parentElement.appendChild(helpText);
            }
            
            if (currentLength > maxLength * 0.8) {
                helpText.innerHTML = `${currentLength}/${maxLength} characters`;
                helpText.style.color = currentLength > maxLength ? '#dc3545' : '#fd7e14';
            } else {
                helpText.innerHTML = '';
            }
            
            if (currentLength > maxLength) {
                this.value = this.value.substring(0, maxLength);
            }
        });
    </script>
</body>
</html>