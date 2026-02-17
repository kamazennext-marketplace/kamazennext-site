<?php
$slug = trim((string) ($_GET['slug'] ?? ''));
if ($slug === '') {
    http_response_code(404);
    echo 'Missing slug.';
    exit;
}

$location = '/out.php?' . http_build_query([
    'slug' => $slug,
    'from' => 'go'
]);

header('Location: ' . $location, true, 302);
exit;
