<?php
declare(strict_types=1);

/**
 * Affiliate redirect + click tracking
 * URL format: /out.php?slug=tool-slug&from=product
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');

function respond(int $code, string $msg): void {
  http_response_code($code);
  header('Content-Type: text/plain; charset=utf-8');
  echo $msg;
  exit;
}

function tableExists(mysqli $conn, string $table): bool {
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1");
  $stmt->bind_param("s", $table);
  $stmt->execute();
  $res = $stmt->get_result();
  return (bool)$res->fetch_row();
}

function columnExists(mysqli $conn, string $table, string $column): bool {
  $stmt = $conn->prepare("SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1");
  $stmt->bind_param("ss", $table, $column);
  $stmt->execute();
  $res = $stmt->get_result();
  return (bool)$res->fetch_row();
}

function firstExistingColumn(mysqli $conn, string $table, array $candidates): ?string {
  foreach ($candidates as $col) {
    if (columnExists($conn, $table, $col)) return $col;
  }
  return null;
}

function appendQuery(string $url, array $params): string {
  $parts = parse_url($url);
  $q = [];
  if (!empty($parts['query'])) parse_str($parts['query'], $q);

  foreach ($params as $k => $v) {
    if ($v === null || $v === '') continue;
    if (!isset($q[$k]) || $q[$k] === '') $q[$k] = $v; // do not overwrite existing
  }

  $scheme = $parts['scheme'] ?? 'https';
  $host   = $parts['host'] ?? '';
  $port   = isset($parts['port']) ? ':' . $parts['port'] : '';
  $path   = $parts['path'] ?? '/';
  $frag   = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

  $newQuery = http_build_query($q);
  return $scheme . '://' . $host . $port . $path . ($newQuery ? '?' . $newQuery : '') . $frag;
}

// --- Input ---
$slug = $_GET['slug'] ?? '';
$slug = strtolower(trim($slug));
$slug = preg_replace('/[^a-z0-9\-]/', '', $slug);

if ($slug === '') respond(400, "Missing slug");

// --- DB include (IMPORTANT) ---
$dbPath = __DIR__ . '/admin/db.php';
if (!file_exists($dbPath)) {
  error_log("out.php: db.php not found at: " . $dbPath);
  respond(500, "Server misconfigured");
}

require_once $dbPath; // expects $conn

if (!isset($conn) || !($conn instanceof mysqli)) {
  error_log('out.php: mysqli $conn not set by db.php');
  respond(500, "Server misconfigured");
}

// --- Products table lookup ---
if (!tableExists($conn, 'products')) {
  error_log("out.php: products table missing");
  respond(500, "Server misconfigured");
}

$slugCol = firstExistingColumn($conn, 'products', ['slug', 'product_slug', 'handle']);
$urlCol  = firstExistingColumn($conn, 'products', ['affiliate_url', 'website_url', 'url', 'website', 'homepage_url']);

if (!$slugCol || !$urlCol) {
  error_log("out.php: products missing required columns. slugCol={$slugCol} urlCol={$urlCol}");
  respond(500, "Server misconfigured");
}

$sql = "SELECT `$urlCol` AS target_url FROM `products` WHERE `$slugCol` = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
  error_log("out.php prepare failed: " . $conn->error);
  respond(500, "Server error");
}
$stmt->bind_param("s", $slug);
$stmt->execute();
$res = $stmt->get_result();
$row = $res ? $res->fetch_assoc() : null;

$target = $row['target_url'] ?? '';
$target = trim((string)$target);

if ($target === '') respond(404, "Tool not found");

// Add UTMs (basic affiliate tracking)
$from = $_GET['from'] ?? 'site';
$targetFinal = appendQuery($target, [
  'utm_source'   => $_GET['utm_source'] ?? 'kamazennext',
  'utm_medium'   => $_GET['utm_medium'] ?? 'referral',
  'utm_campaign' => $_GET['utm_campaign'] ?? 'catalog',
  'utm_content'  => $slug,
  'utm_term'     => $_GET['utm_term'] ?? null,
  'from'         => $from,
]);

// Log click (only if clicks table exists)
if (tableExists($conn, 'clicks')) {
  $ref = substr((string)($_SERVER['HTTP_REFERER'] ?? ''), 0, 255);
  $ua  = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
  $ip  = (string)($_SERVER['REMOTE_ADDR'] ?? '');
  $ipbin = @inet_pton($ip); // VARBINARY(16)

  $ins = $conn->prepare("INSERT INTO clicks (slug, from_page, referrer, target_url, ip, user_agent) VALUES (?,?,?,?,?,?)");
  if ($ins) {
    $ins->bind_param("ssssss", $slug, $from, $ref, $targetFinal, $ipbin, $ua);
    $ins->execute();
  } else {
    error_log("out.php clicks insert prepare failed: " . $conn->error);
  }
}

// Redirect
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Location: " . $targetFinal, true, 302);
exit;
