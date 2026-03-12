<?php
require_once("../config/db.php");
require_once("../config/api_auth.php");

requireAPIAuth();

header("Content-Type: application/json");

// Accept JSON body or POST form data
$data = getRequestData();

$fb_name          = $data["fb_name"] ?? null;
$fb_user_id       = $data["fb_user_id"] ?? null;
$facility_id      = isset($data["facility_id"]) ? intval($data["facility_id"]) : null;
$reservation_date = $data["reservation_date"] ?? null;
$start_time       = $data["start_time"] ?? null;
$end_time         = $data["end_time"] ?? null;
$purpose          = $data["purpose"] ?? '';
$user_email       = $data["user_email"] ?? '';
$user_phone       = $data["user_phone"] ?? '';
$user_type        = $data["user_type"] ?? 'Outside';
$num_attendees    = isset($data["num_attendees"]) ? intval($data["num_attendees"]) : 0;

if (!$fb_name || !$fb_user_id || !$facility_id || !$reservation_date || !$start_time || !$end_time) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "error"   => "Missing required fields: fb_name, fb_user_id, facility_id, reservation_date, start_time, end_time."
    ]);
    exit;
}

// Insert as PENDING with all extended fields
$stmt = $conn->prepare("
    INSERT INTO reservations
    (fb_user_id, fb_name, facility_id, reservation_date, start_time, end_time, purpose,
     user_email, user_phone, user_type, num_attendees, reservation_type)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'ONLINE')
");
$stmt->bind_param(
    "ssisssssssi",
    $fb_user_id, $fb_name, $facility_id, $reservation_date,
    $start_time, $end_time, $purpose,
    $user_email, $user_phone, $user_type, $num_attendees
);

if ($stmt->execute()) {
    $reservation_id = $conn->insert_id;
    echo json_encode([
        "success"        => true,
        "reservation_id" => $reservation_id,
        "message"        => "Reservation submitted. Pending admin approval.",
        "status"         => "PENDING"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "error" => "Database error: " . $conn->error]);
}

$stmt->close();
$conn->close();
