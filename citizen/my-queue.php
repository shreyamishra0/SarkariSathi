<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];
$citizen_name = $_SESSION['name'];

// Get all queue bookings for this citizen
$query = $conn->prepare("
    SELECT q.*, s.name as section_name, u.office_name
    FROM queue q
    JOIN sections s ON q.section_id = s.id
    JOIN users u ON s.officer_id = u.id
    WHERE q.citizen_id = ?
    ORDER BY q.queue_date DESC, q.time_slot DESC
");
$query->bind_param("i", $citizen_id);
$query->execute();
$bookings = $query->get_result();

// Get unread messages count
$unread_messages_query = $conn->query("
    SELECT COUNT(*) as count 
    FROM messages 
    WHERE receiver_id = $citizen_id AND is_read = 0
");
$unread_messages = $unread_messages_query ? $unread_messages_query->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Queue Bookings - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
/* ===== GLOBAL STYLES ===== */
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Inter', sans-serif;
}

body {
    background: #f4f7fb;
    min-height: 100vh;
    color: #333;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    bottom: 0;
    width: 260px;
    background: #0d1b2a;
    color: #fff;
    padding: 2rem 1.5rem;
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
    box-shadow: 2px 0 10px rgba(0,0,0,0.05);
    z-index: 1000;
}

.sidebar h2 {
    font-size: 1.25rem;
    color: #00b4d8;
    margin-bottom: 0.5rem;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    text-decoration: none;
    color: #cfe8f3;
    padding: 0.6rem 0.75rem;
    border-radius: 8px;
    font-weight: 600;
    transition: background 0.2s, color 0.2s;
}

.sidebar a i {
    color: #00b4d8;
    min-width: 18px;
}

.sidebar a.active,
.sidebar a:hover {
    background: rgba(255,255,255,0.06);
    color: #fff;
}

.sidebar .badge {
    background: #ff4d4f;
    color: white;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.75rem;
    margin-left: auto;
}

/* ===== MAIN CONTENT ===== */
.main-content {
    margin-left: 300px;
    padding: 2rem;
    max-width: calc(100% - 300px);
}

.main-content > h1 {
    color: #0d1b2a;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.main-content > p {
    color: #666;
    font-size: 1rem;
    margin-bottom: 2rem;
}

/* ===== FILTER TABS ===== */
.filter-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-tab {
    padding: 10px 20px;
    border: 2px solid #e0e0e0;
    background: white;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 600;
}

.filter-tab.active {
    background: #0d1b2a;
    color: white;
    border-color: #0d1b2a;
}

.filter-tab:hover:not(.active) {
    border-color: #00b4d8;
    color: #00b4d8;
}

/* ===== BOOKINGS GRID ===== */
.bookings-grid {
    display: grid;
    gap: 20px;
    margin-top: 20px;
}

.booking-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    transition: all 0.3s;
}

.booking-card:hover {
    box-shadow: 0 5px 20px rgba(0,180,216,0.2);
    transform: translateY(-3px);
}

.booking-header {
    display: flex;
    justify-content: space-between;
    align-items: start;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #f0f0f0;
}

