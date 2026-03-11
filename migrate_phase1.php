<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once("config/db.php");

echo "Applying Phase 1 Migration...\n";

$sqls = [
    // Database updates for facilities
    "ALTER TABLE facilities ADD COLUMN price_per_hour DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE facilities ADD COLUMN price_per_day DECIMAL(10,2) DEFAULT 0",
    "ALTER TABLE facilities ADD COLUMN open_time TIME DEFAULT '07:00:00'",
    "ALTER TABLE facilities ADD COLUMN close_time TIME DEFAULT '20:00:00'",
    "ALTER TABLE facilities ADD COLUMN advance_days_required INT DEFAULT 2",
    "ALTER TABLE facilities ADD COLUMN min_duration_hours INT DEFAULT 1",
    "ALTER TABLE facilities ADD COLUMN max_duration_hours INT DEFAULT 8",
    "ALTER TABLE facilities ADD COLUMN allowed_days VARCHAR(20) DEFAULT '1,2,3,4,5,6'",
    "ALTER TABLE facilities ADD COLUMN image VARCHAR(255)",

    // Database updates for reservations
    "ALTER TABLE reservations ADD COLUMN duration_hours DECIMAL(4,1)",
    "ALTER TABLE reservations ADD COLUMN total_cost DECIMAL(10,2)",
    "ALTER TABLE reservations ADD COLUMN verification_deadline DATETIME",
    "ALTER TABLE reservations ADD COLUMN verified_at DATETIME",
    "ALTER TABLE reservations ADD COLUMN reject_reason TEXT",
    "ALTER TABLE reservations ADD COLUMN admin_notes TEXT",
    "ALTER TABLE reservations ADD COLUMN cancelled_at DATETIME",
    "ALTER TABLE reservations ADD COLUMN cancel_reason TEXT",
    "ALTER TABLE reservations ADD COLUMN num_attendees INT",
    
    // Modify ENUM status in reservations
    "ALTER TABLE reservations MODIFY COLUMN status ENUM('PENDING', 'APPROVED', 'REJECTED', 'PENDING_VERIFICATION', 'EXPIRED', 'CANCELLED', 'ON_HOLD', 'WAITLISTED') DEFAULT 'PENDING'"
];

foreach ($sqls as $sql) {
    echo "Running: $sql\n";
    if ($conn->query($sql) === TRUE) {
        echo "SUCCESS\n";
    } else {
        echo "ERROR (might already exist): " . $conn->error . "\n";
    }
}

echo "Phase 1 Migration Finished.\n";
?>
