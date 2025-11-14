<?php
session_start();
require_once __DIR__ . '/../config.php';

// If already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $stmt = $conn->prepare("SELECT id, role FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            header("Location: " . BASE_URL . "/" . $user['role'] . "/dashboard.php");
            exit();
        }
        $stmt->close();
    }
    // Invalid session, clear it
    session_unset();
    session_destroy();
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SarkariSathi</title>
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
            max-width: 450px;
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

        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }

        .tab-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s;
            color: #333;
        }

        .tab-btn:hover {
            border-color: #00b4d8;
            color: #00b4d8;
        }

        .tab-btn.active {
            background: #0d1b2a;
            color: white;
            border-color: #0d1b2a;
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
            
            .tab-btn {
                font-size: 0.9em;
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <div class="logo-section">
                <h1>🏛️ SarkariSathi</h1>
                <p>Simplified Government Services</p>
            </div>

            <div class="auth-tabs">
                <button class="tab-btn active" onclick="switchTab('citizen')">Citizen</button>
                <button class="tab-btn" onclick="switchTab('officer')">Officer</button>
            </div>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <?= htmlspecialchars($_GET['error']) ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>

            <form id="loginForm" method="POST" action="<?= BASE_URL ?>/auth/process-login.php">
                <input type="hidden" name="role" id="roleInput" value="citizen">
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="your@email.com" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="auth-footer" id="registerLink">
                <p>Don't have an account? 
                    <a href="<?= BASE_URL ?>/auth/register-citizen.php">Register as Citizen</a>
                </p>
            </div>
        </div>
    </div>

    <script>
        function switchTab(role) {
            document.getElementById('roleInput').value = role;
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            const registerLink = document.getElementById('registerLink');
            if (role === 'citizen') {
                registerLink.innerHTML = '<p>Don\'t have an account? <a href="<?= BASE_URL ?>/auth/register-citizen.php">Register as Citizen</a></p>';
            } else if (role === 'officer') {
                registerLink.innerHTML = '<p>Don\'t have an account? <a href="<?= BASE_URL ?>/auth/register-officer.php">Register as Officer</a></p>';
            }
        }
    </script>
</body>
</html>