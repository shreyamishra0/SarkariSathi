<?php
session_start();
require_once __DIR__ . '/../config.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/" . $_SESSION['role'] . "/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Officer Registration - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #0d1b2a 0%, #1b3a4b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            max-width: 500px;
        }

        .auth-box {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            padding: 40px;
        }

        .logo-section {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo-section h1 {
            color: #00b4d8;
            font-size: 2em;
            margin-bottom: 10px;
        }

        .logo-section p {
            color: #666;
            font-size: 1em;
        }

        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #00b4d8;
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .info-box p {
            color: #004085;
            font-size: 0.9em;
            margin: 0;
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

        .form-group label .required {
            color: #dc3545;
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

        .form-group small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 0.875em;
        }

        .btn-primary {
            width: 100%;
            padding: 14px;
            background: #0d1b2a;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #00b4d8;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.4);
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .auth-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }

        .auth-footer a {
            color: #00b4d8;
            font-weight: 600;
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .auth-box {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="logo-section">
                <h1>👔 Officer Registration</h1>
                <p>Create your SarkariSathi officer account</p>
            </div>

            <div class="info-box">
                <p>⚠️ Your account will need admin verification before you can access the system.</p>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message"><?= htmlspecialchars($_GET['error']) ?></div>
            <?php elseif (isset($_GET['success'])): ?>
                <div class="success-message"><?= htmlspecialchars($_GET['success']) ?></div>
            <?php endif; ?>

            <form method="POST" action="<?= BASE_URL ?>/auth/process-register.php" onsubmit="return validateForm()">
                <input type="hidden" name="role" value="officer">

                <div class="form-group">
                    <label for="name">Full Name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="form-group">
                    <label for="email">Official Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" placeholder="officer@government.gov.np" required>
                    <small>Use your official government email</small>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" pattern="98[0-9]{8}" placeholder="98XXXXXXXX" required>
                    <small>Must be a valid 10-digit Nepali number</small>
                </div>

                <div class="form-group">
                    <label for="office">Office Name <span class="required">*</span></label>
                    <input type="text" id="office" name="office" placeholder="e.g., District Administration Office, Kathmandu" required>
                </div>

                <div class="form-group">
                    <label for="password">Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" minlength="6" required>
                    <small>Minimum 6 characters</small>
                </div>

                <div class="form-group">
                    <label for="confirm">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm" name="confirm" minlength="6" required>
                </div>

                <button type="submit" class="btn-primary">Register</button>
            </form>

            <div class="auth-footer">
                <p>Already have an account? <a href="<?= BASE_URL ?>/auth/login.php">Login here</a></p>
            </div>
        </div>
    </div>

    <script>
        function validateForm() {
            const pwd = document.getElementById("password").value;
            const confirm = document.getElementById("confirm").value;
            if (pwd !== confirm) {
                alert("Passwords do not match!");
                return false;
            }
            return true;
        }
    </script>
</body>
</html>