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

// Get active sections with error handling - FIXED: Get office_name from users table via join
$sections = [];
$stmt = $conn->prepare("
    SELECT s.id, s.name, u.office_name 
    FROM sections s
    JOIN users u ON s.officer_id = u.id
    WHERE s.is_active = TRUE 
    ORDER BY s.name
");

if ($stmt === false) {
    die("Database error: " . htmlspecialchars($conn->error) . "<br><br>Make sure the 'sections' table exists. Run database.sql to create tables.");
}

$stmt->execute();
$result = $stmt->get_result();
$sections = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get unread messages count - FIXED: Use receiver_id instead of recipient_id
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
    <title>Book Queue - SarkariSathi</title>
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

.welcome-header {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.03);
}

.welcome-header h1 {
    color: #0d1b2a;
    font-size: 1.75rem;
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.welcome-header p {
    color: #666;
    font-size: 1rem;
}

/* ===== ALERT BOX ===== */
.alert-box {
    background: white;
    padding: 40px;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    text-align: center;
    margin: 20px 0;
}

.alert-box.warning {
    border-left: 5px solid #ffc107;
}

.alert-box i {
    font-size: 3rem;
    color: #ffc107;
    margin-bottom: 20px;
}

.alert-box h3 {
    color: #0d1b2a;
    margin-bottom: 10px;
}

.alert-box p {
    color: #666;
}

/* ===== BOOKING CONTAINER ===== */
.booking-container {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.form-section {
    display: none;
}

.form-section.active {
    display: block;
}

.form-section h2 {
    color: #0d1b2a;
    font-size: 1.5rem;
    margin-bottom: 1.5rem;
    padding-bottom: 0.75rem;
    border-bottom: 3px solid #00b4d8;
}

.form-group {
    margin-bottom: 1.25rem;
}

.form-group label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
    color: #0d1b2a;
}

.form-group input,
.form-group select {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 1rem;
    transition: border 0.3s;
}

.form-group input:focus,
.form-group select:focus {
    outline: none;
    border-color: #00b4d8;
}

.form-group small {
    display: block;
    margin-top: 0.25rem;
    color: #666;
    font-size: 0.875rem;
}

.info-box {
    background: #d1ecf1;
    padding: 12px 20px;
    border-radius: 8px;
    margin-bottom: 1.25rem;
    border: 1px solid #bee5eb;
}

.info-box strong {
    color: #0c5460;
}

.info-box span {
    color: #0c5460;
    font-weight: 600;
}

/* ===== TIME SLOTS ===== */
#timeSlotsContainer {
    display: none;
}

.time-slots-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 1rem;
    margin-top: 1rem;
}

.time-slot {
    padding: 1rem;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
}

.time-slot:hover {
    border-color: #00b4d8;
    background: #f0f9ff;
}

.time-slot.selected {
    border-color: #00b4d8;
    background: #00b4d8;
    color: white;
}

.time-slot.full {
    opacity: 0.5;
    cursor: not-allowed;
    background: #f8f9fa;
}

.time-slot .time {
    font-weight: 600;
    font-size: 1.1rem;
    margin-bottom: 0.5rem;
}

.time-slot .available {
    font-size: 0.85rem;
    color: #666;
}

.time-slot.selected .available {
    color: white;
}

/* ===== BUTTONS ===== */
.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 1rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: #0d1b2a;
    color: white;
}

