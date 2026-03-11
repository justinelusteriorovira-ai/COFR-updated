<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once("../config/db.php");

// Check if ID is provided
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET["id"];

// Fetch the reservation with full facility details
$stmt = $conn->prepare("
    SELECT r.*, f.name AS facility_name, f.description AS facility_description, 
           f.capacity AS facility_capacity, f.price_per_hour, f.open_time, f.close_time,
           f.image AS facility_image
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

// Check if reservation is approved
if ($reservation["status"] != "APPROVED") {
    header("Location: index.php?error=Only approved reservations can be printed.");
    exit;
}

// Format date and time
$formatted_date = date("F j, Y", strtotime($reservation["reservation_date"]));
$formatted_created_at = date("F j, Y h:i A", strtotime($reservation["created_at"]));
$day_of_week = date("l", strtotime($reservation["reservation_date"]));

// Calculate duration if not stored
$duration = $reservation['duration_hours'];
if (!$duration && $reservation['start_time'] && $reservation['end_time']) {
    $startDT = new DateTime($reservation['start_time']);
    $endDT = new DateTime($reservation['end_time']);
    $duration = round(($endDT->getTimestamp() - $startDT->getTimestamp()) / 3600, 1);
}

$total_cost = $reservation['total_cost'] ?: ($duration * ($reservation['price_per_hour'] ?? 0));

// Format times
$start_formatted = date("g:i A", strtotime($reservation['start_time']));
$end_formatted = date("g:i A", strtotime($reservation['end_time']));

// Document number
$doc_number = 'RES-' . str_pad($reservation["id"], 6, '0', STR_PAD_LEFT);

// QR Code data
$qr_data = urlencode("CEFI Reservation | ID: $doc_number | Facility: {$reservation['facility_name']} | Date: $formatted_date | Time: $start_formatted - $end_formatted | Status: APPROVED");
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=$qr_data";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservation Slip - <?= $doc_number ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* ===== Print Styles ===== */
        @media print {
            body {
                font-size: 11pt;
                background: #fff !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print { display: none !important; }
            .slip-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: none !important;
                border: none !important;
            }
            @page {
                margin: 15mm;
                size: A4;
            }
        }

        /* ===== Screen Styles ===== */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', 'Segoe UI', Arial, sans-serif;
            background: #f0f2f0;
            color: #1a1a1a;
            padding: 2rem;
            line-height: 1.5;
        }

        .slip-container {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0,0,0,0.12);
            border: 1px solid rgba(1, 60, 16, 0.1);
        }

        /* Header Bar */
        .slip-header {
            background: linear-gradient(135deg, #013c10 0%, #015a18 100%);
            color: #ffffff;
            padding: 1.75rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 4px solid #fcb900;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-left img {
            height: 60px;
            width: 60px;
            border-radius: 8px;
            background: rgba(255,255,255,0.15);
            padding: 4px;
        }

        .header-text h1 {
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }

        .header-text p {
            font-size: 0.75rem;
            opacity: 0.85;
            margin-top: 2px;
        }

        .header-right {
            text-align: right;
        }

        .header-right .doc-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            opacity: 0.7;
        }

        .header-right .doc-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: #fcb900;
            letter-spacing: 0.05em;
        }

        /* Title Banner */
        .title-banner {
            background: linear-gradient(90deg, rgba(252,185,0,0.12), rgba(252,185,0,0.03));
            padding: 1rem 2.5rem;
            border-bottom: 1px solid rgba(1,60,16,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .title-banner h2 {
            font-size: 1.15rem;
            font-weight: 700;
            color: #013c10;
            letter-spacing: 0.04em;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.4rem 1rem;
            background: #16a34a;
            color: #ffffff;
            border-radius: 20px;
            font-weight: 700;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }

        .status-badge::before {
            content: '✓';
            font-size: 0.85rem;
        }

        /* Main Content */
        .slip-body {
            padding: 2rem 2.5rem;
        }

        /* Info Grid */
        .info-section {
            margin-bottom: 1.75rem;
        }

        .section-label {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            color: #013c10;
            margin-bottom: 0.75rem;
            padding-bottom: 0.35rem;
            border-bottom: 2px solid #fcb900;
            display: inline-block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem 2rem;
        }

        .info-grid.three-col {
            grid-template-columns: 1fr 1fr 1fr;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.15rem;
        }

        .info-item .label {
            font-size: 0.7rem;
            color: #6b7280;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .info-item .value {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .info-item .value.highlight {
            color: #013c10;
            font-size: 1rem;
        }

        .info-item .value.cost {
            color: #013c10;
            font-size: 1.15rem;
            font-weight: 700;
        }

        /* QR + Cost Section */
        .qr-cost-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: #f7faf8;
            border: 1px solid rgba(1, 60, 16, 0.1);
            border-radius: 12px;
            padding: 1.5rem;
            margin: 1.5rem 0;
        }

        .cost-breakdown {
            flex: 1;
        }

        .cost-line {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.35rem 0;
            font-size: 0.85rem;
            color: #374151;
        }

        .cost-line.total {
            border-top: 2px solid #013c10;
            margin-top: 0.5rem;
            padding-top: 0.65rem;
            font-weight: 700;
            font-size: 1rem;
            color: #013c10;
        }

        .qr-section {
            text-align: center;
            margin-left: 2rem;
            flex-shrink: 0;
        }

        .qr-section img {
            border: 3px solid #013c10;
            border-radius: 8px;
            padding: 4px;
            background: #fff;
        }

        .qr-section p {
            font-size: 0.6rem;
            color: #6b7280;
            margin-top: 0.35rem;
            font-weight: 500;
        }

        /* Facility Description */
        .facility-desc {
            background: #f9fafb;
            border: 1px solid rgba(0,0,0,0.06);
            border-radius: 8px;
            padding: 1rem 1.25rem;
            font-size: 0.83rem;
            color: #4b5563;
            line-height: 1.6;
            margin-top: 0.5rem;
        }

        /* Terms */
        .terms-section {
            margin-top: 1.5rem;
            padding-top: 1rem;
            border-top: 1px dashed rgba(1, 60, 16, 0.15);
        }

        .terms-section h4 {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .terms-section ul {
            list-style: none;
            padding: 0;
        }

        .terms-section li {
            font-size: 0.72rem;
            color: #6b7280;
            padding: 0.2rem 0;
            padding-left: 1rem;
            position: relative;
        }

        .terms-section li::before {
            content: '•';
            position: absolute;
            left: 0;
            color: #013c10;
            font-weight: 700;
        }

        /* Signature */
        .signature-area {
            display: flex;
            justify-content: space-between;
            margin-top: 2.5rem;
            gap: 2rem;
        }

        .signature-block {
            flex: 1;
            text-align: center;
        }

        .signature-line {
            border-top: 1px solid #374151;
            margin-top: 3rem;
            padding-top: 0.5rem;
        }

        .signature-line .sig-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1a1a1a;
        }

        .signature-line .sig-title {
            font-size: 0.7rem;
            color: #6b7280;
        }

        /* Footer */
        .slip-footer {
            background: #013c10;
            color: rgba(255,255,255,0.7);
            text-align: center;
            padding: 0.75rem 2rem;
            font-size: 0.65rem;
            border-top: 3px solid #fcb900;
        }

        /* Buttons */
        .btn-group {
            text-align: center;
            margin-top: 2rem;
            display: flex;
            justify-content: center;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 2rem;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
        }

        .btn-print {
            background: #013c10;
            color: #ffffff;
        }

        .btn-print:hover {
            background: #015a18;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(1, 60, 16, 0.3);
        }

        .btn-back {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-back:hover {
            background: #e5e7eb;
        }

        @media (max-width: 600px) {
            body { padding: 1rem; }
            .slip-header { flex-direction: column; text-align: center; gap: 1rem; }
            .header-right { text-align: center; }
            .info-grid, .info-grid.three-col { grid-template-columns: 1fr; }
            .qr-cost-section { flex-direction: column; }
            .qr-section { margin: 1rem 0 0 0; }
            .signature-area { flex-direction: column; }
        }
    </style>
</head>
<body>

<div class="slip-container">
    <!-- Header -->
    <div class="slip-header">
        <div class="header-left">
            <img src="https://enrollment.cefi.website/images/cefi-logo.png" alt="CEFI Logo">
            <div class="header-text">
                <h1>Calayan Educational Foundation Inc.</h1>
                <p>CEFI Online Facility Reservation System</p>
                <p>Lucena City, Quezon Province, Philippines</p>
            </div>
        </div>
        <div class="header-right">
            <div class="doc-label">Document No.</div>
            <div class="doc-number"><?= $doc_number ?></div>
            <div class="doc-label" style="margin-top:6px;">Date Issued</div>
            <div style="font-size: 0.8rem; font-weight:600;"><?= date("M d, Y") ?></div>
        </div>
    </div>

    <!-- Title Banner -->
    <div class="title-banner">
        <h2>📋 RESERVATION CONFIRMATION SLIP</h2>
        <span class="status-badge">APPROVED</span>
    </div>

    <!-- Body -->
    <div class="slip-body">
        <!-- Requester Information -->
        <div class="info-section">
            <div class="section-label">👤 Requester Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Full Name</span>
                    <span class="value highlight"><?= htmlspecialchars($reservation["fb_name"]) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Contact / ID</span>
                    <span class="value"><?= htmlspecialchars($reservation["fb_user_id"]) ?></span>
                </div>
                <?php if ($reservation['num_attendees']): ?>
                <div class="info-item">
                    <span class="label">Expected Attendees</span>
                    <span class="value"><?= $reservation['num_attendees'] ?> persons</span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <span class="label">Request Date</span>
                    <span class="value"><?= $formatted_created_at ?></span>
                </div>
            </div>
        </div>

        <!-- Schedule Information -->
        <div class="info-section">
            <div class="section-label">📅 Schedule Details</div>
            <div class="info-grid three-col">
                <div class="info-item">
                    <span class="label">Reservation Date</span>
                    <span class="value highlight"><?= $formatted_date ?></span>
                    <span class="label" style="margin-top:2px;"><?= $day_of_week ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Time Slot</span>
                    <span class="value highlight"><?= $start_formatted ?> – <?= $end_formatted ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Duration</span>
                    <span class="value"><?= $duration ?> hour<?= $duration != 1 ? 's' : '' ?></span>
                </div>
            </div>
        </div>

        <!-- Purpose -->
        <div class="info-section">
            <div class="section-label">📝 Purpose of Reservation</div>
            <div class="facility-desc">
                <?= nl2br(htmlspecialchars($reservation["purpose"])) ?>
            </div>
        </div>

        <!-- Facility Information -->
        <div class="info-section">
            <div class="section-label">🏢 Facility Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <span class="label">Facility Name</span>
                    <span class="value highlight"><?= htmlspecialchars($reservation["facility_name"]) ?></span>
                </div>
                <div class="info-item">
                    <span class="label">Max Capacity</span>
                    <span class="value"><?= $reservation["facility_capacity"] ?> persons</span>
                </div>
            </div>
            <?php if ($reservation["facility_description"]): ?>
            <div class="facility-desc" style="margin-top: 0.75rem;">
                <?= htmlspecialchars($reservation["facility_description"]) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- QR Code + Cost Breakdown -->
        <div class="qr-cost-section">
            <div class="cost-breakdown">
                <div class="section-label" style="margin-bottom: 0.5rem;">💰 Cost Summary</div>
                <div class="cost-line">
                    <span>Facility Rate</span>
                    <span>₱<?= number_format($reservation['price_per_hour'] ?? 0, 2) ?> / hour</span>
                </div>
                <div class="cost-line">
                    <span>Duration</span>
                    <span><?= $duration ?> hour<?= $duration != 1 ? 's' : '' ?></span>
                </div>
                <div class="cost-line total">
                    <span>TOTAL ESTIMATED COST</span>
                    <span>₱<?= number_format($total_cost, 2) ?></span>
                </div>
            </div>
            <div class="qr-section">
                <img src="<?= $qr_url ?>" alt="QR Code" width="130" height="130">
                <p>Scan to verify reservation</p>
            </div>
        </div>

        <?php if (!empty($reservation['admin_notes'])): ?>
        <!-- Admin Notes -->
        <div class="info-section">
            <div class="section-label">📌 Admin Notes</div>
            <div class="facility-desc">
                <?= nl2br(htmlspecialchars($reservation['admin_notes'])) ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Terms & Conditions -->
        <div class="terms-section">
            <h4>Terms & Conditions</h4>
            <ul>
                <li>This reservation confirmation is subject to the policies of Calayan Educational Foundation Inc.</li>
                <li>The reserved facility must be used strictly for the stated purpose.</li>
                <li>Users are responsible for maintaining cleanliness and safety of the facility during and after use.</li>
                <li>Any damages to the facility or equipment must be reported immediately to the CEFI administration.</li>
                <li>The administration reserves the right to cancel or modify reservations due to unforeseen circumstances.</li>
                <li>This document must be presented upon entry to the reserved facility.</li>
            </ul>
        </div>

        <!-- Signature Area -->
        <div class="signature-area">
            <div class="signature-block">
                <div class="signature-line">
                    <div class="sig-name"><?= htmlspecialchars($reservation["fb_name"]) ?></div>
                    <div class="sig-title">Requester</div>
                </div>
            </div>
            <div class="signature-block">
                <div class="signature-line">
                    <div class="sig-name">CEFI Administration</div>
                    <div class="sig-title">Authorized Approver</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="slip-footer">
        © <?= date('Y') ?> CEFI ONLINE FACILITY RESERVATION. All rights reserved. | Calayan Educational Foundation Inc., Philippines | Contact: info@cefi.website
    </div>
</div>

<!-- Print / Back Buttons -->
<div class="btn-group no-print">
    <a href="index.php" class="btn btn-back">← Back to Reservations</a>
    <button class="btn btn-print" onclick="window.print()">🖨️ Print Reservation Slip</button>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('print') === 'true') {
            setTimeout(() => window.print(), 500);
        }
    });
</script>
</body>
</html>
