<?php

declare(strict_types=1);

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = trim((string) ($_GET['id'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));

if ($slug === '' && $id === '') {
    http_response_code(404);
    echo 'Missing product identifier.';
    exit;
}

$productsFile = __DIR__ . '/data/products.json';
if (!file_exists($productsFile)) {
    $fallbackFile = __DIR__ . '/../data/products.json';
    if (file_exists($fallbackFile)) {
        $productsFile = $fallbackFile;
    }
}

if (!file_exists($productsFile)) {
    http_response_code(500);
    echo 'Catalog unavailable.';
    exit;
}

$products = json_decode((string) file_get_contents($productsFile), true);
if (!is_array($products)) {
    http_response_code(500);
    echo 'Catalog unreadable.';
    exit;
}

$match = $slug !== '' ? $slug : $id;
$product = null;
foreach ($products as $candidate) {
    if (!is_array($candidate)) {
        continue;
    }
    $candidateId = (string) ($candidate['id'] ?? '');
    $candidateSlug = (string) ($candidate['slug'] ?? '');
    if ($match === $candidateId || $match === $candidateSlug) {
        $product = $candidate;
        break;
    }
}

if (!$product) {
    http_response_code(404);
    echo 'Product not found.';
    exit;
}

$destination = (string) ($product['affiliate_url'] ?? $product['website_url'] ?? $product['website'] ?? '');
if ($destination === '') {
    http_response_code(404);
    echo 'Destination unavailable.';
    exit;
}

$slugify = static function (string $value): string {
    $slugged = strtolower(trim($value));
    $slugged = preg_replace('/[^a-z0-9]+/', '-', $slugged) ?? '';
    return trim($slugged, '-');
};

$categorySlug = $slugify((string) ($product['category'] ?? 'automation')) ?: 'automation';
$productSlug = $slugify((string) ($product['slug'] ?? $product['id'] ?? $product['name'] ?? 'tool')) ?: 'tool';

$parts = parse_url($destination);
if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
    http_response_code(400);
    echo 'Invalid destination.';
    exit;
}

$query = [];
if (!empty($parts['query'])) {
    parse_str($parts['query'], $query);
}

$query += [
    'utm_source' => 'kamazennext',
    'utm_medium' => 'affiliate',
    'utm_campaign' => $categorySlug,
    'utm_content' => $productSlug
];

$rebuiltQuery = http_build_query($query);
$finalUrl = $parts['scheme'] . '://' . $parts['host']
    . (!empty($parts['port']) ? ':' . $parts['port'] : '')
    . ($parts['path'] ?? '')
    . ($rebuiltQuery ? '?' . $rebuiltQuery : '')
    . (!empty($parts['fragment']) ? '#' . $parts['fragment'] : '');

$clickDb = __DIR__ . '/data/clicks.sqlite';
$clickDir = dirname($clickDb);
if (!is_dir($clickDir)) {
    mkdir($clickDir, 0750, true);
}

try {
    $pdo = new PDO('sqlite:' . $clickDb, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    $pdo->exec('CREATE TABLE IF NOT EXISTS clicks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        product_id TEXT NOT NULL,
        ts TEXT NOT NULL,
        referrer TEXT,
        user_agent TEXT,
        ip_hash TEXT,
        from_page TEXT,
        dest_url TEXT,
        utm_campaign TEXT,
        utm_content TEXT
    )');

    $salt = (string) (getenv('CLICK_SALT') ?: 'kz_click_salt_v1');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = hash('sha256', $salt . $ip);

    $stmt = $pdo->prepare('INSERT INTO clicks
        (product_id, ts, referrer, user_agent, ip_hash, from_page, dest_url, utm_campaign, utm_content)
        VALUES (:product_id, :ts, :referrer, :user_agent, :ip_hash, :from_page, :dest_url, :utm_campaign, :utm_content)');
    $stmt->execute([
        ':product_id' => (string) ($product['id'] ?? $productSlug),
        ':ts' => gmdate('c'),
        ':referrer' => (string) ($_SERVER['HTTP_REFERER'] ?? ''),
        ':user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
        ':ip_hash' => $ipHash,
        ':from_page' => $from,
        ':dest_url' => $finalUrl,
        ':utm_campaign' => $categorySlug,
        ':utm_content' => $productSlug
    ]);
} catch (Throwable $e) {
    // Logging failure should not block redirects.
}

header('Location: ' . $finalUrl, true, 302);
exit;
