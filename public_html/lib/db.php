<?php
declare(strict_types=1);

function kzn_require_db_config(): void {
    // Prefer config OUTSIDE public_html
    $paths = [
        dirname(__DIR__, 3) . '/db_config.php',
        dirname(__DIR__) . '/config/db_config.php',
    ];

    foreach ($paths as $path) {
        if (is_file($path)) {
            require_once $path;
            return;
        }
    }
}

function kzn_get_db_connection(): ?mysqli {
    kzn_require_db_config();

    if (!defined('DB_HOST') || !defined('DB_NAME') || !defined('DB_USER') || !defined('DB_PASS')) {
        return null;
    }

    $connection = @new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($connection->connect_error) {
        error_log('Database connection failed: ' . $connection->connect_error);
        return null;
    }

    if (!$connection->set_charset('utf8mb4')) {
        error_log('Failed to set utf8mb4 charset: ' . $connection->error);
    }

    return $connection;
}

function kzn_get_software_columns(mysqli $connection): array {
    static $cached = null;

    if ($cached !== null) {
        return $cached;
    }

    $columns = [];
    $result = $connection->query('SHOW COLUMNS FROM software');
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            if (!empty($row['Field'])) {
                $columns[] = $row['Field'];
            }
        }
        $result->free();
    }

    $cached = $columns;
    return $columns;
}

function kzn_detect_column(array $columns, array $candidates): ?string {
    $lookup = [];
    foreach ($columns as $column) {
        $lookup[strtolower($column)] = $column;
    }

    foreach ($candidates as $candidate) {
        $key = strtolower($candidate);
        if (isset($lookup[$key])) {
            return $lookup[$key];
        }
    }

    return null;
}

function kzn_get_software_column_map(mysqli $connection): array {
    $columns = kzn_get_software_columns($connection);

    return [
        'id' => kzn_detect_column($columns, ['id', 'software_id', 'tool_id', 'product_id']),
        'slug' => kzn_detect_column($columns, ['slug', 'permalink', 'uri', 'url_slug']),
        'name' => kzn_detect_column($columns, ['name', 'title', 'software_name', 'tool_name', 'product_name']),
        'description' => kzn_detect_column($columns, ['description', 'summary', 'short_description', 'tagline', 'details']),
        'website' => kzn_detect_column($columns, ['website', 'url', 'link', 'site_url', 'homepage']),
        'category' => kzn_detect_column($columns, ['category', 'type', 'industry']),
        'pricing' => kzn_detect_column($columns, ['pricing', 'pricing_model', 'price', 'cost']),
        'image' => kzn_detect_column($columns, ['image', 'logo', 'thumbnail', 'icon', 'image_url']),
    ];
}

function kzn_stmt_bind(mysqli_stmt $statement, string $types, array $params): void {
    if ($types === '') {
        return;
    }

    $refs = [];
    foreach ($params as $index => $value) {
        $refs[$index] = &$params[$index];
    }

    array_unshift($refs, $types);
    $statement->bind_param(...$refs);
}
