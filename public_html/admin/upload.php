<?php
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();
header('Content-Type: application/json');

if (empty($_SESSION['admin_authenticated'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_FILES['logo'])) {
    http_response_code(400);
    echo json_encode(['error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['logo'];
if (!empty($file['error'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Upload error']);
    exit;
}

$maxFileSize = 2 * 1024 * 1024; // 2MB
if (!empty($file['size']) && $file['size'] > $maxFileSize) {
    http_response_code(400);
    echo json_encode(['error' => 'File too large. Max 2MB.']);
    exit;
}

$allowedExtensions = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported file type.']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';

$allowedMimes = [
    'png' => ['image/png'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'webp' => ['image/webp'],
];

if ($extension === 'svg') {
    $svgContent = file_get_contents($file['tmp_name']);
    if ($svgContent === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid SVG content.']);
        exit;
    }

    if (!in_array($mime, ['image/svg+xml', 'text/xml', 'application/xml'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid SVG MIME type.']);
        exit;
    }

    if (preg_match('/<\s*script/i', $svgContent) || preg_match('/on[a-z]+\s*=\s*/i', $svgContent)) {
        http_response_code(400);
        echo json_encode(['error' => 'SVG contains potentially unsafe content.']);
        exit;
    }
} elseif (!in_array($mime, $allowedMimes[$extension] ?? [], true)) {
    http_response_code(400);
    echo json_encode(['error' => 'File MIME type does not match extension.']);
    exit;
}

$logosDir = __DIR__ . '/../assets/logos';
if (!is_dir($logosDir)) {
    mkdir($logosDir, 0777, true);
}

$randomSuffix = bin2hex(random_bytes(4));
$targetName = 'tool-logo-' . time() . '-' . $randomSuffix . '.' . $extension;
$targetPath = $logosDir . '/' . $targetName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file.']);
    exit;
}

$publicPath = '/assets/logos/' . $targetName;
echo json_encode(['path' => $publicPath]);
