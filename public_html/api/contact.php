<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
  exit;
}

// ---- CONFIG: set your receiving email here ----
$TO_EMAIL = 'support@kamazennext.com'; // <-- change this
$FROM_NAME = 'Kama ZenNext Website';
$SUBJECT_PREFIX = '[Contact] ';

// ---- Read JSON or form POST ----
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$input = [];

if (stripos($contentType, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $input = json_decode($raw, true) ?: [];
} else {
  $input = $_POST;
}

// ---- Honeypot (spam trap) ----
$honeypot = trim($input['website'] ?? '');
if ($honeypot !== '') {
  http_response_code(200);
  echo json_encode(['ok' => true]); // pretend success
  exit;
}

// ---- Basic rate limit per IP (20 per hour) ----
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rateDir = sys_get_temp_dir() . '/rate_limit_contact';
if (!is_dir($rateDir)) @mkdir($rateDir, 0700, true);

$key = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $ip);
$rateFile = $rateDir . "/{$key}.json";
$now = time();
$windowSeconds = 3600;
$maxRequests = 20;

$bucket = ['start' => $now, 'count' => 0];
if (file_exists($rateFile)) {
  $bucket = json_decode(file_get_contents($rateFile), true) ?: $bucket;
  if (($now - ($bucket['start'] ?? $now)) > $windowSeconds) {
    $bucket = ['start' => $now, 'count' => 0];
  }
}
$bucket['count'] = ($bucket['count'] ?? 0) + 1;
file_put_contents($rateFile, json_encode($bucket));

if ($bucket['count'] > $maxRequests) {
  http_response_code(429);
  echo json_encode(['ok' => false, 'error' => 'Too many requests. Try later.']);
  exit;
}

// ---- Validation ----
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$message = trim($input['message'] ?? '');

$errors = [];

if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 80) {
  $errors[] = 'Name must be 2–80 characters.';
}
if ($email === '' || mb_strlen($email) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  $errors[] = 'Please enter a valid email.';
}
if ($phone !== '' && mb_strlen($phone) > 30) {
  $errors[] = 'Phone is too long.';
}
if ($message === '' || mb_strlen($message) < 10 || mb_strlen($message) > 4000) {
  $errors[] = 'Message must be 10–4000 characters.';
}

if ($errors) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
  exit;
}

// ---- Build email ----
$safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
$safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
$safePhone = htmlspecialchars($phone, ENT_QUOTES, 'UTF-8');

$body =
"New contact form submission:\n\n" .
"Name: {$name}\n" .
"Email: {$email}\n" .
"Phone: {$phone}\n" .
"IP: {$ip}\n" .
"Time: " . date('c') . "\n\n" .
"Message:\n{$message}\n";

$subject = $SUBJECT_PREFIX . $name;

// ---- Send (mail() for now) ----
$headers = [];
$headers[] = "MIME-Version: 1.0";
$headers[] = "Content-Type: text/plain; charset=UTF-8";
$headers[] = "From: {$FROM_NAME} <no-reply@{$_SERVER['HTTP_HOST']}>";
$headers[] = "Reply-To: {$safeName} <{$safeEmail}>";

$ok = @mail($TO_EMAIL, $subject, $body, implode("\r\n", $headers));

if (!$ok) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Failed to send email (mail() error).']);
  exit;
}

echo json_encode(['ok' => true, 'message' => 'Message sent successfully.']);
