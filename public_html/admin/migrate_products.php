<?php

declare(strict_types=1);

session_start();

$adminPassword = getenv('ADMIN_PASSWORD');
if (!$adminPassword || $adminPassword === 'CHANGE_ME_STRONG_PASSWORD') {
    http_response_code(500);
    echo 'Admin password not configured. Set ADMIN_PASSWORD in .htaccess.';
    exit;
}

if (!isset($_SERVER['PHP_AUTH_PW']) || !hash_equals($adminPassword, (string) $_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Catalog Migration"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$messages = [];
$errors = [];

$catalogDb = __DIR__ . '/../data/catalog.sqlite';
$catalogDir = dirname($catalogDb);
if (!is_dir($catalogDir)) {
    mkdir($catalogDir, 0750, true);
}

try {
    $pdo = new PDO('sqlite:' . $catalogDb, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $pdo->exec('CREATE TABLE IF NOT EXISTS products (
        id TEXT PRIMARY KEY,
        name TEXT,
        category TEXT
    )');

    $columns = $pdo->query('PRAGMA table_info(products)')->fetchAll();
    $existing = [];
    foreach ($columns as $col) {
        $existing[$col['name']] = true;
    }

    $desired = [
        'website_url' => 'TEXT',
        'affiliate_url' => 'TEXT',
        'tagline' => 'TEXT',
        'platforms' => 'TEXT',
        'featured_rank' => 'INTEGER DEFAULT NULL'
    ];

    foreach ($desired as $column => $definition) {
        if (!isset($existing[$column])) {
            $pdo->exec("ALTER TABLE products ADD COLUMN {$column} {$definition}");
            $messages[] = "Added column: {$column}";
        }
    }

    if (empty($messages)) {
        $messages[] = 'No migration changes were needed.';
    }
} catch (Throwable $e) {
    $errors[] = 'Migration failed: ' . $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Catalog Migration</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    .success { background: #e6ffed; border: 1px solid #9ad2a6; }
  </style>
</head>
<body>
  <h1>Catalog Migration</h1>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <?php foreach ($messages as $message): ?>
    <div class="message success"><?php echo h($message); ?></div>
  <?php endforeach; ?>
</body>
</html>
