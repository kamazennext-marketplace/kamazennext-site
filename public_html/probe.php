<?php
header('Content-Type: application/json');

$response = [
    'time' => gmdate('c'),
    'php_version' => PHP_VERSION,
    '__FILE__' => __FILE__,
    '__DIR__' => __DIR__,
    'DOCUMENT_ROOT' => $_SERVER['DOCUMENT_ROOT'] ?? null,
    'SCRIPT_FILENAME' => $_SERVER['SCRIPT_FILENAME'] ?? null,
    'REQUEST_URI' => $_SERVER['REQUEST_URI'] ?? null,
    'HTTP_HOST' => $_SERVER['HTTP_HOST'] ?? null,
    'loaded_ini_file' => loaded_ini_file(),
];

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
