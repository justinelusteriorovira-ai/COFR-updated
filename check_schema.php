<?php
require_once("config/db.php");
$res = $conn->query("DESCRIBE reservations");
while($row = $res->fetch_assoc()){
    echo $row['Field'] . " - " . $row['Type'] . "\n";
}
?>
