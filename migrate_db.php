<?php
require_once("c:/xampp/htdocs/cefi_reservation/config/db.php");

echo "Applying Migration...\n";

$sql = "
CREATE TABLE IF NOT EXISTS special_occasions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(150) NOT NULL,
    occasion_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    type ENUM('HOLIDAY','SCHOOL_EVENT','BLOCKED','ANNOUNCEMENT') DEFAULT 'SCHOOL_EVENT',
    description TEXT,
    color VARCHAR(7) DEFAULT '#8e44ad',
    is_recurring TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO special_occasions (title, occasion_date, type, description, color) VALUES
('Independence Day', '2026-06-12', 'HOLIDAY', 'National Holiday in the Philippines', '#e74c3c'),
('CEFI Foundation Day', '2026-03-15', 'SCHOOL_EVENT', 'Foundation day celebrations', '#8e44ad');
";

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "SUCCESS: Migration applied successfully.\n";
} else {
    echo "FAILURE: " . $conn->error . "\n";
}
?>
