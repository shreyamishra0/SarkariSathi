<?php
session_start();
require_once __DIR__ . '/../config.php';

// Manual authentication check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'officer') {
    header("Location: ../auth/login.php");
    exit();
}

$officer_id = $_SESSION['user_id'];

// Fetch posts
$posts = $conn->query("SELECT * FROM posts WHERE officer_id = $officer_id ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Posts - Officer Panel</title>
    <link rel="stylesheet" href="../assets/css/style-v2.css">
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
            width: 250px;
            background: #0d1b2a;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #1b3a4b;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #00b4d8;
            font-size: 1.5rem;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #e0e0e0;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover {
            background: #1b3a4b;
            color: #00b4d8;
        }

        .sidebar-menu a.active {
            background: #00b4d8;
            color: white;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 30px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-title {
            color: #0d1b2a;
            font-size: 2rem;
        }

        .btn-primary {
            background: #00b4d8;
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary:hover {
            background: #0099c3;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 180, 216, 0.3);
        }

        /* Table Styles */
        .admin-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            border-collapse: collapse;
        }

        .admin-table th {
            background: #0d1b2a;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
        }

        .admin-table td {
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
        }

        .admin-table tr:hover {
            background: #f8f9fa;
        }

        .admin-table a {
            color: #00b4d8;
            text-decoration: none;
            margin: 0 5px;
            font-weight: 500;
        }

        .admin-table a:hover {
            text-decoration: underline;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-published { background: #d4edda; color: #155724; }
        .status-draft { background: #fff3cd; color: #856404; }

        .no-posts {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .no-posts i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #ccc;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h2>🏛️ Officer Panel</h2>
            <small style="color: #00b4d8;"><?php echo htmlspecialchars($_SESSION['name']); ?></small>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
            <li><a href="my-posts.php" class="active"><i class="fas fa-newspaper"></i> My Posts</a></li>
            <li><a href="queue-management.php"><i class="fas fa-list-ol"></i> Queue Management</a></li>
            <li><a href="applications.php"><i class="fas fa-file-alt"></i> Applications</a></li>
            <li><a href="messages.php"><i class="fas fa-envelope"></i> Messages</a></li>
            <li><a href="profile.php"><i class="fas fa-user"></i> Profile</a></li>
            <li><a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="page-header">
            <h1 class="page-title">My Posts</h1>
            <a href="add-post.php" class="btn-primary">
                <i class="fas fa-plus"></i> Create New Post
            </a>
        </div>

        <?php if ($posts->num_rows == 0): ?>
            <div class="no-posts">
                <i class="fas fa-newspaper"></i>
                <p>No posts found. Create your first post!</p>
            </div>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($post = $posts->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($post['title']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($post['created_at'])); ?></td>
                        <td>
                            <span class="status-badge status-<?php echo $post['post_type']; ?>">
                                <?php echo ucfirst($post['post_type']); ?>
                            </span>
                        </td>
                        <td>
                            <a href="edit-post.php?id=<?php echo $post['id']; ?>">
                                <i class="fas fa-edit"></i> Edit
                            </a> | 
                            <a href="delete-post.php?id=<?php echo $post['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete this post?')"
                               style="color: #dc3545;">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <script>
        // Simple confirmation for delete
        function confirmDelete() {
            return confirm('Are you sure you want to delete this post?');
        }
    </script>
</body>
</html>