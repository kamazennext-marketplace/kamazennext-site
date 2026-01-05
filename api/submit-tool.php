<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

function respondError(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$toolName = trim($_POST['tool_name'] ?? '');
$toolUrl = trim($_POST['tool_url'] ?? '');
$category = trim($_POST['category'] ?? '');
$message = trim($_POST['message'] ?? '');
$honeypot = trim($_POST['company'] ?? '');
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$timestamp = date('c');
$adminEmail = 'support@kamazennext.com';

if ($honeypot !== '') {
    respondError('Invalid submission.');
}

if ($name === '' || $email === '' || $toolName === '' || $toolUrl === '') {
    respondError('Please fill in all required fields.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respondError('Please enter a valid email.');
}

if (!filter_var($toolUrl, FILTER_VALIDATE_URL)) {
    respondError('Please enter a valid URL.');
}

$baseDir = dirname(__DIR__);
$limitsDir = $baseDir . '/data/limits';
$submissionsDir = $baseDir . '/data/submissions';

foreach ([$limitsDir, $submissionsDir] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

$limitKey = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip ?: 'unknown');
$limitFile = $limitsDir . '/' . $limitKey . '.json';
$now = time();
$windowStart = $now - 3600;
$history = [];

if (file_exists($limitFile)) {
    $json = file_get_contents($limitFile);
    $decoded = json_decode($json, true);
    if (is_array($decoded)) {
        $history = array_filter($decoded, fn($t) => is_int($t) && $t >= $windowStart);
    }
}

if (count($history) >= 5) {
    respondError('Too many submissions. Please try again later.', 429);
}

$history[] = $now;
file_put_contents($limitFile, json_encode(array_values($history)), LOCK_EX);

$record = [
    'timestamp' => $timestamp,
    'ip' => $ip,
    'name' => $name,
    'email' => $email,
    'tool_name' => $toolName,
    'tool_url' => $toolUrl,
    'category' => $category,
    'message' => $message,
];

$month = date('Y-m');
$csvFile = "$submissionsDir/submissions-$month.csv";
$jsonlFile = "$submissionsDir/submissions-$month.jsonl";

$csvHandle = fopen($csvFile, file_exists($csvFile) ? 'a' : 'w');
if ($csvHandle) {
    if (flock($csvHandle, LOCK_EX)) {
        if (ftell($csvHandle) === 0) {
            fputcsv($csvHandle, array_keys($record));
        }
        fputcsv($csvHandle, $record);
        fflush($csvHandle);
        flock($csvHandle, LOCK_UN);
    }
    fclose($csvHandle);
}

file_put_contents($jsonlFile, json_encode($record) . "\n", FILE_APPEND | LOCK_EX);

if (function_exists('mail') && $adminEmail) {
    $subject = "New tool submission: {$toolName}";
    $body = "New tool submitted at {$timestamp}\n\n" .
        "Name: {$name}\n" .
        "Email: {$email}\n" .
        "Tool: {$toolName}\n" .
        "URL: {$toolUrl}\n" .
        "Category: {$category}\n" .
        "Message: {$message}\n" .
        "IP: {$ip}\n";

    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: text/plain; charset=UTF-8";
    $headers[] = "From: Kama ZenNext <no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ">";
    $headers[] = "Reply-To: " . ($email ?: 'no-reply@example.com');

    @mail($adminEmail, $subject, $body, implode("\r\n", $headers));
}

echo json_encode(['ok' => true]);
