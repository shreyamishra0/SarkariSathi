<?php
session_start();
require_once __DIR__ . '/../config.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'citizen') {
    header("Location: " . BASE_URL . "/auth/login.php");
    exit();
}

$citizen_id = $_SESSION['user_id'];

// Get active sections with error handling
$sections = [];
$stmt = $conn->prepare("SELECT id, name, office FROM sections WHERE is_active = TRUE ORDER BY name");

if ($stmt === false) {
    die("Database error: " . htmlspecialchars($conn->error) . "<br><br>Make sure the 'sections' table exists. Run database.sql to create tables.");
}

$stmt->execute();
$result = $stmt->get_result();
$sections = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Queue - SarkariSathi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/citizen.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/officer.css">
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
    <a href="<?= BASE_URL ?>/citizen/track-status.php">
        <i class="fas fa-search"></i> Track Status
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

                <div id="timeSlotsContainer" style="display: none;">
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
        <div id="successModal" class="modal" style="display: none;">
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

<style>
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
</style>

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