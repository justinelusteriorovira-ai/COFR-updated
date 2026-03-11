<?php
require_once("../config/db.php");

header("Content-Type: application/json");

// Get POST data
$fb_name = $_POST["fb_name"] ?? null;
$fb_user_id = $_POST["fb_user_id"] ?? null;
$facility_id = $_POST["facility_id"] ?? null;
$reservation_date = $_POST["reservation_date"] ?? null;
$start_time = $_POST["start_time"] ?? null;
$end_time = $_POST["end_time"] ?? null;
$purpose = $_POST["purpose"] ?? null;

if (
    !$fb_name || !$fb_user_id || !$facility_id ||
    !$reservation_date || !$start_time || !$end_time
) {
    echo json_encode([
        "status" => "error",
        "message" => "Missing required fields."
    ]);
    exit;
}

// Insert as PENDING
$stmt = $conn->prepare("
    INSERT INTO reservations
    (fb_user_id, fb_name, facility_id, reservation_date, start_time, end_time, purpose)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssissss",
    $fb_user_id,
    $fb_name,
    $facility_id,
    $reservation_date,
    $start_time,
    $end_time,
    $purpose
);

$stmt->execute();

echo json_encode([
    "status" => "success",
    "message" => "Reservation submitted. Waiting for admin approval."
]);

$stmt->close();
$conn->close();
