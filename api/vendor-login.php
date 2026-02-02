<?php
require_once __DIR__ . '/api/db.php'; // Adjusted path if db.php is in api/api, or we might need to find where db.php is.
// Actually, let's check where db.php is. Step 28 says db.php is in api/api.
// So if we move this to api/, it should include 'api/db.php'.

// Wait, let's double check db.php location.
// Step 28: api/api/db.php
// So from api/vendor-login.php, it is 'api/db.php'.

header("Content-Type: application/json");

// Get login data
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Validation
if ($email === "" || $password === "") {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Email & Password required"]);
    exit;
}

// Fetch vendor by email using Prepared Statement
$stmt = $conn->prepare("SELECT id, name, company, password FROM vendors WHERE email = ? LIMIT 1");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error"]);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
    exit;
}

$vendor = $result->fetch_assoc();

// Verify password
if (!password_verify($password, $vendor['password'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
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
