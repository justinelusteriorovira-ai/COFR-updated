<?php
session_start();
if (!isset($_SESSION["admin_id"])) {
    header("Location: ../auth/login.php");
    exit;
}
?>
<?php
require_once("../config/db.php");

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET["id"];

// Fetch facility
$stmt = $conn->prepare("SELECT * FROM facilities WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$facility = $result->fetch_assoc();

if (!$facility) {
    header("Location: index.php");
    exit;
}

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
    } else {
        $stmt = $conn->prepare("UPDATE facilities SET name = ?, description = ?, capacity = ?, status = ?, price_per_hour = ?, price_per_day = ?, open_time = ?, close_time = ?, advance_days_required = ?, min_duration_hours = ?, max_duration_hours = ?, allowed_days = ?, image = ? WHERE id = ?");
        $stmt->bind_param("ssisddssiiissi", $name, $description, $capacity, $status, $price_per_hour, $price_per_day, $open_time, $close_time, $advance_days_required, $min_duration_hours, $max_duration_hours, $allowed_days, $image, $id);
        
        if ($stmt->execute()) {
            require_once("../config/audit_helper.php");
            logActivity($conn, 'UPDATE', 'FACILITY', $id, "Updated facility: $name", $facility, [
                'name' => $name,
                'capacity' => $capacity,
                'status' => $status,
                'price_per_hour' => $price_per_hour,
                'open_time' => $open_time,
                'close_time' => $close_time
            ]);

            header("Location: index.php");
            exit;
        } else {
            $error = "Error updating facility: " . $conn->error;
        }
    }
}

$current_allowed_days = explode(',', $facility['allowed_days']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Facility - CEFI Reservation</title>
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
        <h1>Edit Facility</h1>
        <div class="header-user">Admin</div>
    </div>

    <div class="container">
    <div class="form-container">
      <h2>Edit Facility: <?= htmlspecialchars($facility['name']) ?></h2>
      
      <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
      
      <form method="POST">
        <div class="grid-2">
            <div class="input-group">
                <label for="name">Facility Name</label>
                <input type="text" id="name" name="name" value="<?= htmlspecialchars($facility['name']) ?>" required>
            </div>

            <div class="input-group">
                <label for="capacity">Max Capacity</label>
                <input type="number" id="capacity" name="capacity" value="<?= $facility['capacity'] ?>" required>
            </div>
        </div>

        <div class="input-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Describe the facility's amenities..."><?= htmlspecialchars($facility['description'] ?? '') ?></textarea>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="price_per_hour">Price per Hour (PHP)</label>
                <input type="number" step="0.01" id="price_per_hour" name="price_per_hour" value="<?= $facility['price_per_hour'] ?>">
            </div>

            <div class="input-group">
                <label for="price_per_day">Price per Day (PHP)</label>
                <input type="number" step="0.01" id="price_per_day" name="price_per_day" value="<?= $facility['price_per_day'] ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="open_time">Opening Time</label>
                <input type="time" id="open_time" name="open_time" value="<?= substr($facility['open_time'], 0, 5) ?>">
            </div>

            <div class="input-group">
                <label for="close_time">Closing Time</label>
                <input type="time" id="close_time" name="close_time" value="<?= substr($facility['close_time'], 0, 5) ?>">
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="advance_days_required">Advance Booking Days Required</label>
                <input type="number" id="advance_days_required" name="advance_days_required" value="<?= $facility['advance_days_required'] ?>">
            </div>

            <div class="input-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="AVAILABLE" <?= $facility['status'] == 'AVAILABLE' ? 'selected' : '' ?>>AVAILABLE</option>
                    <option value="MAINTENANCE" <?= $facility['status'] == 'MAINTENANCE' ? 'selected' : '' ?>>MAINTENANCE</option>
                    <option value="CLOSED" <?= $facility['status'] == 'CLOSED' ? 'selected' : '' ?>>CLOSED</option>
                </select>
            </div>
        </div>

        <div class="grid-2">
            <div class="input-group">
                <label for="min_duration_hours">Min Duration (Hours)</label>
                <input type="number" id="min_duration_hours" name="min_duration_hours" value="<?= $facility['min_duration_hours'] ?>">
            </div>

            <div class="input-group">
                <label for="max_duration_hours">Max Duration (Hours)</label>
                <input type="number" id="max_duration_hours" name="max_duration_hours" value="<?= $facility['max_duration_hours'] ?>">
            </div>
        </div>

        <div class="input-group">
            <label>Allowed Booking Days</label>
            <div class="checkbox-group">
                <?php
                $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];
                foreach ($days as $val => $label):
                    $checked = in_array((string)$val, $current_allowed_days) ? 'checked' : '';
                ?>
                    <label class="checkbox-item"><input type="checkbox" name="allowed_days[]" value="<?= $val ?>" <?= $checked ?>> <?= $label ?></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="input-group">
            <label for="image">Image URL / Placeholder</label>
            <input type="text" id="image" name="image" value="<?= htmlspecialchars($facility['image'] ?? '') ?>" placeholder="Image filename or URL">
        </div>

        <div style="margin-top: 1rem;">
            <button type="submit" class="btn btn-primary">Update Facility</button>
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
