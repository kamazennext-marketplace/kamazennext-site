<?php

declare(strict_types=1);

require_once __DIR__ . '/../../api/api/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    throw new RuntimeException('Database connection unavailable.');
}

$conn->set_charset('utf8mb4');
