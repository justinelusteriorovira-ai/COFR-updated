<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("config/db.php");

echo "Applying Phase 2 Migration...\n";

$sqls = [
    // Add user contact fields
    "ALTER TABLE reservations ADD COLUMN user_email VARCHAR(255) AFTER fb_user_id",
    "ALTER TABLE reservations ADD COLUMN user_phone VARCHAR(20) AFTER user_email",
];

foreach ($sqls as $sql) {
    echo "Running: $sql\n";
    if ($conn->query($sql) === TRUE) {
        echo "SUCCESS\n";
    } else {
        echo "ERROR (might already exist): " . $conn->error . "\n";
    }
}

echo "Phase 2 Migration Finished.\n";
?>
