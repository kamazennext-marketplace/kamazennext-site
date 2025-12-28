<?php
include "db.php";
header("Content-Type: application/json");

// Get login data
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validation
if ($email == "" || $password == "") {
    echo json_encode(["status" => "error", "message" => "Email & Password required"]);
    exit;
}

// Fetch vendor by email
$sql = "SELECT * FROM vendors WHERE email = '$email' LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 0) {
    echo json_encode(["status" => "error", "message" => "Vendor not found"]);
    exit;
}

$vendor = mysqli_fetch_assoc($result);

// Verify password
if (!password_verify($password, $vendor['password'])) {
    echo json_encode(["status" => "error", "message" => "Wrong password"]);
    exit;
}

// SUCCESS
echo json_encode([
    "status" => "success",
    "message" => "Login successful",
    "vendor_id" => $vendor['id'],
    "vendor_name" => $vendor['name'],
    "vendor_company" => $vendor['company']
]);
?>
