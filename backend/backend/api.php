<?php
//require_once __DIR__ . '/config.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo json_encode([
    "status" => "API FILE LOADED",
    "php_version" => phpversion()
]);
exit;
// ---------- Load .env (cPanel safe) ----------
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        putenv($line);
    }
}
// ---------- Load .env ----------
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        putenv(trim($line));
    }
}
//Config::enableErrorLogging();

header('Content-Type: application/json; charset=utf-8');

// ---------- CORS ----------
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, Config::getAllowedOrigins())) {
    header("Access-Control-Allow-Origin: $origin");
}
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ---------- Router ----------
try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $_GET['action'] ?? 'health';
    $message = $input['message'] ?? '';

    switch ($action) {
        case 'health':
            health();
            break;

        case 'chat':
            chat($message);
            break;

        default:
            throw new Exception("Invalid action");
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// ---------- Handlers ----------
function health() {
    {
    echo json_encode([
        'success' => true,
        'data' => [
            'api_configured' => Config::isConfigured(),
            'env_key_exists' => isset($_ENV['OPENAI_API_KEY']),
            'env_key_length' => isset($_ENV['OPENAI_API_KEY']) ? strlen($_ENV['OPENAI_API_KEY']) : 0,
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
    echo json_encode([
        'success' => true,
        'data' => [
            'api_configured' => Config::isConfigured(),
            'api_key_loaded' => getenv('OPENAI_API_KEY') ? 'YES' : 'NO',
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}
    echo json_encode([
        'success' => true,
        'data' => [
            'api_configured' => Config::isConfigured(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ]);
}

function chat($message) {
    if (!$message) {
        throw new Exception("Message required");
    }

    if (!Config::isConfigured()) {
        echo json_encode([
            'success' => true,
            'data' => getSimulatedResponse($message)
        ]);
        return;
    }

    echo json_encode([
        'success' => true,
        'data' => callOpenAI($message)
    ]);
}

// ---------- OpenAI ----------
function callOpenAI($message) {
    $payload = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => Config::getSystemPrompt()],
            ['role' => 'user', 'content' => $message]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . trim(Config::getApiKey())
        ]
    ]);

    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code !== 200) {
        return getSimulatedResponse($message);
    }

    $json = json_decode($response, true);
    return [
        'reply' => $json['choices'][0]['message']['content']
    ];
}

// ---------- Fallback ----------
function getSimulatedResponse($message) {
    return [
        'reply' => "Simulated AI response.\nYour question was:\n\n{$message}"
    ];
}
