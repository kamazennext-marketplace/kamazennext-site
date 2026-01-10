<?php

declare(strict_types=1);

/**
 * Affiliate redirect + click tracking
 * URL format: /out.php?slug=tool-slug&from=product
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function respond(int $code, string $msg): void
{
    http_response_code($code);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
    exit;
}

function respondNotFound(): void
{
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Not Found</title></head><body><h1>Not Found</h1><p>The requested tool could not be found.</p></body></html>';
    exit;
}

function appendQuery(string $url, array $params): string
{
    $parts = parse_url($url);
    $q = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $q);
    }

    foreach ($params as $k => $v) {
        if ($v === null || $v === '') {
            continue;
        }
        if (!isset($q[$k]) || $q[$k] === '') {
            $q[$k] = $v;
        }
    }

    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'] ?? '';
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';
    $path = $parts['path'] ?? '/';
    $frag = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

    $newQuery = http_build_query($q);
    return $scheme . '://' . $host . $port . $path . ($newQuery ? '?' . $newQuery : '') . $frag;
}

$slug = strtolower(trim((string) ($_GET['slug'] ?? '')));
if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    respond(400, 'Invalid slug');
}

$fromRaw = trim((string) ($_GET['from'] ?? ''));
$fromPage = $fromRaw !== '' ? substr($fromRaw, 0, 50) : null;

$dbPath = __DIR__ . '/admin/db.php';
if (!file_exists($dbPath)) {
    error_log('out.php: db.php not found at: ' . $dbPath);
    respond(500, 'Server misconfigured');
}

require_once $dbPath;

if (!isset($conn) || !($conn instanceof mysqli)) {
    error_log('out.php: mysqli $conn not set by db.php');
    respond(500, 'Server misconfigured');
}

$stmt = $conn->prepare('SELECT slug, website_url, affiliate_url, category FROM products WHERE slug = ? LIMIT 1');
if (!$stmt) {
    error_log('out.php: prepare failed: ' . $conn->error);
    respond(500, 'Server error');
}

$stmt->bind_param('s', $slug);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$row) {
    respondNotFound();
}

$websiteUrl = trim((string) ($row['website_url'] ?? ''));
$affiliateUrl = trim((string) ($row['affiliate_url'] ?? ''));
$category = trim((string) ($row['category'] ?? ''));
$target = $affiliateUrl !== '' ? $affiliateUrl : $websiteUrl;

if ($target === '') {
    respondNotFound();
}

$utmCampaign = $fromRaw !== '' ? $fromRaw : $category;
$targetFinal = appendQuery($target, [
    'utm_source' => 'kamazennext',
    'utm_medium' => 'referral',
    'utm_campaign' => $utmCampaign,
    'utm_content' => $slug
]);

$referrer = substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
$userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$ipbin = inet_pton($ip) ?: null;

$insert = $conn->prepare('INSERT INTO clicks (slug, from_page, referrer, target_url, ip, user_agent) VALUES (?,?,?,?,?,?)');
if ($insert) {
    $insert->bind_param('ssssss', $slug, $fromPage, $referrer, $targetFinal, $ipbin, $userAgent);
    $insert->execute();
    $insert->close();
} else {
    error_log('out.php: clicks insert prepare failed: ' . $conn->error);
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Location: ' . $targetFinal, true, 302);
exit;
