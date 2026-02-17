<?php
header("Content-Type: application/json");
include "db.php";

$sql = "SELECT id, name, category, description, website, price, rating, image 
        FROM software 
        WHERE status='approved' 
        ORDER BY id DESC";

$result = mysqli_query($conn, $sql);

$software = [];

while ($row = mysqli_fetch_assoc($result)) {
    $software[] = $row;
}

echo json_encode($software);
?>
