<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once("../config/db.php");

// Fetch facilities for dropdown
$facilities = $conn->query("SELECT id, name, price_per_hour, price_per_day, open_time, close_time, advance_days_required, min_duration_hours, max_duration_hours, allowed_days, capacity FROM facilities WHERE status = 'AVAILABLE'");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fb_name = trim($_POST["fb_name"]);
    $user_type = $_POST["user_type"];
    $id_number = trim($_POST["id_number"] ?? '');
    $user_email = trim($_POST["user_email"] ?? '');
    $user_phone = trim($_POST["user_phone"] ?? '');
    $host_person = trim($_POST["host_person"] ?? '');
    $facility_id = (int)$_POST["facility_id"];
    $reservation_date = $_POST["reservation_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $purpose = trim($_POST["purpose"]);
    $num_attendees = isset($_POST["num_attendees"]) ? (int)$_POST["num_attendees"] : null;

    // Validate email format
    if (!empty($user_email) && !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    }
    // Validate phone format
    if (!empty($user_phone) && !preg_match('/^[\d\s\-\+\(\)]{7,20}$/', $user_phone)) {
        $error = "Please enter a valid phone number.";
    }

    if (empty($fb_name) || empty($user_type) || empty($facility_id) || empty($reservation_date) || empty($start_time) || empty($end_time)) {
        $error = "All required fields must be filled.";
    } elseif (($user_type == 'STUDENT' || $user_type == 'STAFF') && empty($id_number)) {
        $error = "Identification ID is required for Students and Staff.";
    } elseif ($user_type == 'OUTSIDE' && empty($user_email) && empty($host_person)) {
        $error = "Either Email or Host Person is required for Outside users.";
    } else {
        // Fetch facility rules
        $fstmt = $conn->prepare("SELECT * FROM facilities WHERE id = ? AND status = 'AVAILABLE'");
        $fstmt->bind_param("i", $facility_id);
        $fstmt->execute();
        $fac = $fstmt->get_result()->fetch_assoc();
        $fstmt->close();

        if (!$fac) {
            $error = "Selected facility is not available.";
        }
        
        // === VALIDATIONS ===
        if (!isset($error)) {
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $resDate = new DateTime($reservation_date);
            $resDate->setTime(0, 0, 0);
            $isInPast = $resDate < $today;
            
            if ($isInPast) {
                $error = "Cannot book a date in the past.";
            } 
            // Note: We skip the advance_days_required for walk-ins as per user requirement (physically present)
        }

        // opening/closing, duration, capacity, conflicts... (similar to create.php)
        if (!isset($error)) {
            $facOpen = substr($fac['open_time'], 0, 5);
            $facClose = substr($fac['close_time'], 0, 5);
            if ($start_time < $facOpen || $end_time > $facClose) {
                $error = "Time must be between {$facOpen} and {$facClose} for this facility.";
            }
            if ($start_time >= $end_time) {
                $error = "End time must be after start time.";
            }
        }

        if (!isset($error)) {
            $startDT = new DateTime($start_time);
            $endDT = new DateTime($end_time);
            $durationMinutes = ($endDT->getTimestamp() - $startDT->getTimestamp()) / 60;
            $durationHours = $durationMinutes / 60;
            
            if ($durationHours < $fac['min_duration_hours']) {
                $error = "Minimum booking duration is {$fac['min_duration_hours']} hour(s).";
            }
            if ($durationHours > $fac['max_duration_hours']) {
                $error = "Maximum booking duration is {$fac['max_duration_hours']} hours.";
            }
        }

        // Conflict detection (same as create.php)
        if (!isset($error)) {
            $approved_conflict = $conn->prepare("
                SELECT id, fb_name FROM reservations
                WHERE facility_id = ? AND reservation_date = ? AND status = 'APPROVED'
                AND (? < end_time) AND (? > start_time)
            ");
            $approved_conflict->bind_param("isss", $facility_id, $reservation_date, $start_time, $end_time);
            $approved_conflict->execute();
            if ($approved_conflict->get_result()->num_rows > 0) {
                $error = "Time conflict! This facility already has an APPROVED reservation during this time.";
            }
            $approved_conflict->close();
        }

        if (!isset($error)) {
            $duration_hours = round($durationHours, 1);
            $total_cost = $duration_hours * $fac['price_per_hour'];
            
            // For Walk-ins, we can approve immediately
            $stmt = $conn->prepare("
                INSERT INTO reservations
                (reservation_type, user_type, id_number, fb_name, user_email, user_phone, host_person, facility_id, reservation_date, start_time, end_time, purpose, num_attendees, duration_hours, total_cost, status)
                VALUES ('WALK_IN', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'APPROVED')
            ");

            $stmt->bind_param(
                "ssssssisssisdd",
                $user_type,
                $id_number,
                $fb_name,
                $user_email,
                $user_phone,
                $host_person,
                $facility_id,
                $reservation_date,
                $start_time,
                $end_time,
                $purpose,
                $num_attendees,
                $duration_hours,
                $total_cost
            );

            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                require_once("../config/audit_helper.php");
                logActivity($conn, 'CREATE', 'RESERVATION', $new_id, "Created WALK-IN reservation for $fb_name ($user_type) at {$fac['name']}", null, [
                    'fb_name' => $fb_name,
                    'user_type' => $user_type,
                    'facility' => $fac['name'],
                    'date' => $reservation_date,
                    'status' => 'APPROVED'
                ]);

                header("Location: index.php?msg=Walk-in reservation created and approved successfully.");
                exit;
            } else {
                $error = "Database error: " . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Walk-in Reservation - CEFI Reservation</title>
    <link rel="stylesheet" href="../style/reservations.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <style>
        .form-container { max-width: 850px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .facility-info-panel { background: #f0f7f2; border-left: 4px solid #013c10; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; display: none; }
        .facility-info-panel.show { display: block; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; }
        .info-label { font-size: 0.7rem; color: #6b7280; font-weight: 600; }
        .info-value { font-weight: 700; color: #013c10; }
        .cost-summary { background: #fffbee; border: 1.5px solid rgba(252, 185, 0, 0.4); border-radius: 10px; padding: 1rem 1.25rem; margin-top: 1rem; display: none; align-items: center; justify-content: space-between; }
        .cost-summary.show { display: flex; }
        .cost-amount { font-size: 1.5rem; font-weight: 800; color: #013c10; }
        .warning-banner { background: #fef3c7; border: 1px solid #d97706; border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.85rem; color: #92400e; display: none; }
        .warning-banner.show { display: block; }
        .type-field { display: none; }
        .type-field.active { display: block; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>🚶 Walk-in Reservation</h1>
        <div class="header-user">Staff/Admin</div>
    </div>

    <div class="container">
    <div class="form-container">
        <h2>Walk-in Registration</h2>
        <p style="color: #6b7280; margin-bottom: 1.5rem;">Create a reservation for a person physically present. Walk-in reservations are approved immediately.</p>
        
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <form method="POST" id="walkinForm">
            <div class="grid-2">
                <div class="input-group">
                    <label for="fb_name">Full Name <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="fb_name" name="fb_name" placeholder="Enter full name" required
                        value="<?= isset($fb_name) ? htmlspecialchars($fb_name) : '' ?>">
                </div>

                <div class="input-group">
                    <label for="user_type">User Type <span style="color:#ef4444;">*</span></label>
                    <select id="user_type" name="user_type" required onchange="toggleTypeFields()">
                        <option value="">Select Type</option>
                        <option value="STUDENT" <?= (isset($user_type) && $user_type == 'STUDENT') ? 'selected' : '' ?>>Student</option>
                        <option value="STAFF" <?= (isset($user_type) && $user_type == 'STAFF') ? 'selected' : '' ?>>Employee/Staff</option>
                        <option value="OUTSIDE" <?= (isset($user_type) && $user_type == 'OUTSIDE') ? 'selected' : '' ?>>Outside User / Customer</option>
                    </select>
                </div>
            </div>

            <div id="student_staff_fields" class="type-field">
                <div class="input-group">
                    <label for="id_number">Identification ID (Student/Employee ID) <span style="color:#ef4444;">*</span></label>
                    <input type="text" id="id_number" name="id_number" placeholder="Enter ID number"
                        value="<?= isset($id_number) ? htmlspecialchars($id_number) : '' ?>">
                </div>
            </div>

            <div id="outside_fields" class="type-field">
                <div class="input-group">
                    <label for="host_person">Host or Contact Person Inside School</label>
                    <input type="text" id="host_person" name="host_person" placeholder="Who is the contact person inside CEFI?"
                        value="<?= isset($host_person) ? htmlspecialchars($host_person) : '' ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label for="user_email">Email Address</label>
                    <input type="email" id="user_email" name="user_email" placeholder="user@email.com"
                        value="<?= isset($user_email) ? htmlspecialchars($user_email) : '' ?>">
                </div>

                <div class="input-group">
                    <label for="user_phone">Phone Number</label>
                    <input type="tel" id="user_phone" name="user_phone" placeholder="09171234567"
                        value="<?= isset($user_phone) ? htmlspecialchars($user_phone) : '' ?>">
                </div>
            </div>

            <hr style="margin: 1.5rem 0; border: 0; border-top: 1px solid #e5e7eb;">

            <div class="input-group">
                <label for="facility_id">Facility</label>
                <select id="facility_id" name="facility_id" required>
                    <option value="">Select Facility</option>
                    <?php 
                    $facilities->data_seek(0);
                    while($row = $facilities->fetch_assoc()): ?>
                        <option value="<?= $row['id'] ?>"
                            data-price="<?= $row['price_per_hour'] ?>"
                            data-open="<?= substr($row['open_time'],0,5) ?>"
                            data-close="<?= substr($row['close_time'],0,5) ?>"
                            data-minhr="<?= $row['min_duration_hours'] ?>"
                            data-maxhr="<?= $row['max_duration_hours'] ?>"
                            data-capacity="<?= $row['capacity'] ?>"
                            <?= (isset($facility_id) && $facility_id == $row['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="facility-info-panel" id="facilityInfoPanel">
                <h4>📋 Facility Rules</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Hours</span>
                        <span class="info-value" id="infoHours">—</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Rate / Hour</span>
                        <span class="info-value" id="infoPrice">—</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Capacity</span>
                        <span class="info-value" id="infoCapacity">—</span>
                    </div>
                </div>
            </div>

            <div class="warning-banner" id="warningBanner"></div>

            <div class="grid-2">
                <div class="input-group">
                    <label for="reservation_date">Reservation Date</label>
                    <input type="date" id="reservation_date" name="reservation_date" required
                        value="<?= isset($reservation_date) ? $reservation_date : date('Y-m-d') ?>">
                </div>

                <div class="input-group">
                    <label for="num_attendees">Number of Attendees</label>
                    <input type="number" id="num_attendees" name="num_attendees" placeholder="How many people?"
                        value="<?= isset($num_attendees) ? $num_attendees : '' ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" required
                        value="<?= isset($start_time) ? $start_time : date('H:i') ?>">
                </div>

                <div class="input-group">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" required
                        value="<?= isset($end_time) ? $end_time : '' ?>">
                </div>
            </div>

            <div class="cost-summary" id="costSummary">
                <div>
                    <span class="cost-label" style="font-weight:600; color:#4a7c59;">Estimated Cost</span>
                    <div id="costBreakdown" style="font-size:0.8rem; color:#6b7280;">0 hrs × ₱0.00/hr</div>
                </div>
                <span class="cost-amount" id="costAmount">₱0.00</span>
            </div>

            <div class="input-group" style="margin-top:1rem;">
                <label for="purpose">Purpose</label>
                <textarea id="purpose" name="purpose" placeholder="Enter purpose of reservation"><?= isset($purpose) ? htmlspecialchars($purpose) : '' ?></textarea>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; align-items: center;">
                <button type="submit" class="btn btn-primary">Create & Approve Walk-in</button>
                <a href="index.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
</div>

<script>
const typeSelect = document.getElementById('user_type');
const studentStaffFields = document.getElementById('student_staff_fields');
const outsideFields = document.getElementById('outside_fields');
const facilitySelect = document.getElementById('facility_id');
const dateInput = document.getElementById('reservation_date');
const startInput = document.getElementById('start_time');
const endInput = document.getElementById('end_time');
const attendeesInput = document.getElementById('num_attendees');
const infoPanel = document.getElementById('facilityInfoPanel');
const warningBanner = document.getElementById('warningBanner');
const costSummary = document.getElementById('costSummary');

function toggleTypeFields() {
    const val = typeSelect.value;
    studentStaffFields.classList.remove('active');
    outsideFields.classList.remove('active');
    
    if (val === 'STUDENT' || val === 'STAFF') {
        studentStaffFields.classList.add('active');
    } else if (val === 'OUTSIDE') {
        outsideFields.classList.add('active');
    }
}

function updateInfoPanel() {
    const opt = facilitySelect.selectedOptions[0];
    if (!opt || !opt.value) {
        infoPanel.classList.remove('show');
        return;
    }
    
    infoPanel.classList.add('show');
    document.getElementById('infoHours').textContent = opt.dataset.open + ' – ' + opt.dataset.close;
    document.getElementById('infoPrice').textContent = parseFloat(opt.dataset.price) > 0 ? '₱' + parseFloat(opt.dataset.price).toFixed(2) : 'Free';
    document.getElementById('infoCapacity').textContent = opt.dataset.capacity + ' persons';
    
    validateForm();
}

function validateForm() {
    const opt = facilitySelect.selectedOptions[0];
    if (!opt || !opt.value) return;

    const data = {
        price: parseFloat(opt.dataset.price) || 0,
        open: opt.dataset.open,
        close: opt.dataset.close,
        minHr: parseInt(opt.dataset.minhr) || 1,
        maxHr: parseInt(opt.dataset.maxhr) || 8,
        capacity: parseInt(opt.dataset.capacity) || 0
    };

    let warnings = [];
    const start = startInput.value;
    const end = endInput.value;
    const attendees = parseInt(attendeesInput.value) || 0;

    if (start && end) {
        if (start < data.open) warnings.push('⚠️ Start time is before facility opening (' + data.open + ').');
        if (end > data.close) warnings.push('⚠️ End time is after facility closing (' + data.close + ').');
        if (start >= end) warnings.push('⚠️ End time must be after start time.');
        
        const startParts = start.split(':');
        const endParts = end.split(':');
        const durationHours = ((parseInt(endParts[0]) * 60 + parseInt(endParts[1])) - (parseInt(startParts[0]) * 60 + parseInt(startParts[1]))) / 60;
        
        if (durationHours > 0) {
            if (durationHours < data.minHr) warnings.push('⚠️ Minimum duration is ' + data.minHr + ' hr(s).');
            if (durationHours > data.maxHr) warnings.push('⚠️ Maximum duration is ' + data.maxHr + ' hr(s).');
            
            if (data.price > 0) {
                const cost = durationHours * data.price;
                document.getElementById('costAmount').textContent = '₱' + cost.toFixed(2);
                document.getElementById('costBreakdown').textContent = durationHours.toFixed(1) + ' hrs × ₱' + data.price.toFixed(2) + '/hr';
                costSummary.classList.add('show');
            } else {
                costSummary.classList.remove('show');
            }
        }
    }

    if (attendees > data.capacity) warnings.push('⚠️ Attendees exceed capacity (' + data.capacity + ').');

    if (warnings.length > 0) {
        warningBanner.innerHTML = warnings.join('<br>');
        warningBanner.classList.add('show');
    } else {
        warningBanner.classList.remove('show');
    }
}

typeSelect.addEventListener('change', toggleTypeFields);
facilitySelect.addEventListener('change', updateInfoPanel);
startInput.addEventListener('change', validateForm);
endInput.addEventListener('change', validateForm);
attendeesInput.addEventListener('input', validateForm);

// Init
toggleTypeFields();
if (facilitySelect.value) updateInfoPanel();
</script>

</body>
</html>
