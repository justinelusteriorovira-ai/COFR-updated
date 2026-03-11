<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once("../config/db.php");

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET["id"];

// Fetch the reservation with facility details
$stmt = $conn->prepare("
    SELECT r.*, f.name AS facility_name, f.price_per_hour, f.open_time, f.close_time, 
           f.advance_days_required, f.min_duration_hours, f.max_duration_hours, f.capacity
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    WHERE r.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$reservation = $result->fetch_assoc();

if (!$reservation) {
    header("Location: index.php");
    exit;
}

// Fetch facilities for dropdown
$facilities = $conn->query("SELECT id, name FROM facilities WHERE status = 'AVAILABLE'");

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $fb_name = trim($_POST["fb_name"]);
    $fb_user_id = trim($_POST["fb_user_id"]);
    $facility_id = $_POST["facility_id"];
    $reservation_date = $_POST["reservation_date"];
    $start_time = $_POST["start_time"];
    $end_time = $_POST["end_time"];
    $purpose = trim($_POST["purpose"]);
    $status = $_POST["status"];
    $reject_reason = trim($_POST["reject_reason"] ?? '');
    $admin_notes = trim($_POST["admin_notes"] ?? '');
    $num_attendees = isset($_POST["num_attendees"]) ? (int)$_POST["num_attendees"] : null;

    $valid_statuses = ['PENDING', 'APPROVED', 'REJECTED', 'PENDING_VERIFICATION', 'EXPIRED', 'CANCELLED', 'ON_HOLD', 'WAITLISTED'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'PENDING';
    }

    // Require reject reason when rejecting
    if ($status === 'REJECTED' && empty($reject_reason)) {
        $error = "A rejection reason is required when rejecting a reservation.";
    }

    if (
        empty($fb_name) || empty($fb_user_id) ||
        empty($facility_id) || empty($reservation_date) ||
        empty($start_time) || empty($end_time)
    ) {
        $error = "All required fields must be filled.";
    }

    if (!isset($error)) {
        // Calculate duration and cost
        $startDT = new DateTime($start_time);
        $endDT = new DateTime($end_time);
        $duration_hours = round(($endDT->getTimestamp() - $startDT->getTimestamp()) / 3600, 1);
        $total_cost = $duration_hours * $reservation['price_per_hour'];

        // Conflict check if approving
        if ($status === 'APPROVED') {
            $conflict_stmt = $conn->prepare("
                SELECT id FROM reservations
                WHERE facility_id = ?
                AND reservation_date = ?
                AND status = 'APPROVED'
                AND id != ?
                AND (? < end_time) AND (? > start_time)
            ");
            $conflict_stmt->bind_param("issss", $facility_id, $reservation_date, $id, $start_time, $end_time);
            $conflict_stmt->execute();
            $conflict_stmt->store_result();
            
            if ($conflict_stmt->num_rows > 0) {
                $error = "Cannot approve — time conflict with another approved reservation.";
            }
            $conflict_stmt->close();
        }
    }

    if (!isset($error)) {
        $stmt = $conn->prepare("
            UPDATE reservations 
            SET fb_user_id = ?, fb_name = ?, facility_id = ?, 
                reservation_date = ?, start_time = ?, end_time = ?, 
                purpose = ?, status = ?, reject_reason = ?, admin_notes = ?,
                num_attendees = ?, duration_hours = ?, total_cost = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ssisssssssiddi",
            $fb_user_id,
            $fb_name,
            $facility_id,
            $reservation_date,
            $start_time,
            $end_time,
            $purpose,
            $status,
            $reject_reason,
            $admin_notes,
            $num_attendees,
            $duration_hours,
            $total_cost,
            $id
        );

        if ($stmt->execute()) {
            require_once("../config/audit_helper.php");
            $actionDetail = "Updated reservation for $fb_name (Status: $status)";
            if ($status === 'REJECTED') $actionDetail .= " — Reason: $reject_reason";
            
            logActivity($conn, 'UPDATE', 'RESERVATION', $id, $actionDetail, $reservation, [
                'fb_name' => $fb_name,
                'status' => $status,
                'reject_reason' => $reject_reason,
                'admin_notes' => $admin_notes
            ]);

            header("Location: index.php");
            exit;
        } else {
            $error = "Database error: " . $conn->error;
        }
    }
}

// Check verification deadline
$verificationExpired = false;
if ($reservation['verification_deadline']) {
    $deadline = new DateTime($reservation['verification_deadline']);
    $now = new DateTime();
    if ($now > $deadline && $reservation['status'] === 'PENDING') {
        $verificationExpired = true;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reservation - CEFI Reservation</title>
    <link rel="stylesheet" href="../style/reservations.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <style>
        .form-container { max-width: 850px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        
        .reservation-meta {
            background: #f0f7f2;
            border: 1px solid rgba(1, 60, 16, 0.12);
            border-radius: 10px;
            padding: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .reservation-meta h4 { color: #013c10; margin-bottom: 0.75rem; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.5rem; }
        .meta-item { font-size: 0.85rem; }
        .meta-item span:first-child { color: #6b7280; }
        .meta-item span:last-child { font-weight: 600; color: #1a1a1a; }
        
        .deadline-warning {
            background: #fee2e2;
            border: 1px solid #ef4444;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            color: #991b1b;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .reject-reason-group { display: none; }
        .reject-reason-group.show { display: flex; }
        
        .admin-section {
            background: #fefce8;
            border: 1px solid rgba(252, 185, 0, 0.3);
            border-radius: 10px;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        .admin-section h4 { color: #92400e; margin-bottom: 0.75rem; font-size: 0.95rem; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>Edit Reservation</h1>
        <div class="header-user">Admin</div>
    </div>

    <div class="container">
    <div class="form-container">
        <h2>Edit Reservation #<?= $id ?></h2>
        
        <?php if ($verificationExpired): ?>
            <div class="deadline-warning">
                ⚠️ Verification deadline has passed! This reservation was not verified within 24 hours.
                Consider setting status to EXPIRED.
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <p class="error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>
        
        <!-- Reservation Info Summary -->
        <div class="reservation-meta">
            <h4>📋 Reservation Details</h4>
            <div class="meta-grid">
                <div class="meta-item"><span>Facility: </span><span><?= htmlspecialchars($reservation['facility_name']) ?></span></div>
                <div class="meta-item"><span>Created: </span><span><?= $reservation['created_at'] ?></span></div>
                <?php if ($reservation['duration_hours']): ?>
                    <div class="meta-item"><span>Duration: </span><span><?= $reservation['duration_hours'] ?> hrs</span></div>
                <?php endif; ?>
                <?php if ($reservation['total_cost'] > 0): ?>
                    <div class="meta-item"><span>Est. Cost: </span><span>₱<?= number_format($reservation['total_cost'], 2) ?></span></div>
                <?php endif; ?>
                <?php if ($reservation['verification_deadline']): ?>
                    <div class="meta-item"><span>Verify By: </span><span><?= $reservation['verification_deadline'] ?></span></div>
                <?php endif; ?>
                <?php if ($reservation['num_attendees']): ?>
                    <div class="meta-item"><span>Attendees: </span><span><?= $reservation['num_attendees'] ?></span></div>
                <?php endif; ?>
            </div>
        </div>
        
        <form method="POST">
            <div class="grid-2">
                <div class="input-group">
                    <label for="fb_name">Facebook Name</label>
                    <input type="text" id="fb_name" name="fb_name" value="<?= htmlspecialchars($reservation['fb_name']) ?>" required>
                </div>

                <div class="input-group">
                    <label for="fb_user_id">Facebook User ID</label>
                    <input type="text" id="fb_user_id" name="fb_user_id" value="<?= htmlspecialchars($reservation['fb_user_id']) ?>" required>
                </div>
            </div>

            <div class="input-group">
                <label for="facility_id">Facility</label>
                <select id="facility_id" name="facility_id" required>
                    <option value="">Select Facility</option>
                    <?php 
                    $facilities->data_seek(0);
                    while($row = $facilities->fetch_assoc()): 
                    ?>
                        <option value="<?= $row['id'] ?>" <?= $reservation['facility_id'] == $row['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($row['name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label for="reservation_date">Date</label>
                    <input type="date" id="reservation_date" name="reservation_date" value="<?= $reservation['reservation_date'] ?>" required>
                </div>

                <div class="input-group">
                    <label for="num_attendees">Attendees</label>
                    <input type="number" id="num_attendees" name="num_attendees" value="<?= $reservation['num_attendees'] ?>">
                </div>
            </div>

            <div class="grid-2">
                <div class="input-group">
                    <label for="start_time">Start Time</label>
                    <input type="time" id="start_time" name="start_time" value="<?= substr($reservation['start_time'],0,5) ?>" required>
                </div>

                <div class="input-group">
                    <label for="end_time">End Time</label>
                    <input type="time" id="end_time" name="end_time" value="<?= substr($reservation['end_time'],0,5) ?>" required>
                </div>
            </div>

            <div class="input-group">
                <label for="purpose">Purpose</label>
                <textarea id="purpose" name="purpose"><?= htmlspecialchars($reservation['purpose']) ?></textarea>
            </div>

            <!-- Admin Action Section -->
            <div class="admin-section">
                <h4>🔑 Admin Actions</h4>

                <div class="input-group">
                    <label for="status">Status</label>
                    <select id="status" name="status" onchange="toggleRejectReason()">
                        <?php 
                        $allStatuses = ['PENDING', 'APPROVED', 'REJECTED', 'PENDING_VERIFICATION', 'EXPIRED', 'CANCELLED', 'ON_HOLD', 'WAITLISTED'];
                        foreach ($allStatuses as $s):
                        ?>
                            <option value="<?= $s ?>" <?= $reservation['status'] == $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="input-group reject-reason-group" id="rejectReasonGroup">
                    <label for="reject_reason">Rejection Reason <span style="color:#ef4444;">*</span></label>
                    <textarea id="reject_reason" name="reject_reason" placeholder="Explain why this reservation is being rejected..."><?= htmlspecialchars($reservation['reject_reason'] ?? '') ?></textarea>
                </div>

                <div class="input-group" style="margin-top: 0.75rem;">
                    <label for="admin_notes">Admin Notes (internal)</label>
                    <textarea id="admin_notes" name="admin_notes" placeholder="Internal notes visible only to admins..."><?= htmlspecialchars($reservation['admin_notes'] ?? '') ?></textarea>
                </div>
            </div>

            <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">Update Reservation</button>
                <a href="index.php" class="btn btn-secondary">Back</a>
                <?php if ($reservation['status'] == 'APPROVED'): ?>
                    <a href="print.php?id=<?= $reservation['id'] ?>" class="btn btn-secondary" style="background:#013c10;color:#fff;border-color:#013c10;">🖨️ Print Slip</a>
                <?php endif; ?>
                <?php if (in_array($reservation['status'], ['PENDING', 'APPROVED', 'PENDING_VERIFICATION', 'ON_HOLD'])): ?>
                    <a href="javascript:void(0)" class="btn btn-secondary" style="background:#f59e0b;color:#fff;border-color:#f59e0b;" onclick="if(confirm('Are you sure you want to cancel this reservation?')){let r=prompt('Please provide a cancellation reason:');if(r){window.location='cancel.php?id=<?= $reservation['id'] ?>&reason='+encodeURIComponent(r);}}">❌ Cancel Reservation</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
</div>

<div class="footer">
    © 2026 CEFI ONLINE FACILITY RESERVATION. All rights reserved. | Calayan Educational Foundation Inc., Philippines | Contact: info@cefi.website
</div>

<script>
function toggleRejectReason() {
    const status = document.getElementById('status').value;
    const group = document.getElementById('rejectReasonGroup');
    if (status === 'REJECTED') {
        group.classList.add('show');
    } else {
        group.classList.remove('show');
    }
}
// Initialize on load
toggleRejectReason();
</script>

</body>
</html>
