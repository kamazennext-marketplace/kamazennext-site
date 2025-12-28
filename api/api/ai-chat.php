<?php
// ===============================
// PRODUCTION MODE
// ===============================
ini_set('display_errors', 0);
error_reporting(0);

// ===============================
// HEADERS (LOCKED)
// ===============================
header("Content-Type: application/json");
$allowedOrigins = [
    "https://kamazennext.com",
    "https://www.kamazennext.com"
];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowedOrigins)) {
    header("Access-Control-Allow-Origin: " . $_SERVER['HTTP_ORIGIN']);
}
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// ===============================
// SIMPLE RATE LIMIT (IP BASED)
// ===============================
$ip = $_SERVER['REMOTE_ADDR'];
$rateFile = sys_get_temp_dir() . "/ai_rate_" . md5($ip);
$limit = 15; // requests
$window = 60; // seconds

$requests = file_exists($rateFile)
    ? json_decode(file_get_contents($rateFile), true)
    : ["count" => 0, "time" => time()];

if (time() - $requests["time"] > $window) {
    $requests = ["count" => 0, "time" => time()];
}

$requests["count"]++;

if ($requests["count"] > $limit) {
    echo json_encode(["reply" => "Too many requests. Please wait a minute."]);
    exit;
}

file_put_contents($rateFile, json_encode($requests));

// ===============================
// READ INPUT
// ===============================
$input = json_decode(file_get_contents("php://input"), true);

if (!isset($input["message"])) {
    echo json_encode(["reply" => "Invalid request"]);
    exit;
}

$userMessage = trim($input["message"]);
// ===============================
// SOFTWARE CATALOG (MVP)
// ===============================
$softwareCatalog = [
    "zoho crm" => [
        "name" => "Zoho CRM",
        "category" => "CRM",
        "pricing" => "Freemium / Paid",
        "best_for" => "SMBs, Indian businesses",
        "platforms" => "Web, Android, iOS",
        "strengths" => "Cost-effective, deep customization, Indian compliance"
    ],
    "hubspot" => [
        "name" => "HubSpot CRM",
        "category" => "CRM",
        "pricing" => "Freemium / Premium",
        "best_for" => "Startups, SMBs, Enterprises",
        "platforms" => "Web, Android, iOS",
        "strengths" => "Ease of use, marketing automation, scalability"
    ]
];
// ===============================
// COMPARISON INTENT DETECTION
// ===============================
$comparisonKeywords = [
    "compare",
    "vs",
    "versus",
    "difference between",
    "better than"
];

$isComparison = false;

foreach ($comparisonKeywords as $keyword) {
    if (stripos($userMessage, $keyword) !== false) {
        $isComparison = true;
        break;
    }
}
// ===============================
// SOFTWARE NAME MATCHING
// ===============================
$matchedSoftware = [];

foreach ($softwareCatalog as $key => $software) {
    if (stripos($userMessage, $key) !== false ||
        stripos($userMessage, strtolower($software["name"])) !== false) {
        $matchedSoftware[$key] = $software;
    }
}
// ===============================
// CONTROLLED COMPARISON OVERRIDE
// ===============================
if ($isComparison && count($matchedSoftware) >= 2) {

    $comparisonPrompt = "You are comparing software using verified marketplace data only.\n\n";

    foreach ($matchedSoftware as $software) {
        $comparisonPrompt .= "{$software['name']}:\n";
        $comparisonPrompt .= "- Category: {$software['category']}\n";
        $comparisonPrompt .= "- Pricing: {$software['pricing']}\n";
        $comparisonPrompt .= "- Best For: {$software['best_for']}\n";
        $comparisonPrompt .= "- Platforms: {$software['platforms']}\n";
        $comparisonPrompt .= "- Strengths: {$software['strengths']}\n\n";
    }

    $comparisonPrompt .= "Instructions:\n";
    $comparisonPrompt .= "1. Compare pricing models\n";
    $comparisonPrompt .= "2. Compare features & strengths\n";
    $comparisonPrompt .= "3. Best choice for startups\n";
    $comparisonPrompt .= "4. Best choice for enterprises\n";
    $comparisonPrompt .= "5. Final recommendation\n";
    $comparisonPrompt .= "Do not add information that is not provided.\n";

    // Override user message
    $userMessage = $comparisonPrompt;
}

// ===============================
// BASIC SAFETY FILTER
// ===============================
$blocked = [
    "hack", "malware", "phishing",
    "exploit", "bypass", "crack",
    "keylogger", "ransomware"
];

foreach ($blocked as $word) {
    if (stripos($userMessage, $word) !== false) {
        echo json_encode([
            "reply" => "I can’t help with harmful or illegal activities. I can explain cybersecurity concepts safely."
        ]);
        exit;
    }
}

// ===============================
// LOAD API KEY
// ===============================
$apiKey = getenv("OPENAI_API_KEY");
if (!$apiKey) {
    echo json_encode(["reply" => "AI service unavailable"]);
    exit;
}

// ===============================
// SYSTEM PROMPT (CRITICAL)
// ===============================
$systemPrompt = <<<PROMPT
You are a professional software and cybersecurity comparison analyst.

Rules you must follow:
- Only use information explicitly provided in the input.
- Do NOT invent features, pricing, or capabilities.
- If information is missing, clearly say it is not available.
- Do NOT provide hacking, malware, phishing, or exploit instructions.
- Focus on legitimate, well-known software only.
- Use a clear, structured, business-friendly format.
- End comparisons with a neutral recommendation.

Tone:
Professional, factual, concise.
PROMPT;

// ===============================
// OPENAI PAYLOAD
// ===============================
$payload = [
    "model" => "gpt-4o-mini",
    "max_output_tokens" => 400,
    "input" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => $userMessage]
    ]
];

// ===============================
// CURL REQUEST
// ===============================
$ch = curl_init("https://api.openai.com/v1/responses");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        "Content-Type: application/json",
        "Authorization: Bearer " . $apiKey
    ],
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);

// Robust extraction for Responses API
$reply = null;

if (isset($data["output_text"])) {
    $reply = $data["output_text"];
} elseif (isset($data["output"][0]["content"][0]["text"])) {
    $reply = $data["output"][0]["content"][0]["text"];
}

if (!$reply) {
    echo json_encode([
        "reply" => "Live AI is temporarily unavailable. Please try again later."
    ]);
    exit;
}

// ===============================
// FINAL RESPONSE
// ===============================
echo json_encode(["reply" => $reply]);
exit;
