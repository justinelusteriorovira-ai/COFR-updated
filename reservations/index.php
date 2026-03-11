<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once("../config/db.php");
require_once("../config/audit_helper.php");

// ═══════ Handle Bulk Conflict Rejection (from conflict modal) ═══════
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["conflict_resolve"])) {
    $approve_id = intval($_POST["approve_id"]);
    $reject_reason = trim($_POST["reject_reason"] ?? '');
    $conflict_ids = isset($_POST["conflict_ids"]) ? array_map('intval', explode(',', $_POST["conflict_ids"])) : [];

    if (empty($reject_reason)) {
        $error = "A rejection reason is required when resolving conflicts.";
    } else {
        // 1. Approve the selected reservation
        $conn->prepare("UPDATE reservations SET status = 'APPROVED' WHERE id = ?")->bind_param("i", $approve_id) || true;
        $stmt = $conn->prepare("UPDATE reservations SET status = 'APPROVED' WHERE id = ?");
        $stmt->bind_param("i", $approve_id);
        $stmt->execute();
        logActivity($conn, 'UPDATE', 'RESERVATION', $approve_id, "Approved reservation ID $approve_id (conflict resolved)", null, ['status' => 'APPROVED']);

        // 2. Reject all conflicting reservations with the provided reason
        foreach ($conflict_ids as $cid) {
            $rej = $conn->prepare("UPDATE reservations SET status = 'REJECTED', reject_reason = ? WHERE id = ? AND status = 'PENDING'");
            $rej->bind_param("si", $reject_reason, $cid);
            $rej->execute();
            if ($rej->affected_rows > 0) {
                logActivity($conn, 'UPDATE', 'RESERVATION', $cid, "Rejected reservation ID $cid — Conflict with approved ID $approve_id. Reason: $reject_reason", null, ['status' => 'REJECTED', 'reject_reason' => $reject_reason]);
            }
        }

        $count = count($conflict_ids);
        header("Location: index.php?msg=Reservation approved and $count conflicting reservation(s) rejected.");
        exit;
    }
}

// ═══════ Handle Approve ═══════
if (isset($_GET["approve"])) {
    $id = intval($_GET["approve"]);

    // Get reservation details
    $stmt = $conn->prepare("SELECT r.*, f.name AS facility_name FROM reservations r JOIN facilities f ON r.facility_id = f.id WHERE r.id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$res) {
        $error = "Reservation not found.";
    } else {
        // Check for APPROVED conflicts (hard block)
        $check = $conn->prepare("
            SELECT id FROM reservations 
            WHERE facility_id = ? AND reservation_date = ? AND status = 'APPROVED' AND id != ?
            AND (? < end_time) AND (? > start_time)
        ");
        $check->bind_param("isiss", $res['facility_id'], $res['reservation_date'], $id, $res['start_time'], $res['end_time']);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Cannot approve — this time slot already has an APPROVED reservation.";
            $check->close();
        } else {
            $check->close();

            // Check for PENDING conflicts
            $pcheck = $conn->prepare("
                SELECT r.id, r.fb_name, r.start_time, r.end_time, r.user_email, r.user_phone, r.purpose, f.name AS facility_name
                FROM reservations r 
                JOIN facilities f ON r.facility_id = f.id
                WHERE r.facility_id = ? AND r.reservation_date = ? AND r.status = 'PENDING' AND r.id != ?
                AND (? < r.end_time) AND (? > r.start_time)
            ");
            $pcheck->bind_param("isiss", $res['facility_id'], $res['reservation_date'], $id, $res['start_time'], $res['end_time']);
            $pcheck->execute();
            $pending_conflicts = $pcheck->get_result();

            if ($pending_conflicts->num_rows > 0) {
                // Store conflict data for the modal
                $conflict_list = [];
                while ($pc = $pending_conflicts->fetch_assoc()) {
                    $conflict_list[] = $pc;
                }
                $show_conflict_modal = true;
                $approve_target = $res;
            } else {
                // No conflicts — approve directly
                $upd = $conn->prepare("UPDATE reservations SET status = 'APPROVED' WHERE id = ?");
                $upd->bind_param("i", $id);
                $upd->execute();
                logActivity($conn, 'UPDATE', 'RESERVATION', $id, "Approved reservation ID $id", null, ['status' => 'APPROVED']);
                header("Location: index.php?msg=Reservation approved successfully.");
                exit;
            }
            $pcheck->close();
        }
    }
}

// ═══════ Handle Reject (with reason) ═══════
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["reject_id"])) {
    $id = intval($_POST["reject_id"]);
    $reject_reason = trim($_POST["reject_reason"] ?? '');

    if (empty($reject_reason)) {
        $error = "A rejection reason is required.";
    } else {
        $stmt = $conn->prepare("UPDATE reservations SET status = 'REJECTED', reject_reason = ? WHERE id = ?");
        $stmt->bind_param("si", $reject_reason, $id);
        $stmt->execute();
        logActivity($conn, 'UPDATE', 'RESERVATION', $id, "Rejected reservation ID $id. Reason: $reject_reason", null, ['status' => 'REJECTED', 'reject_reason' => $reject_reason]);
        header("Location: index.php?msg=Reservation rejected.");
        exit;
    }
} elseif (isset($_GET["reject"])) {
    // Legacy GET reject — redirect to show modal instead
    $show_reject_modal_id = intval($_GET["reject"]);
    $rr = $conn->prepare("SELECT r.fb_name, f.name AS facility_name FROM reservations r JOIN facilities f ON r.facility_id = f.id WHERE r.id = ?");
    $rr->bind_param("i", $show_reject_modal_id);
    $rr->execute();
    $reject_target = $rr->get_result()->fetch_assoc();
    $rr->close();
}

