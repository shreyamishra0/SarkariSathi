<?php
require_once __DIR__ . '/../includes/admin-check.php';

// Admin name
$admin_name = $_SESSION['name'];

// Success/Error messages from URL
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch complaints with citizen info
$sql = "SELECT c.id, c.citizen_id, u.name AS citizen_name, c.complaint_type, c.subject, c.description, c.status, c.officer_response, c.submitted_at, c.resolved_at
        FROM complaints c
        LEFT JOIN users u ON c.citizen_id = u.id
        ORDER BY c.submitted_at DESC";
$result = $conn->query($sql);
$complaints = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $complaints[] = $row;
    }
} else {
    die("Database error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Complaints - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<style>
.main-content { padding: 2rem; margin-left: 250px; background: #f5f6fa; min-height: 100vh; }
h1.section-title { margin-bottom: 1.5rem; color: #333; }
.table-card { background: #fff; padding: 1.5rem; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.05); margin-bottom: 2rem; }
.admin-table { width: 100%; border-collapse: collapse; }
.admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; }
.admin-table th { background: #007BFF; color: #fff; font-weight: 600; }
.admin-table tr:hover { background: #f1f1f1; }
.btn { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 14px; text-decoration: none; color: #fff; transition: background 0.3s; cursor: pointer; }
.admin-table .btn {
    padding: 3px 6px;
    font-size: 12px;
    border-radius: 3px;
}
.admin-table .btn.respond {
    background: #28a745;
}
.admin-table .btn.respond:hover {
    background: #218838;
}
.admin-table .btn.delete {
    background: #dc3545;
}
.admin-table .btn.delete:hover {
    background: #c82333;
}
.message { padding: 10px 15px; border-radius: 5px; margin-bottom: 1rem; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }

/* Status badges */
.status-pending { background: #ffc107; color: #212529; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
.status-resolved { background: #28a745; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: 600; }

/* Modal Styles */
.modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
.modal-content { background: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 400px; position: relative; }
.close { position: absolute; top: 10px; right: 15px; font-size: 25px; font-weight: bold; cursor: pointer; }
.modal input, .modal textarea, .modal select { width: 100%; padding: 8px; margin: 5px 0 15px 0; border-radius: 4px; border: 1px solid #ccc; }
.modal button { width: 100%; padding: 10px; border: none; border-radius: 5px; background: #007BFF; color: #fff; cursor: pointer; font-size: 16px; }
.modal button:hover { background: #0069d9; }
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
    <a href="<?= BASE_URL ?>/admin/manage-users.php">
        <i class="fas fa-users"></i> Manage Users
    </a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php"class="active">
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
    <h1 class="section-title">Manage Complaints</h1>

    <!-- Messages -->
    <div id="alert-container">
        <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div>

    <!-- Complaints Table -->
    <div class="table-card">
        <?php if (!empty($complaints)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Citizen</th>
                    <th>Type</th>
                    <th>Subject</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Officer Response</th>
                    <th>Submitted At</th>
                    <th>Resolved At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($complaints as $c): ?>
                <tr data-id="<?= $c['id'] ?>">
                    <td><?= $c['id'] ?></td>
                    <td><?= htmlspecialchars($c['citizen_name'] ?? 'Unknown') ?></td>
                    <td><?= htmlspecialchars($c['complaint_type']) ?></td>
                    <td><?= htmlspecialchars($c['subject']) ?></td>
                    <td><?= htmlspecialchars($c['description']) ?></td>
                    <td>
                        <span class="status-<?= strtolower($c['status']) ?>"><?= ucfirst($c['status']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($c['officer_response'] ?? '-') ?></td>
                    <td><?= $c['submitted_at'] ?></td>
                    <td><?= $c['resolved_at'] ?? '-' ?></td>
                    <td>
                        <button class="btn respond" onclick="openRespondModal(this)">Respond</button>
                        <button class="btn delete" onclick="deleteComplaint(<?= $c['id'] ?>)">Delete</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?><p>No complaints found.</p><?php endif; ?>
    </div>
</div>

<!-- Respond Modal -->
<div id="respondModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeRespondModal()">&times;</span>
        <h2>Respond to Complaint</h2>
        <form id="respondForm">
            <input type="hidden" name="id" id="complaintId">
            <label>Officer Response:</label>
            <textarea name="officer_response" id="complaintResponse" required></textarea>
            <label>Status:</label>
            <select name="status" id="complaintStatus" required>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
            </select>
            <button type="submit">Submit Response</button>
        </form>
    </div>
</div>

<script>
// Modal logic
const respondModal = document.getElementById('respondModal');
function openRespondModal(button) {
    const tr = button.closest('tr');
    document.getElementById('complaintId').value = tr.dataset.id;
    document.getElementById('complaintResponse').value = tr.children[6].textContent === '-' ? '' : tr.children[6].textContent;
    document.getElementById('complaintStatus').value = tr.children[5].textContent.toLowerCase();
    respondModal.style.display = 'block';
}

function closeRespondModal() { respondModal.style.display = 'none'; }
window.onclick = function(event) { if (event.target == respondModal) { respondModal.style.display = "none"; } }

// AJAX form submission
document.getElementById('respondForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);

    fetch('respond-complaint.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
      .then(data => {
          closeRespondModal();
          const alertContainer = document.getElementById('alert-container');
          if (data.success) {
              alertContainer.innerHTML = `<div class="message success">${data.message}</div>`;
              const row = document.querySelector(`tr[data-id='${formData.get('id')}']`);
              row.children[5].innerHTML = `<span class="status-${formData.get('status')}">${formData.get('status')}</span>`;
              row.children[6].textContent = formData.get('officer_response');
              if (formData.get('status') === 'resolved') row.children[8].textContent = new Date().toISOString().slice(0,19).replace('T',' ');
          } else {
              alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
          }
      }).catch(err => { console.error(err); alert('Error submitting response.'); });
});

// Delete complaint
function deleteComplaint(id) {
    if (!confirm('Are you sure you want to delete this complaint?')) return;
    fetch('delete-complaint.php', {
        method: 'POST',
        headers: { 'Content-Type':'application/json' },
        body: JSON.stringify({ id })
    }).then(res => res.json())
      .then(data => {
          const alertContainer = document.getElementById('alert-container');
          if (data.success) {
              alertContainer.innerHTML = `<div class="message success">${data.message}</div>`;
              const row = document.querySelector(`tr[data-id='${id}']`);
              if (row) row.remove();
          } else {
              alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
          }
      }).catch(err => { console.error(err); alert('Error deleting complaint.'); });
}
</script>
</body>
</html>