.btn-primary:hover {
    background: #00b4d8;
    transform: translateY(-2px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.form-actions {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
}

/* ===== CONFIRMATION ===== */
.confirmation-details {
    background: #f8f9fa;
    padding: 1.5rem;
    border-radius: 10px;
    margin-bottom: 1.5rem;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 0.75rem 0;
    border-bottom: 1px solid #e0e0e0;
}

.detail-row:last-child {
    border-bottom: none;
}

.detail-row .label {
    color: #666;
    font-weight: 500;
}

.detail-row .value {
    color: #0d1b2a;
    font-weight: 600;
}

.important-note {
    background: #fff3cd;
    border-left: 4px solid #ffc107;
    padding: 1rem;
    border-radius: 8px;
    margin-bottom: 1.5rem;
}

.important-note strong {
    color: #856404;
    display: block;
    margin-bottom: 0.5rem;
}

.important-note ul {
    margin-left: 1.5rem;
    color: #856404;
}

.important-note li {
    margin-bottom: 0.25rem;
}

/* ===== MODAL ===== */
.modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: white;
    padding: 2rem;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
    text-align: center;
}

.modal-content h2 {
    color: #0d1b2a;
    margin-bottom: 1rem;
}

.queue-number-display {
    background: linear-gradient(135deg, #00b4d8, #0d1b2a);
    padding: 2rem;
    border-radius: 12px;
    margin: 1.5rem 0;
}

.queue-number-display p {
    color: white;
    margin-bottom: 0.5rem;
}

.queue-number-display h1 {
    color: white;
    font-size: 3rem;
}

.booking-details {
    text-align: left;
    background: #f8f9fa;
    padding: 1rem;
    border-radius: 8px;
    margin: 1rem 0;
}

.booking-details p {
    margin-bottom: 0.5rem;
}

.modal-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1.5rem;
}

.modal-actions .btn {
    flex: 1;
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
    .time-slots-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .modal-actions {
        flex-direction: column;
    }
}
    </style>
</head>
<body>

<!-- Sidebar Navigation -->
<div class="sidebar">
    <h2>🏛️ SarkariSathi</h2>
    <a href="<?= BASE_URL ?>/citizen/dashboard.php">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="<?= BASE_URL ?>/citizen/sections.php">
        <i class="fas fa-list"></i> Services
    </a>
    <a href="<?= BASE_URL ?>/citizen/queue-booking.php" class="active">
        <i class="fas fa-calendar-check"></i> Book Queue
    </a>
    <a href="<?= BASE_URL ?>/citizen/my-queue.php">
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

<!-- Main Content -->
<div class="main-content">
    <div class="welcome-header">
        <h1>Book Appointment</h1>
        <p>Schedule your visit to the government office</p>
    </div>

    <?php if (empty($sections)): ?>
        <div class="alert-box warning">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>No Services Available</h3>
            <p>There are currently no active services available for booking. Please check back later or contact your local office.</p>
        </div>
    <?php else: ?>

    <div class="booking-container">
        <form id="queueBookingForm" class="booking-form">
            <!-- Step 1: Select Service -->
            <div class="form-section active" data-step="1">
                <h2>Step 1: Select Service</h2>
                
                <div class="form-group">
                    <label for="section_id">Service Type *</label>
                    <select id="section_id" name="section_id" required>
                        <option value="">-- Select Service --</option>
                        <?php foreach ($sections as $section): ?>
                            <option value="<?= $section['id'] ?>" data-office="<?= htmlspecialchars($section['office_name']) ?>">
                                <?= htmlspecialchars($section['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="officeInfo" class="info-box" style="display: none;">
                    <strong>Office:</strong> <span id="officeName"></span>
                </div>

                <div class="form-group">
                    <label for="visit_type">Visit Type *</label>
                    <select id="visit_type" name="visit_type" required>
                        <option value="">-- Select Visit Type --</option>
                        <option value="submission">Document Submission (First Visit)</option>
                        <option value="pickup">Document Pickup</option>
                        <option value="inquiry">General Inquiry</option>
                    </select>
                </div>

                <div id="trackingNumberSection" style="display: none;">
                    <div class="form-group">
                        <label for="tracking_number">Tracking Number (for pickup)</label>
                        <input type="text" id="tracking_number" name="tracking_number" placeholder="e.g., PAS-2025-0123">
                    </div>
                </div>

                <button type="button" class="btn btn-primary" onclick="goToStep(2)">Next: Select Date & Time</button>
            </div>

            <!-- Step 2: Select Date & Time -->
            <div class="form-section" data-step="2">
                <h2>Step 2: Select Date & Time</h2>
                
                <div class="form-group">
                    <label for="queue_date">Select Date *</label>
                    <input type="date" id="queue_date" name="queue_date" required>
                    <small>You can book up to 7 days in advance</small>
                </div>

                <div id="timeSlotsContainer">
                    <label>Select Time Slot *</label>
                    <div id="timeSlots" class="time-slots-grid"></div>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">Back</button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">Next: Confirm</button>
                </div>
            </div>

            <!-- Step 3: Confirm Booking -->
            <div class="form-section" data-step="3">
                <h2>Step 3: Confirm Booking</h2>
                
                <div class="confirmation-details">
                    <div class="detail-row">
                        <span class="label">Service:</span>
                        <span id="confirm_service" class="value"></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Visit Type:</span>
                        <span id="confirm_visit_type" class="value"></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Date:</span>
                        <span id="confirm_date" class="value"></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Time:</span>
                        <span id="confirm_time" class="value"></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Office:</span>
                        <span id="confirm_office" class="value"></span>
                    </div>
                </div>

                <div class="important-note">
                    <strong>Important:</strong>
                    <ul>
                        <li>Please arrive 10 minutes before your scheduled time</li>
                        <li>Bring all required documents</li>
                        <li>Check in at the reception upon arrival</li>
                        <li>Your queue number will be displayed after booking</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(2)">Back</button>
                    <button type="submit" class="btn btn-primary">Confirm Booking</button>
                </div>
            </div>
        </form>

        <!-- Success Modal -->
        <div id="successModal" class="modal">
            <div class="modal-content">
                <h2>Booking Confirmed!</h2>
                <div class="queue-number-display">
                    <p>Your Queue Number:</p>
                    <h1 id="displayQueueNumber"></h1>
                </div>
                <div class="booking-details">
                    <p><strong>Date:</strong> <span id="modal_date"></span></p>
                    <p><strong>Time:</strong> <span id="modal_time"></span></p>
                    <p><strong>Service:</strong> <span id="modal_service"></span></p>
                </div>
                <div class="modal-actions">
                    <a href="<?= BASE_URL ?>/citizen/my-queue.php" class="btn btn-primary">View My Bookings</a>
                    <button onclick="closeModal()" class="btn btn-secondary">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php endif; ?>
</div>

<script>
const BASE_URL = '<?= BASE_URL ?>';
let selectedSlot = null;
let bookingData = {};

// Set min and max date
const today = new Date();
const dateInput = document.getElementById('queue_date');
if (dateInput) {
    dateInput.min = today.toISOString().split('T')[0];
    const maxDate = new Date();
    maxDate.setDate(maxDate.getDate() + 7);
    dateInput.max = maxDate.toISOString().split('T')[0];
}

// Show office info when section selected
const sectionSelect = document.getElementById('section_id');
if (sectionSelect) {
    sectionSelect.addEventListener('change', function() {
        const selected = this.options[this.selectedIndex];
        if (this.value) {
            document.getElementById('officeInfo').style.display = 'block';
            document.getElementById('officeName').textContent = selected.dataset.office;
        } else {
            document.getElementById('officeInfo').style.display = 'none';
        }
    });
}

// Show tracking number field for pickup
const visitTypeSelect = document.getElementById('visit_type');
if (visitTypeSelect) {
    visitTypeSelect.addEventListener('change', function() {
        const trackingSection = document.getElementById('trackingNumberSection');
        trackingSection.style.display = this.value === 'pickup' ? 'block' : 'none';
    });
}

// Load time slots when date selected
if (dateInput) {
    dateInput.addEventListener('change', function() {
        const sectionId = document.getElementById('section_id').value;
        if (!sectionId) {
            alert('Please select a service first');
            return;
        }
        loadTimeSlots(sectionId, this.value);
    });
}

async function loadTimeSlots(sectionId, date) {
    const container = document.getElementById('timeSlotsContainer');
    const slotsGrid = document.getElementById('timeSlots');
    
    try {
        const response = await fetch(`${BASE_URL}/api/get-available-slot.php?section_id=${sectionId}&date=${date}`);
        const data = await response.json();
        
        if (data.success) {
            slotsGrid.innerHTML = '';
            data.slots.forEach(slot => {
                const slotDiv = document.createElement('div');
                slotDiv.className = 'time-slot' + (slot.available === 0 ? ' full' : '');
                slotDiv.innerHTML = `
                    <div class="time">${slot.time_display}</div>
                    <div class="available">${slot.available} slots left</div>
                `;
                
                if (slot.available > 0) {
                    slotDiv.onclick = () => selectTimeSlot(slot.time_slot, slot.time_display, slotDiv);
                }
                
                slotsGrid.appendChild(slotDiv);
            });
            
            container.style.display = 'block';
        } else {
            alert(data.message || 'Failed to load time slots');
        }
    } catch (error) {
        console.error('Error loading slots:', error);
        alert('Failed to load time slots. Please try again.');
    }
}

function selectTimeSlot(time, display, element) {
    document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
    element.classList.add('selected');
    selectedSlot = { time, display };
}

function goToStep(step) {
    // Validate current step
    if (step === 2) {
        if (!document.getElementById('section_id').value || !document.getElementById('visit_type').value) {
            alert('Please fill all required fields');
            return;
        }
    }
    
    if (step === 3) {
        if (!document.getElementById('queue_date').value || !selectedSlot) {
            alert('Please select date and time slot');
            return;
        }
        updateConfirmation();
    }
    
    document.querySelectorAll('.form-section').forEach(section => section.classList.remove('active'));
    document.querySelector(`[data-step="${step}"]`).classList.add('active');
}

function updateConfirmation() {
    const sectionSelect = document.getElementById('section_id');
    const visitTypeSelect = document.getElementById('visit_type');
    const date = document.getElementById('queue_date').value;
    
    document.getElementById('confirm_service').textContent = sectionSelect.options[sectionSelect.selectedIndex].text;
    document.getElementById('confirm_visit_type').textContent = visitTypeSelect.options[visitTypeSelect.selectedIndex].text;
    document.getElementById('confirm_date').textContent = new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    document.getElementById('confirm_time').textContent = selectedSlot.display;
    document.getElementById('confirm_office').textContent = document.getElementById('officeName').textContent;
}

const bookingForm = document.getElementById('queueBookingForm');
if (bookingForm) {
    bookingForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = {
            section_id: document.getElementById('section_id').value,
            visit_type: document.getElementById('visit_type').value,
            queue_date: document.getElementById('queue_date').value,
            time_slot: selectedSlot.time,
            tracking_number: document.getElementById('tracking_number').value
        };
        
        try {
            const response = await fetch(`${BASE_URL}/api/book-queue.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(formData)
            });
            
            const data = await response.json();
            
            if (data.success) {
                showSuccessModal(data);
            } else {
                alert(data.message || 'Booking failed');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Booking failed. Please try again.');
        }
    });
}

function showSuccessModal(data) {
    document.getElementById('displayQueueNumber').textContent = data.queue_number;
    document.getElementById('modal_date').textContent = document.getElementById('confirm_date').textContent;
    document.getElementById('modal_time').textContent = document.getElementById('confirm_time').textContent;
    document.getElementById('modal_service').textContent = document.getElementById('confirm_service').textContent;
    document.getElementById('successModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('successModal').style.display = 'none';
    window.location.href = `${BASE_URL}/citizen/my-queue.php`;
}
</script>

</body>
</html>