// Auto-expire reservations past verification deadline
$conn->query("
    UPDATE reservations
    SET status = 'EXPIRED'
    WHERE status = 'PENDING'
    AND verification_deadline IS NOT NULL
    AND verification_deadline < NOW()
");

// ═══════ Detect Conflicts for Badge Display ═══════
// Get all pending reservations that have overlapping pending siblings
$conflict_map = [];
$cq = $conn->query("
    SELECT r1.id AS res_id, COUNT(r2.id) AS conflict_count
    FROM reservations r1
    JOIN reservations r2 ON r1.facility_id = r2.facility_id 
        AND r1.reservation_date = r2.reservation_date
        AND r1.id != r2.id
        AND r2.status IN ('PENDING', 'APPROVED')
        AND (r1.start_time < r2.end_time) AND (r1.end_time > r2.start_time)
    WHERE r1.status = 'PENDING'
    GROUP BY r1.id
");
while ($cr = $cq->fetch_assoc()) {
    $conflict_map[$cr['res_id']] = $cr['conflict_count'];
}

// Fetch reservations with facility name
$result = $conn->query("
    SELECT r.*, f.name AS facility_name
    FROM reservations r
    JOIN facilities f ON r.facility_id = f.id
    ORDER BY r.created_at DESC
");

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - CEFI Reservation</title>
    <link rel="stylesheet" href="../style/reservations.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <style>
        .status-expired, .status-cancelled {
            background: #f3f4f6;
            color: #6b7280;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-on_hold, .status-on-hold {
            background: #ede9fe;
            color: #6d28d9;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-pending_verification, .status-pending-verification {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .status-waitlisted {
            background: #fef3c7;
            color: #b45309;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .cost-cell { font-weight: 600; color: #013c10; }
        .deadline-badge {
            font-size: 0.7rem;
            display: block;
            margin-top: 0.25rem;
            color: #d97706;
            font-weight: 500;
        }
        .deadline-badge.expired { color: #ef4444; }

        /* Cancel button */
        .cancel-btn {
            background: #fef3c7;
            color: #b45309;
            padding: 0.25rem 0.6rem;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }
        .cancel-btn:hover {
            background: #fde68a;
            color: #92400e;
        }

        /* Cancel reason in status column */
        .cancel-reason-preview {
            font-size: 0.7rem;
            color: #6b7280;
            display: block;
            margin-top: 0.2rem;
            font-style: italic;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0;
            width: 100vw; height: 100vh;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .modal-overlay.active {
            opacity: 1;
            pointer-events: auto;
        }
        .modal-box {
            background: #fff;
            border-radius: 16px;
            padding: 2rem;
            max-width: 480px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
        }
        .modal-box h3 {
            margin-bottom: 0.5rem;
            font-size: 1.15rem;
        }
        .modal-box p {
            color: #6b7280;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        .modal-box textarea {
            width: 100%;
            min-height: 80px;
            padding: 0.75rem;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.85rem;
            resize: vertical;
            margin-bottom: 1rem;
        }
        .modal-box textarea:focus {
            outline: none;
            border-color: #013c10;
            box-shadow: 0 0 0 3px rgba(1, 60, 16, 0.1);
        }
        .modal-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 0.6rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .modal-btn.secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }
        .modal-btn.secondary:hover { background: #e5e7eb; }
        .modal-btn.danger {
            background: #dc2626;
            color: #fff;
        }
        .modal-btn.danger:hover { background: #b91c1c; }
        .modal-btn.warning {
            background: #f59e0b;
            color: #fff;
        }
        .modal-btn.warning:hover { background: #d97706; }
        .modal-btn.success {
            background: #013c10;
            color: #fff;
        }
        .modal-btn.success:hover { background: #015a18; }

        /* Conflict badge */
        .conflict-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            background: #fef3c7;
            color: #92400e;
            padding: 0.15rem 0.5rem;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-top: 0.2rem;
        }
        .conflict-badge.active {
            background: #fee2e2;
            color: #dc2626;
            animation: pulse-conflict 2s ease-in-out infinite;
        }
        @keyframes pulse-conflict {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        /* Conflict modal extras */
        .conflict-list { margin: 1rem 0; }
        .conflict-item {
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            font-size: 0.83rem;
        }
        .conflict-item .ci-name { font-weight: 700; color: #1a1a1a; }
        .conflict-item .ci-meta { color: #6b7280; font-size: 0.75rem; margin-top: 0.15rem; }
        .approve-target-card {
            background: #dcfce7;
            border: 1px solid #86efac;
            border-radius: 8px;
            padding: 0.75rem;
            margin-bottom: 1rem;
            font-size: 0.83rem;
        }
        .approve-target-card .at-name { font-weight: 700; color: #166534; }
        .approve-target-card .at-meta { color: #6b7280; font-size: 0.75rem; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>Reservations</h1>
        <div class="header-user">Admin</div>
    </div>

    <div class="container">
    <h2>All Reservations</h2>
    
    <?php if (isset($error)): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>
    
    <a href="create.php" class="add-btn">+ Create Reservation</a>
    
    <?php if (isset($_GET['msg'])): ?>
        <p class="success"><?= htmlspecialchars($_GET['msg']) ?></p>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <p class="error"><?= htmlspecialchars($_GET['error']) ?></p>
    <?php endif; ?>
    
    <table>
        <tr>
            <th>Name</th>
            <th>Facility</th>
            <th>Purpose</th>
            <th>Date</th>
            <th>Time</th>
            <th>Cost</th>
            <th>Status</th>
            <th>Action</th>
        </tr>

        <?php while($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars($row["fb_name"]) ?></td>
            <td><?= htmlspecialchars($row["facility_name"]) ?></td>
            <td title="<?= htmlspecialchars($row['purpose'] ?? '') ?>">
                <span style="font-size:0.8rem;color:#374151;"><?= htmlspecialchars(mb_strimwidth($row['purpose'] ?? '—', 0, 40, '...')) ?></span>
            </td>
            <td><?= $row["reservation_date"] ?></td>
            <td>
                <?= substr($row["start_time"],0,5) ?> - <?= substr($row["end_time"],0,5) ?>
                <?php if ($row["duration_hours"]): ?>
                    <br><small style="color:#6b7280;"><?= $row["duration_hours"] ?> hrs</small>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($row["total_cost"] > 0): ?>
                    <span class="cost-cell">₱<?= number_format($row["total_cost"], 2) ?></span>
                <?php else: ?>
                    <span style="color:#9ca3af;">Free</span>
                <?php endif; ?>
            </td>
            <td>
                <?php
                    $status_class = 'status-' . strtolower(str_replace('_', '-', $row["status"]));
                    echo "<span class=\"$status_class\">{$row["status"]}</span>";
                    
                    // Show conflict badge for PENDING reservations
                    if ($row['status'] === 'PENDING' && isset($conflict_map[$row['id']])) {
                        $cc = $conflict_map[$row['id']];
                        echo '<span class="conflict-badge active">⚠️ ' . $cc . ' conflict' . ($cc > 1 ? 's' : '') . '</span>';
                    }
                    
                    // Show verification deadline
                    if ($row['verification_deadline'] && in_array($row['status'], ['PENDING', 'PENDING_VERIFICATION'])) {
                        $deadline = new DateTime($row['verification_deadline']);
                        $now = new DateTime();
                        $remaining = $now->diff($deadline);
                        if ($now > $deadline) {
                            echo '<span class="deadline-badge expired">⏰ Expired</span>';
                        } else {
                            echo '<span class="deadline-badge">⏰ ' . $remaining->h . 'h ' . $remaining->i . 'm left</span>';
                        }
                    }
                    
                    // Show reject reason
                    if ($row['status'] === 'REJECTED' && !empty($row['reject_reason'])) {
                        echo '<span class="cancel-reason-preview" title="' . htmlspecialchars($row['reject_reason']) . '">Reason: ' . htmlspecialchars(substr($row['reject_reason'], 0, 30)) . (strlen($row['reject_reason']) > 30 ? '...' : '') . '</span>';
                    }
                    
                    // Show cancel reason
                    if ($row['status'] === 'CANCELLED' && !empty($row['cancel_reason'])) {
                        echo '<span class="cancel-reason-preview" title="' . htmlspecialchars($row['cancel_reason']) . '">Reason: ' . htmlspecialchars(substr($row['cancel_reason'], 0, 30)) . (strlen($row['cancel_reason']) > 30 ? '...' : '') . '</span>';
                    }
                ?>
            </td>
            <td>
                <div class="action-links">
                    <?php if ($row["status"] == "PENDING"): ?>
                        <a href="?approve=<?= $row["id"] ?>" class="approve">Approve</a>
                        <a href="javascript:void(0)" class="reject" onclick="showRejectModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fb_name'], ENT_QUOTES) ?>')" >Reject</a>
                    <?php elseif ($row["status"] == "APPROVED"): ?>
                        <a href="print.php?id=<?= $row["id"] ?>" class="print">Print</a>
                    <?php else: ?>
                        <span class="no-action">—</span>
                    <?php endif; ?>
                    
                    <?php if (in_array($row["status"], ['PENDING', 'APPROVED', 'PENDING_VERIFICATION', 'ON_HOLD'])): ?>
                        <button type="button" class="cancel-btn" onclick="showCancelModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fb_name'], ENT_QUOTES) ?>', '<?= htmlspecialchars($row['facility_name'], ENT_QUOTES) ?>')">Cancel</button>
                    <?php endif; ?>
                    
                    <a href="edit.php?id=<?= $row["id"] ?>">Edit</a>
                    <a href="javascript:void(0)" class="reject" onclick="showDeleteModal(<?= $row['id'] ?>, '<?= htmlspecialchars($row['fb_name'], ENT_QUOTES) ?>')">Delete</a>
                </div>
            </td>
        </tr>
        <?php endwhile; ?>
    </table>
    </div>
</div>

<!-- ═══════ Reject Reason Modal ═══════ -->
<div class="modal-overlay" id="rejectModal">
    <div class="modal-box">
        <h3 style="color: #dc2626;">❌ Reject Reservation</h3>
        <p id="rejectModalText">Reject this reservation?</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="reject_id" id="rejectIdInput" value="">
            <textarea name="reject_reason" placeholder="Please provide a reason for rejection (required)..." required></textarea>
            <div class="modal-actions">
                <button type="button" class="modal-btn secondary" onclick="closeRejectModal()">Go Back</button>
                <button type="submit" class="modal-btn danger">Reject Reservation</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════ Cancel Confirmation Modal ═══════ -->
<div class="modal-overlay" id="cancelModal">
    <div class="modal-box">
        <h3 style="color: #b45309;">⚠️ Cancel Reservation</h3>
        <p id="cancelModalText">Are you sure you want to cancel this reservation?</p>
        <form id="cancelForm" method="POST" action="">
            <textarea name="cancel_reason" id="cancelReasonInput" placeholder="Please provide a reason for cancellation..." required></textarea>
            <div class="modal-actions">
                <button type="button" class="modal-btn secondary" onclick="closeCancelModal()">Go Back</button>
                <button type="submit" class="modal-btn warning">Cancel Reservation</button>
            </div>
        </form>
    </div>
</div>

<!-- ═══════ Delete Confirmation Modal ═══════ -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="text-align:center;">
        <h3 style="color:#991b1b;">⚠️ Delete Reservation</h3>
        <p id="deleteModalText">Are you sure?</p>
        <div class="modal-actions" style="justify-content:center;">
            <button type="button" class="modal-btn secondary" onclick="closeDeleteModal()">Cancel</button>
            <a id="deleteConfirmBtn" href="#" class="modal-btn danger" style="text-decoration:none;">Delete</a>
        </div>
    </div>
</div>

<!-- ═══════ Conflict Resolution Modal (rendered by PHP) ═══════ -->
<?php if (isset($show_conflict_modal) && $show_conflict_modal): ?>
<div class="modal-overlay active" id="conflictModal">
    <div class="modal-box" style="max-width:560px;">
        <h3 style="color: #b45309;">⚠️ Conflict Detected — Resolve Before Approving</h3>
        <p>Approving this reservation will affect <strong><?= count($conflict_list) ?></strong> conflicting pending reservation(s). You must provide a rejection reason for them.</p>
        
        <div class="approve-target-card">
            <div class="at-name">✅ Approving: <?= htmlspecialchars($approve_target['fb_name']) ?></div>
            <div class="at-meta"><?= htmlspecialchars($approve_target['facility_name']) ?> · <?= $approve_target['reservation_date'] ?> · <?= substr($approve_target['start_time'],0,5) ?>–<?= substr($approve_target['end_time'],0,5) ?></div>
        </div>

        <div class="conflict-list">
            <strong style="font-size:0.8rem;color:#92400e;">❌ Will be rejected:</strong>
            <?php foreach ($conflict_list as $cl): ?>
            <div class="conflict-item">
                <div class="ci-name"><?= htmlspecialchars($cl['fb_name']) ?></div>
                <div class="ci-meta">
                    <?= htmlspecialchars($cl['facility_name']) ?> · <?= substr($cl['start_time'],0,5) ?>–<?= substr($cl['end_time'],0,5) ?>
                    <?php if (!empty($cl['user_email'])): ?> · 📧 <?= htmlspecialchars($cl['user_email']) ?><?php endif; ?>
                    <?php if (!empty($cl['user_phone'])): ?> · 📞 <?= htmlspecialchars($cl['user_phone']) ?><?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <form method="POST" action="index.php">
            <input type="hidden" name="conflict_resolve" value="1">
            <input type="hidden" name="approve_id" value="<?= $approve_target['id'] ?>">
            <input type="hidden" name="conflict_ids" value="<?= implode(',', array_column($conflict_list, 'id')) ?>">
            <textarea name="reject_reason" placeholder="Rejection reason for conflicting reservations (required)..." required></textarea>
            <div class="modal-actions">
                <a href="index.php" class="modal-btn secondary" style="text-decoration:none;">Go Back</a>
                <button type="submit" class="modal-btn success">Approve & Reject Conflicts</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ═══════ Reject Modal (auto-open from GET) ═══════ -->
<?php if (isset($show_reject_modal_id) && $reject_target): ?>
<div class="modal-overlay active" id="autoRejectModal">
    <div class="modal-box">
        <h3 style="color: #dc2626;">❌ Reject Reservation</h3>
        <p>Reject the reservation by <strong>"<?= htmlspecialchars($reject_target['fb_name']) ?>"</strong> at <strong><?= htmlspecialchars($reject_target['facility_name']) ?></strong>?</p>
        <form method="POST" action="index.php">
            <input type="hidden" name="reject_id" value="<?= $show_reject_modal_id ?>">
            <textarea name="reject_reason" placeholder="Please provide a reason for rejection (required)..." required></textarea>
            <div class="modal-actions">
                <a href="index.php" class="modal-btn secondary" style="text-decoration:none;">Go Back</a>
                <button type="submit" class="modal-btn danger">Reject Reservation</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="footer">
    © 2026 CEFI ONLINE FACILITY RESERVATION. All rights reserved. | Calayan Educational Foundation Inc., Philippines | Contact: info@cefi.website
</div>

<script>
// ── Reject Modal ──
function showRejectModal(id, name) {
    const modal = document.getElementById('rejectModal');
    document.getElementById('rejectModalText').innerHTML = 'Reject the reservation by <strong>"' + name + '"</strong>?';
    document.getElementById('rejectIdInput').value = id;
    modal.classList.add('active');
}
function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
}
document.getElementById('rejectModal').addEventListener('click', function(e) {
    if (e.target === this) closeRejectModal();
});

// ── Cancel Modal ──
function showCancelModal(id, name, facility) {
    const modal = document.getElementById('cancelModal');
    document.getElementById('cancelModalText').innerHTML = 
        'Cancel the reservation by <strong>"' + name + '"</strong> at <strong>' + facility + '</strong>?';
    document.getElementById('cancelForm').action = 'cancel.php?id=' + id;
    document.getElementById('cancelReasonInput').value = '';
    modal.classList.add('active');
}
function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
}
document.getElementById('cancelModal').addEventListener('click', function(e) {
    if (e.target === this) closeCancelModal();
});

// ── Delete Modal ──
function showDeleteModal(id, name) {
    const modal = document.getElementById('deleteModal');
    document.getElementById('deleteModalText').innerHTML = 'Are you sure you want to permanently delete the reservation by <strong>"' + name + '"</strong>?';
    document.getElementById('deleteConfirmBtn').href = 'delete.php?id=' + id;
    modal.classList.add('active');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('active');
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});
</script>

</body>
</html>

