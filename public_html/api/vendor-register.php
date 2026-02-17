<?php
require_once __DIR__ . '/api/db.php';

header("Content-Type: application/json");

// Get form values
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$company = $_POST['company'] ?? '';

// Basic validation
if ($name === "" || $email === "" || $password === "" || $company === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "All fields required"]);
    exit;
}

// Check if email already exists
$stmt = $conn->prepare("SELECT id FROM vendors WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    http_response_code(409); // Conflict
    echo json_encode(["status" => "error", "message" => "Email already registered"]);
    exit;
}
$stmt->close();

// HASH PASSWORD for security
$hash = password_hash($password, PASSWORD_BCRYPT);

// Insert vendor using Prepared Statement
$stmt = $conn->prepare("INSERT INTO vendors (name, email, password, company) VALUES (?, ?, ?, ?)");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
    exit;
}

$stmt->bind_param("ssss", $name, $email, $hash, $company);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Vendor registered successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Registration failed"]);
}
