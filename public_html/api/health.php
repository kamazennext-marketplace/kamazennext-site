<?php
/**
 * API ENDPOINT: /api/health.php
 * METHOD: GET
 * RETURNS: application/json
 *
 * SECURITY:
 * - No secrets in response.
 * - Validate input strictly.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// Health check used by frontend to detect missing API key / deployment issues.
$keyFile = '/home2/kamazennext/.secrets/openai_key.txt';
$fileHasKey = is_readable($keyFile) && trim((string)file_get_contents($keyFile)) !== '';
$envHasKey = (string)getenv('OPENAI_API_KEY') !== '';

echo json_encode([
    'ok' => true,
    'has_key' => $fileHasKey || $envHasKey,
    'time' => gmdate('c'),
    'php' => PHP_VERSION,
], JSON_UNESCAPED_SLASHES);
