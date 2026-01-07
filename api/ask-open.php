<?php
/**
 * API ENDPOINT: /api/ask-open.php
 * METHOD: POST
 * RETURNS: application/json
 *
 * SECURITY:
 * - No secrets in response.
 * - Validate input strictly.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['reply' => 'Method not allowed'], JSON_UNESCAPED_SLASHES);
    exit;
}

$payload = json_decode((string)file_get_contents('php://input'), true);
$message = is_array($payload) && isset($payload['message'])
    ? trim((string)$payload['message'])
    : '';
$history = is_array($payload) && isset($payload['history']) && is_array($payload['history'])
    ? $payload['history']
    : [];

if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => 'Please provide a message.'], JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    require_once __DIR__ . '/api/ask-openai.php';
    $reply = kz_ask_openai($message, $history);
    echo json_encode(['reply' => $reply], JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('ask-open.php error: ' . $e->getMessage());
    http_response_code(503);
    echo json_encode(['reply' => 'AI service unavailable'], JSON_UNESCAPED_SLASHES);
}
