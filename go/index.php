<?php
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    echo 'Missing slug.';
    exit;
}

$root = dirname(__DIR__);
$productsFile = $root . '/data/products.json';

if (!file_exists($productsFile)) {
    http_response_code(500);
    echo 'Catalog unavailable.';
    exit;
}

$products = json_decode(file_get_contents($productsFile), true);
if (!is_array($products)) {
    http_response_code(500);
    echo 'Catalog unreadable.';
    exit;
}

$product = null;
foreach ($products as $candidate) {
    $candidateId = (string) ($candidate['id'] ?? '');
    $candidateSlug = (string) ($candidate['slug'] ?? '');
    if ($candidateId === $slug || $candidateSlug === $slug) {
        $product = $candidate;
        break;
    }
}

if (!$product || empty($product['website'])) {
    http_response_code(404);
    echo 'Product not found.';
    exit;
}

$website = $product['website'];

$clickDir = $root . '/data/clicks';
if (!is_dir($clickDir)) {
    mkdir($clickDir, 0777, true);
}

$salt = 'kz_click_salt_v1';
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ipHash = hash('sha256', $salt . $ip);
$userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
$uaShort = substr($userAgent, 0, 180);
$referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');

$logEntry = [
    'time' => gmdate('c'),
    'slug' => $slug,
    'ip_hash' => $ipHash,
    'ua_short' => $uaShort,
    'referrer' => $referrer,
];

$logFile = $clickDir . '/clicks-' . gmdate('Y-m') . '.jsonl';
file_put_contents($logFile, json_encode($logEntry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND | LOCK_EX);

header('Location: ' . $website, true, 302);
exit;
