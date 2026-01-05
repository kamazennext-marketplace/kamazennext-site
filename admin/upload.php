<?php
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

$allowedExtensions = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported file type.']);
    exit;
}

$logosDir = __DIR__ . '/../assets/logos';
if (!is_dir($logosDir)) {
    mkdir($logosDir, 0777, true);
}

$baseName = preg_replace('/[^a-zA-Z0-9-_\.]/', '', pathinfo($file['name'], PATHINFO_FILENAME));
if ($baseName === '') {
    $baseName = 'logo';
}
$targetName = $baseName . '.' . $extension;
$targetPath = $logosDir . '/' . $targetName;
$counter = 1;
while (file_exists($targetPath)) {
    $targetName = $baseName . '-' . $counter . '.' . $extension;
    $targetPath = $logosDir . '/' . $targetName;
    $counter++;
}

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save file.']);
    exit;
}

$publicPath = '/assets/logos/' . $targetName;
echo json_encode(['path' => $publicPath]);
