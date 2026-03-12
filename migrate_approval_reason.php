<?php
require_once("config/db.php");
// Add approval_reason column if it doesn't exist
$check = $conn->query("SHOW COLUMNS FROM reservations LIKE 'approval_reason'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE reservations ADD COLUMN approval_reason TEXT AFTER reject_reason")) {
        echo "SUCCESS: approval_reason column added.";
    } else {
        echo "ERROR: " . $conn->error;
    }
} else {
    echo "EXISTS: approval_reason column already exists.";
}
?>
