<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/constants.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];
$current_user = $_SESSION['name'];

$success = '';
$error = '';
$generated_tracking = '';

// Get officer's services
$services_query = $conn->query("
    SELECT id, name, estimated_days, fee_amount 
    FROM sections 
    WHERE officer_id = $officer_id AND is_active = 1
    ORDER BY name ASC
");
$services = $services_query ? $services_query->fetch_all(MYSQLI_ASSOC) : [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_application'])) {
    $citizen_phone = trim($_POST['citizen_phone']);
    $citizen_name = trim($_POST['citizen_name']);
    $section_id = (int)$_POST['section_id'];
    $notes = trim($_POST['notes'] ?? '');
    
    // Validate inputs
    if (empty($citizen_phone) || empty($section_id)) {
        $error = "Phone number and service are required.";
    } else {
        // Check if citizen exists by phone
        $citizen_stmt = $conn->prepare("SELECT id, name FROM users WHERE phone = ? AND role = 'citizen'");
        $citizen_stmt->bind_param("s", $citizen_phone);
        $citizen_stmt->execute();
        $citizen_result = $citizen_stmt->get_result();
        
        $citizen_id = null;
        
        if ($citizen_result->num_rows > 0) {
            // Citizen exists - use existing account
            $citizen = $citizen_result->fetch_assoc();
            $citizen_id = $citizen['id'];
        } else {
            // Citizen doesn't exist - create new account
            if (empty($citizen_name)) {
                $error = "Citizen name is required for new registration.";
            } else {
                // Generate temporary email and password
                $temp_email = "citizen_" . $citizen_phone . "@sarkarisathi.temp";
                $temp_password = substr(str_shuffle('0123456789'), 0, 6); // 6-digit PIN
                $password_hash = password_hash($temp_password, PASSWORD_DEFAULT);
                
                $create_citizen = $conn->prepare("
                    INSERT INTO users (name, phone, email, password_hash, role, is_verified, created_at) 
                    VALUES (?, ?, ?, ?, 'citizen', 1, NOW())
                ");
                $create_citizen->bind_param("ssss", $citizen_name, $citizen_phone, $temp_email, $password_hash);
                
                if ($create_citizen->execute()) {
                    $citizen_id = $conn->insert_id;
                    
                    // Store temp password in session to show to officer
                    $_SESSION['new_citizen_password'] = $temp_password;
                    $_SESSION['new_citizen_phone'] = $citizen_phone;
                } else {
                    $error = "Failed to create citizen account: " . $conn->error;
                }
            }
        }
        
        if ($citizen_id && empty($error)) {
            // Verify this section belongs to current officer
            $verify_stmt = $conn->prepare("SELECT name FROM sections WHERE id = ? AND officer_id = ?");
            $verify_stmt->bind_param("ii", $section_id, $officer_id);
            $verify_stmt->execute();
            $section_result = $verify_stmt->get_result();
            
            if ($section_result->num_rows === 0) {
                $error = "Invalid service selected.";
            } else {
                $section = $section_result->fetch_assoc();
                
                // Create application using function from functions.php
                $result = createApplication($citizen_id, $section_id, [
                    'notes' => $notes,
                    'created_by_officer' => true
                ]);
                
                if ($result['success']) {
                    $generated_tracking = $result['tracking_number'];
                    $success = "Application created successfully! Tracking Number: " . $generated_tracking;
                    
                    // Log in status_history (if table exists)
                    $app_id = $result['application_id'];
                    try {
                        $log_stmt = $conn->prepare("
                            INSERT INTO status_history (application_id, status, changed_by, notes) 
                            VALUES (?, 'submitted', ?, ?)
                        ");
                        if ($log_stmt) {
                            $log_stmt->bind_param("iis", $app_id, $officer_id, $notes);
                            $log_stmt->execute();
                        }
                    } catch (Exception $e) {
                        // Table doesn't exist, skip logging (non-critical)
                    }
                    
                    // Send notification to citizen
                    sendNotification(
                        $citizen_id,
                        'application_created',
                        'New Application Created',
                        "Your application for {$section['name']} has been created. Tracking Number: {$generated_tracking}",
                        "/citizen/track-status.php"
                    );
                    
                    // Clear form
                    $_POST = [];
                } else {
                    $error = $result['message'] ?? "Failed to create application.";
                }
            }
        }
    }
}

// Get recent applications created by this officer
$recent_apps = $conn->query("
    SELECT a.tracking_number, a.status, a.submitted_date,
           s.name as service_name,
           u.name as citizen_name, u.phone as citizen_phone
    FROM applications a
    JOIN sections s ON a.section_id = s.id
    JOIN users u ON a.citizen_id = u.id
    WHERE s.officer_id = $officer_id
    ORDER BY a.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Application - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officerMain.css">
    <style>
        .tracking-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 12px;
            margin: 2rem 0;
            text-align: center;
        }
        .tracking-number-big {
            font-size: 2.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            letter-spacing: 2px;
        }
        .copy-button {
            background: white;
            color: #667eea;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 1rem;
        }
        .copy-button:hover {
            background: #f0f0f0;
        }
        .new-citizen-alert {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        .password-display {
            font-size: 1.5rem;
            font-weight: 700;
            font-family: 'Courier New', monospace;
            color: #d32f2f;
            padding: 10px;
            background: #ffebee;
            border-radius: 6px;
            display: inline-block;
            margin: 0.5rem 0;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-plus-circle"></i>
                Create New Application
            </h1>
            <p class="page-subtitle">Generate tracking number and create application for citizens</p>
        </div>

        <?php if ($success && $generated_tracking): ?>
            <div class="tracking-display">
                <h2 style="margin: 0 0 1rem 0;">
                    <i class="fas fa-check-circle"></i>
                    Application Created Successfully!
                </h2>
                <p style="margin: 0; opacity: 0.9;">Tracking Number</p>
                <div class="tracking-number-big" id="trackingNumber"><?= htmlspecialchars($generated_tracking) ?></div>
                <button class="copy-button" onclick="copyTracking()">
                    <i class="fas fa-copy"></i> Copy Tracking Number
                </button>
                <button class="copy-button" onclick="window.print()" style="margin-left: 10px;">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>

            <?php if (isset($_SESSION['new_citizen_password'])): ?>
                <div class="new-citizen-alert">
                    <h3 style="color: #856404; margin: 0 0 1rem 0;">
                        <i class="fas fa-user-plus"></i>
                        New Citizen Account Created
                    </h3>
                    <p style="margin: 0 0 1rem 0; color: #856404;">
                        <strong>IMPORTANT:</strong> A new citizen account has been created. Please provide these credentials to the citizen:
                    </p>
                    <div style="background: white; padding: 1rem; border-radius: 8px;">
                        <p style="margin: 0.5rem 0;"><strong>Phone Number:</strong> <?= htmlspecialchars($_SESSION['new_citizen_phone']) ?></p>
                        <p style="margin: 0.5rem 0;"><strong>Temporary Password:</strong></p>
                        <div class="password-display"><?= htmlspecialchars($_SESSION['new_citizen_password']) ?></div>
                        <p style="margin: 1rem 0 0 0; color: #666; font-size: 0.9rem;">
                            <i class="fas fa-info-circle"></i>
                            The citizen should change this password after first login at: <?= BASE_URL ?>/auth/login.php
                        </p>
                    </div>
                </div>
                <?php 
                unset($_SESSION['new_citizen_password']);
                unset($_SESSION['new_citizen_phone']);
                ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <!-- Application Creation Form -->
        <div class="form-card">
            <h3>
                <i class="fas fa-file-alt"></i>
                Application Details
            </h3>
            <form method="POST" id="applicationForm">
                <input type="hidden" name="create_application" value="1">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="citizen_phone">
                            <i class="fas fa-phone"></i>
                            Citizen Phone Number *
                        </label>
                        <input type="text" 
                               id="citizen_phone" 
                               name="citizen_phone" 
                               value="<?= htmlspecialchars($_POST['citizen_phone'] ?? '') ?>"
                               placeholder="98XXXXXXXX"
                               pattern="98[0-9]{8}"
                               required
                               onblur="checkCitizen()">
                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                            Enter 10-digit phone number. System will check if citizen exists.
                        </small>
                        <div id="citizenStatus" style="margin-top: 0.5rem;"></div>
                    </div>

                    <div class="form-group" id="nameGroup">
                        <label for="citizen_name">
                            <i class="fas fa-user"></i>
                            Citizen Name <span id="nameRequired">*</span>
                        </label>
                        <input type="text" 
                               id="citizen_name" 
                               name="citizen_name" 
                               value="<?= htmlspecialchars($_POST['citizen_name'] ?? '') ?>"
                               placeholder="Full Name">
                        <small style="color: #666; display: block; margin-top: 0.5rem;">
                            <span id="nameHelp">Required only for new citizens</span>
                        </small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="section_id">
                        <i class="fas fa-cogs"></i>
                        Select Service *
                    </label>
                    <select id="section_id" name="section_id" required onchange="updateServiceInfo()">
                        <option value="">-- Choose a Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= $service['id'] ?>" 
                                    data-days="<?= $service['estimated_days'] ?>"
                                    data-fee="<?= $service['fee_amount'] ?>"
                                    <?= (isset($_POST['section_id']) && $_POST['section_id'] == $service['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($service['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="serviceInfo" style="margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; display: none;">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div>
                                <strong>Processing Time:</strong>
                                <span id="serviceDays">-</span> days
                            </div>
                            <div>
                                <strong>Fee:</strong>
                                Rs. <span id="serviceFee">-</span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (empty($services)): ?>
                    <div class="alert" style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 8px; margin: 1rem 0;">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>No Active Services:</strong> You need to create at least one active service before creating applications.
                        <a href="add-section.php" style="color: #856404; text-decoration: underline; margin-left: 10px;">
                            Create Service Now
                        </a>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="notes">
                        <i class="fas fa-sticky-note"></i>
                        Notes (Optional)
                    </label>
                    <textarea id="notes" 
                              name="notes" 
                              rows="3"
                              placeholder="Add any notes about this application..."><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                </div>

                <div class="form-actions" style="display: flex; gap: 1rem; margin-top: 2rem;">
                    <button type="submit" class="btn btn-primary" <?= empty($services) ? 'disabled' : '' ?>>
                        <i class="fas fa-plus-circle"></i>
                        Create Application & Generate Tracking Number
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-redo"></i>
                        Clear Form
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Applications -->
        <?php if ($recent_apps && $recent_apps->num_rows > 0): ?>
            <div class="content-card" style="margin-top: 2rem;">
                <h3 style="margin-bottom: 1.5rem;">
                    <i class="fas fa-history"></i>
                    Recent Applications Created
                </h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tracking Number</th>
                            <th>Citizen</th>
                            <th>Service</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = $recent_apps->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong style="font-family: 'Courier New', monospace;">
                                        <?= htmlspecialchars($app['tracking_number']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?= htmlspecialchars($app['citizen_name']) ?>
                                    <br>
                                    <small style="color: #666;"><?= htmlspecialchars($app['citizen_phone']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($app['service_name']) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $app['status'] ?>">
                                        <?= ucwords(str_replace('_', ' ', $app['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($app['submitted_date'])) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Copy tracking number to clipboard
        function copyTracking() {
            const trackingNumber = document.getElementById('trackingNumber').textContent;
            navigator.clipboard.writeText(trackingNumber).then(() => {
                alert('Tracking number copied: ' + trackingNumber);
            });
        }

        // Check if citizen exists
        function checkCitizen() {
            const phone = document.getElementById('citizen_phone').value;
            if (phone.length !== 10) return;

            fetch('<?= BASE_URL ?>/api/check-citizen.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({phone: phone})
            })
            .then(r => r.json())
            .then(data => {
                const statusDiv = document.getElementById('citizenStatus');
                const nameField = document.getElementById('citizen_name');
                const nameRequired = document.getElementById('nameRequired');
                const nameHelp = document.getElementById('nameHelp');
                
                if (data.exists) {
                    statusDiv.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> Citizen found: ' + data.name + '</span>';
                    nameField.value = data.name;
                    nameField.readOnly = true;
                    nameField.required = false;
                    nameRequired.style.display = 'none';
                    nameHelp.textContent = 'Existing citizen account';
                } else {
                    statusDiv.innerHTML = '<span style="color: #ffc107;"><i class="fas fa-user-plus"></i> New citizen - account will be created</span>';
                    nameField.value = '';
                    nameField.readOnly = false;
                    nameField.required = true;
                    nameRequired.style.display = 'inline';
                    nameHelp.textContent = 'Required - new citizen account will be created';
                }
            })
            .catch(err => console.error('Error checking citizen:', err));
        }

        // Update service info
        function updateServiceInfo() {
            const select = document.getElementById('section_id');
            const option = select.options[select.selectedIndex];
            const infoDiv = document.getElementById('serviceInfo');
            
            if (option.value) {
                document.getElementById('serviceDays').textContent = option.dataset.days || '-';
                document.getElementById('serviceFee').textContent = parseFloat(option.dataset.fee || 0).toFixed(2);
                infoDiv.style.display = 'block';
            } else {
                infoDiv.style.display = 'none';
            }
        }

        // Auto-focus phone number
        document.getElementById('citizen_phone').focus();
    </script>
</body>
</html>