<?php
header('Content-Type: application/json');

echo json_encode([
    'ok' => true,
    'time' => gmdate('c'),
    'path' => __FILE__,
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
