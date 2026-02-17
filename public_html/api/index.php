<?php

// Simple Autoloader
spl_autoload_register(function ($class) {
    $prefix = '';
    $base_dir = '';

    if (strpos($class, 'Core\\') === 0) {
        $prefix = 'Core\\';
        $base_dir = __DIR__ . '/../core/';
    } elseif (strpos($class, 'Services\\') === 0) {
        $prefix = 'Services\\';
        $base_dir = __DIR__ . '/../services/';
    } else {
        return;
    }

    $len = strlen($prefix);
    $relative_class = substr($class, $len);
    // Convert namespace separators to directory separators
    $path = str_replace('\\', '/', $relative_class);

    // Try original case first
    $file = $base_dir . $path . '.php';
    if (file_exists($file)) {
        require $file;
        return;
    }

    // Try lowercase directory for the first segment (e.g. Services/Vendor -> services/vendor/VendorService.php)
    $parts = explode('/', $path);
    if (count($parts) > 1) {
        $parts[0] = strtolower($parts[0]);
        $file_lower = $base_dir . implode('/', $parts) . '.php';
        if (file_exists($file_lower)) {
            require $file_lower;
            return;
        }
    }
});

use Core\Response;
use Core\Database;

// CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Basic Routing
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Remove /api and /v1 prefixes for internal routing
$uri = str_replace('/api', '', $uri);
$uri = str_replace('/v1', '', $uri);

// Routes
if ($uri === '/health' || $uri === '/health.php') {
    try {
        $db = Database::getInstance()->getConnection();
        Response::success(['status' => 'ok', 'db' => 'connected', 'version' => 'v1']);
    } catch (Exception $e) {
        Response::error('Database connection failed: ' . $e->getMessage(), 500);
    }
} elseif (strpos($uri, '/catalog') === 0) {
    // Delegate to CatalogController
    $controller = new \Services\Catalog\CatalogController();
    $controller->handleRequest($uri, $_SERVER['REQUEST_METHOD']);
} elseif (strpos($uri, '/vendor') === 0) {
    // Delegate to VendorController
    $controller = new \Services\Vendor\VendorController();
    $controller->handleRequest($uri, $_SERVER['REQUEST_METHOD']);
} elseif ($uri === '/' || $uri === '') {
    Response::success(['message' => 'API Gateway v1']);
} else {
    Response::error('Not Found', 404);
}
