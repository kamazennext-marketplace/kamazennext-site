<?php
header('Content-Type: application/xml; charset=utf-8');
date_default_timezone_set('UTC');

$host = $_SERVER['HTTP_HOST'] ?? 'kamazennext.com';
$base = 'https://' . $host;
$today = date('Y-m-d');

$productsPath = __DIR__ . '/data/products.json';
$products = [];
if (file_exists($productsPath)) {
    $json = file_get_contents($productsPath);
    $products = json_decode($json, true) ?: [];
}

$slugify = function ($str) {
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', (string) $str), '-'));
    return $slug;
};

$categoryMeta = [];
foreach ($products as $product) {
    $category = $product['category'] ?? '';
    if (!$category) { continue; }
    $slug = $slugify($category);
    $lastUpdated = $product['last_updated'] ?? $today;
    if (!isset($categoryMeta[$slug])) {
        $categoryMeta[$slug] = ['name' => $category, 'lastmod' => $lastUpdated];
    } else {
        $existing = strtotime($categoryMeta[$slug]['lastmod']);
        $current = strtotime($lastUpdated);
        if ($current && $current > $existing) {
            $categoryMeta[$slug]['lastmod'] = $lastUpdated;
        }
    }
}

$formatDate = function ($date) use ($today) {
    $timestamp = strtotime($date);
    return $timestamp ? date('Y-m-d', $timestamp) : $today;
};

$urls = [
    ['loc' => $base . '/', 'lastmod' => $today],
    ['loc' => $base . '/software.html', 'lastmod' => $today],
    ['loc' => $base . '/compare.html', 'lastmod' => $today],
];

foreach ($categoryMeta as $slug => $meta) {
    $urls[] = [
        'loc' => $base . '/category/' . $slug,
        'lastmod' => $formatDate($meta['lastmod']),
    ];
}

foreach ($products as $product) {
    $urls[] = [
        'loc' => $base . '/p/' . rawurlencode($product['id']),
        'lastmod' => $formatDate($product['last_updated'] ?? $today),
    ];
}

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
echo "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

foreach ($urls as $url) {
    $loc = htmlspecialchars($url['loc'], ENT_QUOTES, 'UTF-8');
    $lastmod = htmlspecialchars($url['lastmod'], ENT_QUOTES, 'UTF-8');
    echo "  <url>\n";
    echo "    <loc>{$loc}</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "  </url>\n";
}

echo "</urlset>";