.queue-number {
    background: linear-gradient(135deg, #00b4d8, #0d1b2a);
    color: white;
    padding: 10px 20px;
    border-radius: 8px;
    font-size: 1.5rem;
    font-weight: bold;
}

.status-badge {
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.status-badge.booked {
    background: #d1ecf1;
    color: #0c5460;
}

.status-badge.checked_in {
    background: #fff3cd;
    color: #856404;
}

.status-badge.in_service {
    background: #cce5ff;
    color: #004085;
}

.status-badge.completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.no_show {
    background: #f8d7da;
    color: #721c24;
}

.booking-details {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 15px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-item .label {
    color: #666;
    font-size: 0.875rem;
    margin-bottom: 5px;
}

.detail-item .value {
    color: #333;
    font-weight: 600;
    font-size: 1rem;
}

.detail-item i {
    color: #00b4d8;
    margin-right: 8px;
}

.booking-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

.btn-action {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
    display: inline-block;
}

.btn-cancel {
    background: #dc3545;
    color: white;
}

.btn-cancel:hover {
    background: #c82333;
    transform: translateY(-2px);
}

.btn-view {
    background: #0d1b2a;
    color: white;
}

.btn-view:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.empty-state i {
    font-size: 4rem;
    color: #00b4d8;
    margin-bottom: 20px;
}

.empty-state h3 {
    color: #0d1b2a;
    margin-bottom: 10px;
}

.empty-state p {
    color: #666;
    margin-bottom: 30px;
}

/* ===== RESPONSIVE ===== */
@media (max-width: 992px) {
    .sidebar {
        position: relative;
        width: 100%;
        flex-direction: row;
        gap: 0.5rem;
        padding: 0.75rem;
        overflow-x: auto;
    }
    
    .sidebar h2 {
        display: none;
    }
    
    .sidebar a {
        white-space: nowrap;
    }
    
    .main-content {
        margin-left: 0;
        max-width: 100%;
        padding: 1rem;
    }
}

@media (max-width: 768px) {
    .booking-header {
        flex-direction: column;
        gap: 10px;
    }
    
    .booking-details {
        grid-template-columns: 1fr;
    }
    
    .filter-tabs {
        flex-direction: column;
    }
}
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h2>🏛️ SarkariSathi</h2>
    <a href="<?= BASE_URL ?>/citizen/dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/citizen/sections.php">
        <i class="fas fa-list"></i> Services
    </a>
    <a href="<?= BASE_URL ?>/citizen/queue-booking.php">
        <i class="fas fa-calendar-check"></i> Book Queue
    </a>
    <a href="<?= BASE_URL ?>/citizen/my-queue.php" class="active">
        <i class="fas fa-users"></i> My Queue
    </a>
    <a href="<?= BASE_URL ?>/citizen/smart-track-status.php">
        <i class="fas fa-search"></i> Track Status
    </a>
    <a href="<?= BASE_URL ?>/citizen/complaints.php">
        <i class="fas fa-exclamation-circle"></i> Complaints
    </a>
    <a href="<?= BASE_URL ?>/citizen/messages.php">
        <i class="fas fa-envelope"></i> Messages
        <?php if ($unread_messages > 0): ?>
            <span class="badge"><?= $unread_messages ?></span>
        <?php endif; ?>
    </a>
    <a href="<?= BASE_URL ?>/citizen/profile.php">
        <i class="fas fa-user"></i> Profile
    </a>
    <a href="<?= BASE_URL ?>/auth/logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>

<div class="main-content">
    <h1>My Queue Bookings</h1>
    <p>View and manage your appointment bookings</p>

    <!-- Filter Tabs -->
    <div class="filter-tabs">
        <div class="filter-tab active" onclick="filterBookings('all')">
            All Bookings
        </div>
        <div class="filter-tab" onclick="filterBookings('upcoming')">
            Upcoming
        </div>
        <div class="filter-tab" onclick="filterBookings('completed')">
            Completed
        </div>
    </div>

    <?php if ($bookings->num_rows > 0): ?>
        <div class="bookings-grid" id="bookingsContainer">
            <?php while ($booking = $bookings->fetch_assoc()): 
                $booking_date = strtotime($booking['queue_date']);
                $today = strtotime(date('Y-m-d'));
                $is_upcoming = $booking_date >= $today;
                $is_completed = in_array($booking['status'], ['completed', 'no_show']);
            ?>
                <div class="booking-card" data-status="<?= $booking['status'] ?>" data-upcoming="<?= $is_upcoming ? '1' : '0' ?>" data-completed="<?= $is_completed ? '1' : '0' ?>">
                    <div class="booking-header">
                        <div class="queue-number">
                            <?= htmlspecialchars($booking['queue_number']) ?>
                        </div>
                        <div class="status-badge <?= $booking['status'] ?>">
                            <?= ucwords(str_replace('_', ' ', $booking['status'])) ?>
                        </div>
                    </div>

                    <div class="booking-details">
                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-calendar"></i> Date
                            </span>
                            <span class="value">
                                <?= date('F d, Y', strtotime($booking['queue_date'])) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-clock"></i> Time
                            </span>
                            <span class="value">
                                <?= date('h:i A', strtotime($booking['time_slot'])) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-briefcase"></i> Service
                            </span>
                            <span class="value">
                                <?= htmlspecialchars($booking['section_name']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-building"></i> Office
                            </span>
                            <span class="value">
                                <?= htmlspecialchars($booking['office_name']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-tag"></i> Visit Type
                            </span>
                            <span class="value">
                                <?= ucfirst($booking['visit_type']) ?>
                            </span>
                        </div>

                        <div class="detail-item">
                            <span class="label">
                                <i class="fas fa-calendar-plus"></i> Booked On
                            </span>
                            <span class="value">
                                <?= date('M d, Y', strtotime($booking['created_at'])) ?>
                            </span>
                        </div>
                    </div>

                    <?php if ($booking['status'] === 'booked' && $is_upcoming): ?>
                        <div class="booking-actions">
                            <button class="btn-action btn-cancel" onclick="cancelBooking(<?= $booking['id'] ?>)">
                                <i class="fas fa-times"></i> Cancel Booking
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Bookings Found</h3>
            <p>You haven't made any queue bookings yet</p>
            <a href="<?= BASE_URL ?>/citizen/queue-booking.php" class="btn-action btn-view">
                <i class="fas fa-plus"></i> Book Your First Appointment
            </a>
        </div>
    <?php endif; ?>
</div>

<script>
function filterBookings(filter) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
    event.target.classList.add('active');
    
    // Filter bookings
    const bookings = document.querySelectorAll('.booking-card');
    
    bookings.forEach(booking => {
        const isUpcoming = booking.dataset.upcoming === '1';
        const isCompleted = booking.dataset.completed === '1';
        
        if (filter === 'all') {
            booking.style.display = 'block';
        } else if (filter === 'upcoming') {
            booking.style.display = isUpcoming && !isCompleted ? 'block' : 'none';
        } else if (filter === 'completed') {
            booking.style.display = isCompleted ? 'block' : 'none';
        }
    });
}

function cancelBooking(bookingId) {
    if (!confirm('Are you sure you want to cancel this booking?')) {
        return;
    }
    
    fetch('<?= BASE_URL ?>/api/cancel-booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Booking cancelled successfully');
            location.reload();
        } else {
            alert(data.message || 'Failed to cancel booking');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to cancel booking');
    });
}
</script>

</body>
</html>