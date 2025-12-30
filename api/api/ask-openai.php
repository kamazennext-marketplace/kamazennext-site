<?php
/**
 * OpenAI helper for the AI chat endpoint.
 *
 * The API key should be stored at /home2/kamazennext/.secrets/openai_key.txt on the server
 * (outside public_html) or provided via the OPENAI_API_KEY environment variable.
 */
function kz_ask_openai(string $message, array $history = []): string
{
    $trimmedMessage = trim($message);
    if ($trimmedMessage === '') {
        http_response_code(503);
        throw new Exception('Missing message');
    }

    $apiKey = null;
    $keyFile = '/home2/kamazennext/.secrets/openai_key.txt';

    if (is_readable($keyFile)) {
        $fileContents = trim((string)file_get_contents($keyFile));
        if ($fileContents !== '') {
            $apiKey = $fileContents;
        }
    }

    if (!$apiKey) {
        $envKey = getenv('OPENAI_API_KEY');
        if ($envKey) {
            $apiKey = trim($envKey);
        }
    }

    if (!$apiKey) {
        http_response_code(503);
        throw new Exception('OpenAI API key not configured');
    }

    $model = getenv('OPENAI_MODEL') ?: 'gpt-4.1-mini';

    $inputMessages = [];
    foreach ($history as $item) {
        if (!is_array($item)) {
            continue;
        }

        $role = $item['role'] ?? null;
        $content = $item['content'] ?? null;

        if (is_string($role) && is_string($content) && trim($content) !== '') {
            $inputMessages[] = [
                'role' => $role,
                'content' => $content,
            ];
        }
    }

    $inputMessages[] = [
        'role' => 'user',
        'content' => $trimmedMessage,
    ];

    $payload = [
        'model' => $model,
        'input' => $inputMessages,
        'max_output_tokens' => 500,
        'temperature' => 0.7,
    ];

    $ch = curl_init('https://api.openai.com/v1/responses');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        http_response_code(503);
        throw new Exception('Failed to contact OpenAI: ' . $curlError);
    }

    $data = json_decode($response, true);

    if ($statusCode >= 400 || !is_array($data)) {
        http_response_code(503);
        $errorMessage = is_array($data) && isset($data['error']['message'])
            ? $data['error']['message']
            : 'Unknown error from OpenAI';
        throw new Exception($errorMessage);
    }

    $reply = null;

    if (isset($data['output_text'])) {
        $reply = $data['output_text'];
    } elseif (isset($data['output'][0]['content'][0]['text'])) {
        $reply = $data['output'][0]['content'][0]['text'];
    }

    if (!is_string($reply) || trim($reply) === '') {
        http_response_code(503);
        throw new Exception('No reply returned from OpenAI');
    }

    return $reply;
}
