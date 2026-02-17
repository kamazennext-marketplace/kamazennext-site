<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

// (Optional) CORS – safe if you only use your domain
header('Access-Control-Allow-Origin: https://kamazennext.com');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204);
  exit;
}

try {
  require_once __DIR__ . '/ask-openai.php';

  $body = json_decode((string)file_get_contents('php://input'), true);
  $message = is_array($body) && isset($body['message']) ? trim((string)$body['message']) : '';
  $history = is_array($body) && isset($body['history']) && is_array($body['history']) ? $body['history'] : [];

  if ($message === '') {
    http_response_code(400);
    echo json_encode(['reply' => 'Please type a message.']);
    exit;
  }

  $reply = kz_ask_openai($message, $history);
  echo json_encode(['reply' => $reply], JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {
  // Log server-side for debugging (check your error_log file)
  error_log("AI ERROR: " . $e->getMessage());

  http_response_code(503);
  echo json_encode(['reply' => 'AI service unavailable'], JSON_UNESCAPED_SLASHES);
}
