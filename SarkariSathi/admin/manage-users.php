<?php
require_once __DIR__ . '/../includes/admin-check.php';

// Admin name
$admin_name = $_SESSION['name'];

// Success/Error messages from URL (for delete)
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch users (citizens and officers)
$sql = "SELECT id, role, name, phone, email, office_name FROM users WHERE role IN ('citizen', 'officer') ORDER BY role, name";
$result = $conn->query($sql);
$users = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
} else {
    die("Database error: " . $conn->error);
}

// Separate by role
$citizens = array_filter($users, fn($u) => $u['role'] === 'citizen');
$officers = array_filter($users, fn($u) => $u['role'] === 'officer');
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<style>
/* --- Page Styles --- */
.main-content { padding: 2rem; margin-left: 250px; background: #f5f6fa; min-height: 100vh; }
h1.section-title { margin-bottom: 1.5rem; color: #333; }
.table-card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05); margin-bottom: 2rem; }
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; }
.admin-table th { background: #007BFF; color: #fff; font-weight: 600; }
.admin-table tr:hover { background: #f1f1f1; }
.btn { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 14px; text-decoration: none; color: #fff; transition: background 0.3s; cursor: pointer; }
.btn.edit { background: #28a745; }
.btn.edit:hover { background: #218838; }
.btn.delete { background: #dc3545; }
.btn.delete:hover { background: #c82333; }
.message { padding: 10px 15px; border-radius: 5px; margin-bottom: 1rem; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }

/* --- Modal Styles --- */
.modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
.modal-content { background: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 400px; position: relative; }
.close { position: absolute; top: 10px; right: 15px; font-size: 25px; font-weight: bold; cursor: pointer; }
.modal input, .modal select { width: 100%; padding: 8px; margin: 5px 0 15px 0; border-radius: 4px; border: 1px solid #ccc; }
.modal button { width: 100%; padding: 10px; border: none; border-radius: 5px; background: #007BFF; color: #fff; cursor: pointer; font-size: 16px; }
.modal button:hover { background: #0069d9; }

/* Delete Modal */
#deleteModal .modal-content {
    max-width: 450px;        /* wider modal */
    width: 90%;              /* responsive on smaller screens */
    padding: 30px;           /* more padding */
    border-radius: 10px;
    background: #fff;
    position: relative;
    text-align: center;      /* center the content */
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

#deleteModal .modal-content h3 {
    margin-top: 0;
    color: #dc3545;
    font-size: 22px;
}

#deleteModal .modal-content p {
    margin: 15px 0 25px 0;
    color: #333;
    font-size: 16px;
}

#deleteModal button {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    color: #fff;
    cursor: pointer;
    font-size: 14px;
    min-width: 100px;       /* buttons wider */
}

#deleteModal button:hover {
    opacity: 0.9;
}

#deleteModal #cancelDelete {
    background: #6c757d;
    margin-right: 15px;
}

#deleteModal #confirmDelete {
    background: #dc3545;
}

#deleteModal .close {
    position: absolute;
    top: 10px;
    right: 15px;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: #333;
}
#deleteModal .modal-content div {
    display: flex;
    justify-content: center;
    gap: 15px;
}

</style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🛡️ Admin Panel</h2>
   <a href="<?= BASE_URL ?>/admin/dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/admin/verify-officers.php">
        <i class="fas fa-user-check"></i> Verify Officers
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-users.php" class="active">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php">
        <i class="fas fa-exclamation-triangle"></i> Complaints
    </a> 
    <a href="<?= BASE_URL ?>/admin/profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h1 class="section-title">Manage Users</h1>

    <!-- Messages -->
    <div id="alert-container">
        <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div>

    <!-- Citizens Table -->
    <div class="table-card">
        <h2>Citizens</h2>
        <?php if (!empty($citizens)): ?>
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>Role</th><th>Name</th><th>Phone</th><th>Email</th><th>Office</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($citizens as $c): ?>
                <tr data-id="<?= $c['id'] ?>" data-role="<?= $c['role'] ?>" data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($c['phone'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($c['email'], ENT_QUOTES) ?>" data-office="">
                    <td><?= $c['id'] ?></td>
                    <td><?= $c['role'] ?></td>
                    <td><?= $c['name'] ?></td>
                    <td><?= $c['phone'] ?></td>
                    <td><?= $c['email'] ?></td>
                    <td>N/A</td>
                    <td>
                        <button class="btn edit" onclick="openEditModal(this)">Edit</button>
                        <button class="btn delete" onclick="deleteUser(<?= $c['id'] ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p>No citizens found.</p><?php endif; ?>
    </div>

    <!-- Officers Table -->
    <div class="table-card">
        <h2>Officers</h2>
        <?php if (!empty($officers)): ?>
        <table class="admin-table">
            <thead>
                <tr><th>ID</th><th>Role</th><th>Name</th><th>Phone</th><th>Email</th><th>Office</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($officers as $o): ?>
                <tr data-id="<?= $o['id'] ?>" data-role="<?= $o['role'] ?>" data-name="<?= htmlspecialchars($o['name'], ENT_QUOTES) ?>" data-phone="<?= htmlspecialchars($o['phone'], ENT_QUOTES) ?>" data-email="<?= htmlspecialchars($o['email'], ENT_QUOTES) ?>" data-office="<?= htmlspecialchars($o['office_name'], ENT_QUOTES) ?>">
                    <td><?= $o['id'] ?></td>
                    <td><?= $o['role'] ?></td>
                    <td><?= $o['name'] ?></td>
                    <td><?= $o['phone'] ?></td>
                    <td><?= $o['email'] ?></td>
                    <td><?= $o['office_name'] ?? 'N/A' ?></td>
                    <td>
                        <button class="btn edit" onclick="openEditModal(this)">Edit</button>
                        <a class="btn delete" href="delete-user.php?id=<?= $o['id'] ?>" onclick="return confirm('Are you sure you want to delete this user?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p>No officers found.</p><?php endif; ?>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeEditModal()">&times;</span>
        <h2>Edit User</h2>
        <form id="editUserForm">
            <input type="hidden" name="id" id="editUserId">
            <label>Name:</label>
            <input type="text" name="name" id="editUserName" required>
            <label>Phone:</label>
            <input type="text" name="phone" id="editUserPhone" required>
            <label>Email:</label>
            <input type="email" name="email" id="editUserEmail" required>
            <label>Office:</label>
            <input type="text" name="office_name" id="editUserOffice">
            <button type="submit">Update User</button>
        </form>
    </div>
</div>
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <span class="close" id="deleteModalClose">&times;</span>
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete this user?</p>
        <div style="text-align: right;">
            <button id="cancelDelete" style="background: #6c757d; margin-right: 10px;">Cancel</button>
            <button id="confirmDelete" style="background: #dc3545;">Delete</button>
        </div>
    </div>
</div>
<script>
// Modal logic
const modal = document.getElementById('editModal');
function openEditModal(button) {
    const tr = button.closest('tr');
    document.getElementById('editUserId').value = tr.dataset.id;
    document.getElementById('editUserName').value = tr.dataset.name;
    document.getElementById('editUserPhone').value = tr.dataset.phone;
    document.getElementById('editUserEmail').value = tr.dataset.email;
    document.getElementById('editUserOffice').value = tr.dataset.office;
    modal.style.display = 'block';
}

function closeEditModal() {
    modal.style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target == modal) { modal.style.display = "none"; }
}

// AJAX form submission
document.getElementById('editUserForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('edit-user.php', { // JSON endpoint
        method: 'POST',
        body: formData
    }).then(res => res.json())
      .then(data => {
          closeEditModal();
          const alertContainer = document.getElementById('alert-container');
          if (data.success) {
              alertContainer.innerHTML = `<div class="message success">${data.message}</div>`;
              // Update table row inline
              const row = document.querySelector(`tr[data-id='${formData.get('id')}']`);
              row.children[2].textContent = formData.get('name');
              row.children[3].textContent = formData.get('phone');
              row.children[4].textContent = formData.get('email');
              row.children[5].textContent = formData.get('office_name') || 'N/A';
          } else {
              alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
          }
      }).catch(err => {
          console.error(err);
          alert('An error occurred. Check console.');
      });

});

    let userIdToDelete = null; // store user ID temporarily
