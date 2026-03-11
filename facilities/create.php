<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<?php
require_once("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name = trim($_POST["name"]);
    $description = trim($_POST["description"]);
    $capacity = trim($_POST["capacity"]);
    $status = $_POST["status"];

    $price_per_hour = trim($_POST["price_per_hour"]);
    $price_per_day = trim($_POST["price_per_day"]);
    $open_time = $_POST["open_time"];
    $close_time = $_POST["close_time"];
    $advance_days_required = trim($_POST["advance_days_required"]);
    $min_duration_hours = trim($_POST["min_duration_hours"]);
    $max_duration_hours = trim($_POST["max_duration_hours"]);

    $allowed_days = isset($_POST["allowed_days"]) ? implode(',', $_POST["allowed_days"]) : '1,2,3,4,5,6';
    $image = trim($_POST["image"]);

    $valid_statuses = ['AVAILABLE', 'MAINTENANCE', 'CLOSED'];
    if (!in_array($status, $valid_statuses)) {
        $status = 'AVAILABLE';
    }

    if (empty($name) || empty($capacity)) {
        $error = "Name and Capacity are required fields.";
    }
    else {

        $stmt = $conn->prepare("INSERT INTO facilities (name, description, capacity, status, price_per_hour, price_per_day, open_time, close_time, advance_days_required, min_duration_hours, max_duration_hours, allowed_days, image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssisddssiiiss", $name, $description, $capacity, $status, $price_per_hour, $price_per_day, $open_time, $close_time, $advance_days_required, $min_duration_hours, $max_duration_hours, $allowed_days, $image);

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;

            require_once("../config/audit_helper.php");
            logActivity($conn, 'CREATE', 'FACILITY', $new_id, "Created a new facility: $name", null, [
                'name' => $name,
                'capacity' => $capacity,
                'status' => $status,
                'price_per_hour' => $price_per_hour,
                'open_time' => $open_time,
                'close_time' => $close_time
            ]);

            header("Location: index.php");
            exit;
        }
        else {
            $error = "Error saving facility: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Facility - CEFI Reservation</title>
    <link rel="stylesheet" href="../style/facilities.css">
    <link rel="stylesheet" href="../style/navbar.css">
    <style>
        .form-container { max-width: 800px; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
        .checkbox-group { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 0.5rem; }
        .checkbox-item { display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; }
        .checkbox-item input { width: auto; margin: 0; }
    </style>
</head>
<body>

<?php include '../includes/navbar.php'; ?>

<div class="main-content">
    <div class="page-header">
        <h1>Add Facility</h1>
        <div class="header-user">Admin</div>
    </div>

    <div class="container">
    <div class="form-container">
      <h2>Add Facility</h2>
      
      <?php if (isset($error))
    echo "<p class='error'>$error</p>"; ?>
      
      <form method="POST">
        <div class="grid-2">
            <div class="input-group">
                <label for="name">Facility Name</label>
                <input type="text" id="name" name="name" placeholder="e.g. Gym, Multi-purpose Hall" required>
            </div>

            <div class="input-group">
                <label for="capacity">Max Capacity</label>
                <input type="number" id="capacity" name="capacity" placeholder="Number of people" required>
            </div>
        </div>

        <div class="input-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Describe the facility's amenities and rules..."></textarea>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="price_per_hour">Price per Hour (PHP)</label>
                <input type="number" step="0.01" id="price_per_hour" name="price_per_hour" value="0.00">
            </div>

            <div class="input-group">
                <label for="price_per_day">Price per Day (PHP)</label>
                <input type="number" step="0.01" id="price_per_day" name="price_per_day" value="0.00">
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="open_time">Opening Time</label>
                <input type="time" id="open_time" name="open_time" value="07:00">
            </div>

            <div class="input-group">
                <label for="close_time">Closing Time</label>
                <input type="time" id="close_time" name="close_time" value="20:00">
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="advance_days_required">Advance Booking Days Required</label>
                <input type="number" id="advance_days_required" name="advance_days_required" value="2">
            </div>

            <div class="input-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="AVAILABLE">AVAILABLE</option>
                    <option value="MAINTENANCE">MAINTENANCE</option>
                    <option value="CLOSED">CLOSED</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="min_duration_hours">Min Duration (Hours)</label>
                <input type="number" id="min_duration_hours" name="min_duration_hours" value="1">
            </div>

            <div class="input-group">
                <label for="max_duration_hours">Max Duration (Hours)</label>
                <input type="number" id="max_duration_hours" name="max_duration_hours" value="8">
            </div>
        </div>

        <div class="input-group">
            <label>Allowed Booking Days</label>
            <div class="checkbox-group">
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="1" checked> Mon</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="2" checked> Tue</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="3" checked> Wed</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="4" checked> Thu</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="5" checked> Fri</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="6" checked> Sat</label>
                <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="0"> Sun</label>
            </div>
        </div>

        <div class="input-group">
            <label for="image">Image URL / Placeholder</label>
            <input type="text" id="image" name="image" placeholder="Image filename or URL">
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit" class="btn btn-primary">Create Facility</button>
            <a href="index.php" class="btn btn-secondary">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="footer">
    © 2026 CEFI ONLINE FACILITY RESERVATION. All rights reserved. | Calayan Educational Foundation Inc., Philippines | Contact: info@cefi.website
</div>
</body>
</html>
