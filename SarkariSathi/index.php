<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SarkariSathi - सरकारी सेवा सहज</title>
    <link rel="stylesheet" href="assets/css/style-v2.css">
</head>
<body>
    <div class="landing-page">
        <header>
            <nav>
                <div class="logo">
                    <h1>Sarkari Sathi</h1>
                    <p>सरकारी सेवा सहज</p>
                </div>
            </nav>
        </header>
       
        <main>
            <section class="hero">
                <h1>Government Services Made Simple</h1>
                <p>Book appointments, track applications, and stay updated - all in one place</p>
               
                <div class="login-options">
                    <div class="login-card">
                        <h2>Citizen Login</h2>
                        <p>Track your applications, book appointments, and get help</p>
                        <a href="auth/login.php?role=citizen" class="btn btn-primary">Login as Citizen</a>
                        <a href="auth/register-citizen.php" class="link">Don't have an account? Register</a>
                    </div>
                   
                    <div class="login-card">
                        <h2>Officer Login</h2>
                        <p>Manage applications, handle queues, and assist citizens</p>
                        <a href="auth/login.php?role=officer" class="btn btn-secondary">Login as Officer</a>
                        <a href="auth/register-officer.php" class="link">Register as Officer</a>
                    </div>
                </div>
            </section>
           
            <section class="features">
                <h2>Key Features</h2>
                <div class="feature-grid">
                    <div class="feature">
                        <h3>Learn Before You Visit</h3>
                        <p>Watch videos and guides about document requirements</p>
                    </div>
                    <div class="feature">
                        <h3>Book Your Slot</h3>
                        <p>Reserve appointment times and skip long waits</p>
                    </div>
                    <div class="feature">
                        <h3>Track Live Queue</h3>
                        <p>See your position in queue from home</p>
                    </div>
                    <div class="feature">
                        <h3>Track Application Status</h3>
                        <p>Monitor your document processing in real-time</p>
                    </div>
                    <div class="feature">
                        <h3>Direct Communication</h3>
                        <p>Message officers for any queries</p>
                    </div>
                    <div class="feature">
                        <h3>File Complaints</h3>
                        <p>Report issues and track resolutions</p>
                    </div>
                </div>
            </section>
        </main>
       
        <footer>
            <p>&copy; 2025 SarkariSathi - Making Government Services Accessible</p>
        </footer>
    </div>
</body>
</html>
