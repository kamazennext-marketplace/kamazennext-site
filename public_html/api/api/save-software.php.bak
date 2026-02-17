<?php
include "db.php";
header("Content-Type: application/json");

// Check vendor ID
$vendor_id = $_POST['vendor_id'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$category = $_POST['category'] ?? '';

if ($vendor_id == "" || $title == "" || $description == "" || $category == "") {
    echo json_encode(["status" => "error", "message" => "All fields are required"]);
    exit;
}

// Handle image upload
$image_path = "";
if (!empty($_FILES["image"]["name"])) {
    $target_dir = "../software/";
    if (!is_dir($target_dir)) mkdir($target_dir);

    $file_name = time() . "_" . basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $file_name;

    if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
        $image_path = "software/" . $file_name;
    }
}

// Insert into DB
$query = "INSERT INTO software (vendor_id, title, description, category, image, status)
          VALUES ('$vendor_id', '$title', '$description', '$category', '$image_path', 'pending')";

if (mysqli_query($conn, $query)) {
    echo json_encode(["status" => "success", "message" => "Software submitted. Pending approval."]);
} else {
    echo json_encode(["status" => "error", "message" => "Database error"]);
}
?>
