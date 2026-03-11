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

// Fetch reservation details for logging BEFORE deletion
$res_stmt = $conn->prepare("SELECT * FROM reservations WHERE id = ?");
$res_stmt->bind_param("i", $id);
$res_stmt->execute();
$reservation = $res_stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("DELETE FROM reservations WHERE id = ?");
$stmt->bind_param("i", $id);

$redirect_to = "index.php";
if (isset($_SERVER['HTTP_REFERER']) && (strpos($_SERVER['HTTP_REFERER'], 'dashboard.php') !== false || strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false)) {
    $redirect_to = $_SERVER['HTTP_REFERER'];
}

// Clean up existing msg/error if any
$redirect_to = strtok($redirect_to, '?');

if ($stmt->execute()) {
    if ($reservation) {
        require_once("../config/audit_helper.php");
        logActivity($conn, 'DELETE', 'RESERVATION', $id, "Deleted reservation for " . $reservation['fb_name'], $reservation, null);
    }
    header("Location: $redirect_to?msg=Reservation deleted successfully.");
} else {
    header("Location: $redirect_to?error=Delete failed: " . $conn->error);
}
exit;
?>
