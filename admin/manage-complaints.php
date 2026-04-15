<?php
require_once __DIR__ . '/../includes/admin-check.php';

// Admin name (if needed)
$admin_name = $_SESSION['name'] ?? 'Admin';

// Success/Error messages from URL
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';

// Fetch complaints with citizen info (select all needed columns)
$sql = "SELECT c.id, c.citizen_id, c.title, c.category, c.description, c.location, c.priority, c.image_path, c.status, c.admin_response, c.created_at, c.resolved_at,
               u.name AS citizen_name
        FROM complaints c
        LEFT JOIN users u ON c.citizen_id = u.id
        ORDER BY c.created_at DESC";

$result = $conn->query($sql);
if (!$result) {
    die("Database error: " . $conn->error);
}

$complaints = [];
while ($row = $result->fetch_assoc()) {
    $complaints[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Complaints - Admin</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
<style>
/* keep your styles (same as you had) */
.main-content { padding: 2rem; margin-left: 250px; background: #f5f6fa; min-height: 100vh; }
h1.section-title { margin-bottom: 1.5rem; color: #333; }
.table-card {
    background: #fff;
    padding: 1.5rem;
    border-radius: 8px;
    box-shadow: 0 0 15px rgba(0,0,0,0.05);
    margin-bottom: 2rem;

    overflow-x: auto;   /* ✅ THIS FIXES OVERFLOW */
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px; /* ✅ forces scroll instead of breaking layout */
}
.admin-table td {
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.cell-description {
    max-width: 250px;
}
.admin-table th, .admin-table td { padding: 12px 15px; border-bottom: 1px solid #ddd; text-align: left; }
.admin-table th { background: #007BFF; color: #fff; font-weight: 600; }
.admin-table tr:hover { background: #f1f1f1; }
.btn { display: inline-block; padding: 6px 12px; border-radius: 4px; font-size: 14px; text-decoration: none; color: #fff; transition: background 0.3s; cursor: pointer; }
.admin-table .btn { padding: 3px 6px; font-size: 12px; border-radius: 3px; }
.admin-table .btn.respond { background: #28a745; }
.admin-table .btn.respond:hover { background: #218838; }
.admin-table .btn.delete { background: #dc3545; }
.admin-table .btn.delete:hover { background: #c82333; }
.message { padding: 10px 15px; border-radius: 5px; margin-bottom: 1rem; }
.success { background: #d4edda; color: #155724; }
.error { background: #f8d7da; color: #721c24; }
.status-pending { background: #ffc107; color: #212529; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
.status-resolved { background: #28a745; color: #fff; padding: 4px 8px; border-radius: 4px; font-weight: 600; }
.modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4); }
.modal-content { background: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; width: 400px; position: relative; }
.close { position: absolute; top: 10px; right: 15px; font-size: 25px; font-weight: bold; cursor: pointer; }
.modal input, .modal textarea, .modal select { width: 100%; padding: 8px; margin: 5px 0 15px 0; border-radius: 4px; border: 1px solid #ccc; }
.modal button { width: 100%; padding: 10px; border: none; border-radius: 5px; background: #007BFF; color: #fff; cursor: pointer; font-size: 16px; }
.modal button:hover { background: #0069d9; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>🛡️ Admin Panel</h2>
    <a href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="<?= BASE_URL ?>/admin/verify-officers.php"><i class="fas fa-user-check"></i> Verify Officers</a>
    <a href="<?= BASE_URL ?>/admin/manage-users.php"><i class="fas fa-users"></i> Manage Users</a>
    <a href="<?= BASE_URL ?>/admin/manage-complaints.php" class="active"><i class="fas fa-exclamation-triangle"></i> Complaints</a>
    <a href="<?= BASE_URL ?>/admin/profile.php"><i class="fas fa-user"></i> Profile</a>
    <a href="<?= BASE_URL ?>/auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<!-- Main Content -->
<div class="main-content">
    <h1 class="section-title">Manage Complaints</h1>

    <div id="alert-container">
        <?php if ($success): ?><div class="message success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="message error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    </div>

    <div class="table-card">
        <?php if (!empty($complaints)): ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Citizen</th>
                    <th>Category</th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Officer Response</th>
                    <th>Submitted At</th>
                    <th>Resolved At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($complaints as $c): 
                // set default values and escape
                $id = (int)$c['id'];
                $citizen_name = htmlspecialchars($c['citizen_name'] ?? 'Unknown');
                $category = htmlspecialchars($c['category'] ?? '');
                $title = htmlspecialchars($c['title'] ?? '');
                $description = htmlspecialchars($c['description'] ?? '');
                $status = htmlspecialchars($c['status'] ?? 'pending');
                $admin_response = htmlspecialchars($c['admin_response'] ?? '');
                $created_at = htmlspecialchars($c['created_at'] ?? '');
                $resolved_at = htmlspecialchars($c['resolved_at'] ?? '');
            ?>
                <tr data-id="<?= $id ?>"
                    data-status="<?= $status ?>"
                    data-admin_response="<?= htmlspecialchars($c['admin_response'] ?? '', ENT_QUOTES) ?>"
                    data-created_at="<?= $created_at ?>"
                    data-resolved_at="<?= $resolved_at ?>">
                    <td class="cell-id"><?= $id ?></td>
                    <td class="cell-citizen"><?= $citizen_name ?></td>
                    <td class="cell-category"><?= $category ?></td>
                    <td class="cell-title"><?= $title ?></td>
                    <td class="cell-description"><?= $description ?></td>
                    <td class="cell-status"><span class="status-<?= strtolower($status) ?>"><?= ucfirst($status) ?></span></td>
                    <td class="cell-response"><?= $admin_response ?: '-' ?></td>
                    <td class="cell-created"><?= $created_at ?></td>
                    <td class="cell-resolved"><?= $resolved_at ?: '-' ?></td>
                    <td class="cell-actions">
                        <button class="btn delete" onclick="deleteComplaint(<?= $id ?>)">Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
            <p>No complaints found.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Respond Modal -->
<div id="respondModal" class="modal" aria-hidden="true">
    <div class="modal-content">
        <span class="close" onclick="closeRespondModal()">&times;</span>
        <h2>Respond to Complaint</h2>
        <form id="respondForm">
            <input type="hidden" name="id" id="complaintId">
            <label>Officer Response:</label>
            <textarea name="admin_response" id="complaintResponse" required></textarea>
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
// Helpers to find ancestor row
function getRowFromButton(button) {
    return button.closest('tr');
}

const respondModal = document.getElementById('respondModal');

function openRespondModal(button) {
    const tr = getRowFromButton(button);
    const id = tr.dataset.id;
    const admin_response = tr.dataset.admin_response || '';
    const status = tr.dataset.status || 'pending';

    document.getElementById('complaintId').value = id;
    document.getElementById('complaintResponse').value = admin_response === '-' ? '' : admin_response;
    document.getElementById('complaintStatus').value = status.toLowerCase();

    respondModal.style.display = 'block';
    respondModal.setAttribute('aria-hidden', 'false');
}

function closeRespondModal() {
    respondModal.style.display = 'none';
    respondModal.setAttribute('aria-hidden', 'true');
}

window.onclick = function(event) {
    if (event.target == respondModal) {
        closeRespondModal();
    }
}

// AJAX submission for response
document.getElementById('respondForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const form = this;
    const formData = new FormData(form);

    fetch('respond-complaint.php', {
        method: 'POST',
        body: formData
    }).then(res => res.json())
      .then(data => {
          closeRespondModal();
          const alertContainer = document.getElementById('alert-container');
          if (data.success) {
              // show success message
              alertContainer.innerHTML = `<div class="message success">${data.message}</div>`;

              // update UI row (robust: select by data-id)
              const id = formData.get('id');
              const status = formData.get('status');
              const admin_response = formData.get('admin_response');

              const row = document.querySelector(`tr[data-id='${CSS.escape(id)}']`);
              if (row) {
                  // update dataset
                  row.dataset.status = status;
                  row.dataset.admin_response = admin_response;

                  // update visible cells
                  const statusCell = row.querySelector('.cell-status');
                  if (statusCell) {
                      statusCell.innerHTML = `<span class="status-${status}">${status.charAt(0).toUpperCase() + status.slice(1)}</span>`;
                  }
                  const responseCell = row.querySelector('.cell-response');
                  if (responseCell) responseCell.textContent = admin_response || '-';

                  // if resolved, set resolved_at to current timestamp (server should set real one; this is client-side mimic)
                  if (status === 'resolved') {
                      const now = new Date().toISOString().slice(0,19).replace('T',' ');
                      const resolvedCell = row.querySelector('.cell-resolved');
                      if (resolvedCell) resolvedCell.textContent = now;
                      row.dataset.resolved_at = now;
                  }
              }
          } else {
              alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
          }
      }).catch(err => {
          console.error(err);
          alert('Error submitting response.');
      });
});

// delete complaint
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
              const row = document.querySelector(`tr[data-id='${CSS.escape(id)}']`);
              if (row) row.remove();
          } else {
              alertContainer.innerHTML = `<div class="message error">${data.message}</div>`;
          }
      }).catch(err => {
          console.error(err);
          alert('Error deleting complaint.');
      });
}
</script>
</body>
</html>
