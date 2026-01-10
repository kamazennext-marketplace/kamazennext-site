<?php

declare(strict_types=1);

require_once __DIR__ . '/admin/db.php';

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = trim((string) ($_GET['id'] ?? ''));
$from = trim((string) ($_GET['from'] ?? ''));

if ($slug === '' && $id === '') {
    http_response_code(404);
    echo 'Missing product identifier.';
    exit;
}

$fromPage = preg_replace('/[^a-z0-9_-]+/i', '', $from);
$fromPage = substr($fromPage, 0, 64);

$stmt = null;
if ($slug !== '') {
    $stmt = $conn->prepare('SELECT id, slug, name, category, website_url, affiliate_url FROM products WHERE slug = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $slug);
    }
} else {
    $idValue = (int) $id;
    $stmt = $conn->prepare('SELECT id, slug, name, category, website_url, affiliate_url FROM products WHERE id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('i', $idValue);
    }
}

if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    echo 'Catalog unavailable.';
    exit;
}

$result = $stmt->get_result();
$product = $result ? $result->fetch_assoc() : null;
$stmt->close();

if (!$product) {
    http_response_code(404);
    echo 'Product not found.';
    exit;
}

$destination = (string) ($product['affiliate_url'] ?? '');
if ($destination === '') {
    $destination = (string) ($product['website_url'] ?? '');
}

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
$productSlug = $slugify((string) ($product['slug'] ?? $product['name'] ?? (string) ($product['id'] ?? 'tool'))) ?: 'tool';

$parts = parse_url($destination);
if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
    http_response_code(400);
    echo 'Invalid destination.';
    exit;
}

if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
    http_response_code(400);
    echo 'Invalid destination.';
    exit;
}

$query = [];
if (!empty($parts['query'])) {
    parse_str($parts['query'], $query);
}

$utmCampaign = $fromPage !== '' ? $fromPage : $categorySlug;

$query['utm_source'] = 'kamazennext';
$query['utm_medium'] = 'affiliate';
$query['utm_campaign'] = $utmCampaign;
$query['utm_content'] = $productSlug;

$rebuiltQuery = http_build_query($query);
$finalUrl = $parts['scheme'] . '://' . $parts['host']
    . (!empty($parts['port']) ? ':' . $parts['port'] : '')
    . ($parts['path'] ?? '')
    . ($rebuiltQuery ? '?' . $rebuiltQuery : '')
    . (!empty($parts['fragment']) ? '#' . $parts['fragment'] : '');

try {
    $salt = (string) (getenv('CLICK_SALT') ?: 'kz_click_salt_v1');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = hash('sha256', $salt . $ip);

    $insert = $conn->prepare('INSERT INTO clicks
        (product_id, referrer, user_agent, ip_hash, from_page, dest_url, utm_campaign, utm_content)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)');

    if ($insert) {
        $productId = (int) ($product['id'] ?? 0);
        $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $insert->bind_param(
            'isssssss',
            $productId,
            $referrer,
            $userAgent,
            $ipHash,
            $fromPage,
            $finalUrl,
            $utmCampaign,
            $productSlug
        );
        $insert->execute();
        $insert->close();
    }
} catch (Throwable $e) {
    // Ignore logging errors to avoid interrupting redirects.
}

header('Location: ' . $finalUrl, true, 302);
exit;
