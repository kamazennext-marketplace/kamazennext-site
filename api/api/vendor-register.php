<?php
include "db.php";   // database connection

header("Content-Type: application/json");

// Get form values
$name     = $_POST['name'] ?? '';
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$company  = $_POST['company'] ?? '';

// Basic validation
if ($name == "" || $email == "" || $password == "" || $company == "") {
    echo json_encode(["status" => "error", "message" => "All fields required"]);
    exit;
}

// HASH PASSWORD for security
$hash = password_hash($password, PASSWORD_BCRYPT);

// Insert vendor
$sql = "INSERT INTO vendors (name, email, password, company)
        VALUES ('$name', '$email', '$hash', '$company')";

if (mysqli_query($conn, $sql)) {
    echo json_encode(["status" => "success", "message" => "Vendor registered"]);
} else {
    echo json_encode(["status" => "error", "message" => mysqli_error($conn)]);
}
?>
