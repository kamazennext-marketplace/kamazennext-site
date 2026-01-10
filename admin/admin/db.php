<?php

declare(strict_types=1);

$configPath = '/home2/kamazennext/db_config.php';
if (!file_exists($configPath)) {
    error_log('Database config missing at ' . $configPath);
    $conn = null;
    return;
}

require_once $configPath;

if (!defined('DB_HOST') || !defined('DB_USER') || !defined('DB_PASS') || !defined('DB_NAME')) {
    error_log('Database config constants are not defined.');
    $conn = null;
    return;
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_errno) {
    error_log('Database connection failed: ' . $conn->connect_error);
    $conn = null;
    return;
}

$conn->set_charset('utf8mb4');
