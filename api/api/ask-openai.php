<?php
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!is_array($input) || !isset($input['message'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

$message = trim($input['message']);
$history = isset($input['history']) && is_array($input['history']) ? $input['history'] : [];

// Load OpenAI API key from external config file.
$configPath = '/home2/kamazennext/openai_config.php';
if (file_exists($configPath)) {
    include $configPath;
}

if (!defined('OPENAI_API_KEY') || !OPENAI_API_KEY) {
    http_response_code(500);
    echo json_encode(['error' => 'AI service unavailable']);
    exit;
}

// Build the conversation for the Responses API.
$messages = [];
foreach ($history as $item) {
    if (!isset($item['role'], $item['content'])) {
        continue;
    }

    $role = $item['role'] === 'assistant' ? 'assistant' : 'user';
    $messages[] = [
        'role' => $role,
        'content' => (string) $item['content'],
    ];
}

$messages[] = [
    'role' => 'user',
    'content' => $message,
];

$payload = [
    'model' => 'gpt-4o-mini',
    'max_output_tokens' => 400,
    'input' => $messages,
    'response_format' => ['type' => 'text'],
];

$ch = curl_init('https://api.openai.com/v1/responses');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
]);

$response = curl_exec($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $statusCode >= 400) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to contact AI service']);
    exit;
}

$data = json_decode($response, true);
$reply = $data['output'][0]['content'][0]['text'] ?? null;

if (!$reply) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid AI response']);
    exit;
}

echo json_encode(['reply' => $reply]);
