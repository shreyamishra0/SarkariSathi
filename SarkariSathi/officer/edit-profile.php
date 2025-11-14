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
    <title>Edit Profile - Officer Panel</title>
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

        /* Edit Profile Form */
        .profile-container {
            max-width: 700px;
            margin: 0 auto;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .profile-header {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 100%);
            padding: 30px;
            color: white;
            text-align: center;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            background: #00b4d8;
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: white;
            border: 4px solid white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .profile-header h2 {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }

        .profile-header p {
            color: #e0e0e0;
            font-size: 0.95rem;
        }

        .profile-body {
            padding: 40px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
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

        .form-group label .required {
            color: #dc3545;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #00b4d8;
            box-shadow: 0 0 0 3px rgba(0, 180, 216, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .form-help {
            margin-top: 5px;
            font-size: 0.85rem;
            color: #666;
        }

        .profile-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e0e0e0;
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

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.3);
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

        /* Read-only Fields */
        .read-only-field {
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            padding: 12px 15px;
            border-radius: 8px;
            color: #666;
            font-size: 0.95rem;
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
            
            .profile-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
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
            <li><a href="my-posts.php"><i class="fas fa-newspaper"></i> My Posts</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php"><i class="fas fa-comments"></i> Messages</a></li>
            <li><a href="complaints.php"><i class="fas fa-exclamation-circle"></i> Complaints</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="edit-profile.php" class="active"><i class="fas fa-edit"></i> Edit Profile</a></li>
            <li><a href="change-password.php"><i class="fas fa-key"></i> Change Password</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

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