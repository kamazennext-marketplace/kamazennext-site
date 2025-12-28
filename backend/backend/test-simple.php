<?php
// backend/test-simple.php
echo "Testing .htaccess configuration...<br><br>";

// Test 1: Check if API key is set
$apiKey = getenv('OPENAI_API_KEY');
echo "1. API Key from environment: " . ($apiKey ? "SET (" . substr($apiKey, 0, 10) . "...)" : "NOT SET") . "<br>";

// Test 2: Check server info
echo "2. Server: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown') . "<br>";
echo "3. PHP Version: " . phpversion() . "<br>";

// Test 3: Check if we can access this file
echo "4. File accessible: YES<br>";

// Test 4: Try to access config.php (should be blocked)
echo "5. config.php should be blocked by .htaccess<br>";
?>