const deleteModal = document.getElementById('deleteModal');
const confirmDeleteBtn = document.getElementById('confirmDelete');
const cancelDeleteBtn = document.getElementById('cancelDelete');
const deleteModalClose = document.getElementById('deleteModalClose');

function deleteUser(userId) {
    userIdToDelete = userId;
    deleteModal.style.display = 'block';
}

// Close modal
cancelDeleteBtn.onclick = deleteModalClose.onclick = () => { 
    deleteModal.style.display = 'none'; 
    userIdToDelete = null;
}

// When clicking outside modal
window.onclick = function(event) {
    if (event.target == deleteModal) { 
        deleteModal.style.display = "none"; 
        userIdToDelete = null;
    }
}

// Confirm deletion
confirmDeleteBtn.onclick = function() {
    if (!userIdToDelete) return;
    
    fetch('delete-users.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: userIdToDelete })
    })
    .then(res => res.json())
    .then(data => {
        const alertContainer = document.getElementById('alert-container');
        if (data.success) {
            alertContainer.innerHTML = `<div class="message success">${data.message}</div>`;
            const row = document.querySelector(`tr[data-id='${userIdToDelete}']`);
            if (row) row.remove();
        } else {
            alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
        }
        deleteModal.style.display = 'none';
        userIdToDelete = null;
    })
    .catch(err => {
        console.error(err);
        alert('An error occurred while deleting the user.');
        deleteModal.style.display = 'none';
        userIdToDelete = null;
    });
}

</script>
</body>
</html>
