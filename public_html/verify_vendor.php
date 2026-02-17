<?php
// Run this on the server to verify VendorService
require_once __DIR__ . '/api/index.php'; // For Autoloader

use Services\Vendor\VendorService;

echo "Testing VendorService...\n";

$service = new VendorService();

// 1. Test Registration
$email = 'test_vendor_' . time() . '@example.com';
$password = 'secret123';
$name = 'Test Vendor';

echo "Registering $email...\n";
$regResult = $service->register([
    'email' => $email,
    'password' => $password,
    'name' => $name,
    'company' => 'Test Corp'
]);

print_r($regResult);

if (isset($regResult['success'])) {
    echo "Registration OK.\n";

    // 2. Test Login
    echo "Logging in...\n";
    $loginResult = $service->login($email, $password);

    if ($loginResult) {
        echo "Login OK. Vendor ID: " . $loginResult['id'] . "\n";

        // 3. Test Dashboard
        echo "Fetching dashboard...\n";
        $dash = $service->getDashboardData($loginResult['id']);
        print_r($dash);

        // 4. Test Software Submission
        echo "Submitting software...\n";
        $swData = [
            'title' => 'Test Tool',
            'description' => 'A test tool description',
            'category' => 'CRM',
            'website' => 'https://example.com',
            'price' => 'Free'
        ];
        // Mock file upload (pass null for CLI test or simulate array)
        $swResult = $service->submitSoftware($loginResult['id'], $swData, null);
        print_r($swResult);

    } else {
        echo "Login Failed!\n";
    }
} else {
    echo "Registration Failed!\n";
}
?>