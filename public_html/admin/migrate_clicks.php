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
    header('WWW-Authenticate: Basic realm="Clicks Migration"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

require_once __DIR__ . '/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$messages = [];
$errors = [];

$dbNameResult = $conn->query('SELECT DATABASE() as db_name');
$dbRow = $dbNameResult ? $dbNameResult->fetch_assoc() : null;
$dbName = $dbRow['db_name'] ?? '';

if ($dbName === '') {
    $errors[] = 'Unable to determine database name.';
} else {
    $createClicks = <<<SQL
CREATE TABLE IF NOT EXISTS clicks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  slug VARCHAR(150) NOT NULL,
  from_page VARCHAR(50) NULL,
  referrer VARCHAR(255) NULL,
  target_url TEXT NULL,
  ip VARBINARY(16) NULL,
  user_agent VARCHAR(255) NULL,
  INDEX (slug),
  INDEX (created_at)
);
SQL;

    if (!$conn->query($createClicks)) {
        $errors[] = 'Unable to create clicks table: ' . $conn->error;
    } else {
        $messages[] = 'Clicks table is ready.';
    }

    $desiredColumns = [
        'website_url' => 'TEXT NULL',
        'affiliate_url' => 'TEXT NULL',
        'platforms' => 'VARCHAR(255) NULL',
        'featured_rank' => 'INT NULL',
        'tagline' => 'VARCHAR(255) NULL'
    ];

    $columnQuery = $conn->prepare(
        'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?'
    );

    if ($columnQuery === false) {
        $errors[] = 'Unable to prepare column inspection query.';
    } else {
        $tableName = 'products';
        $columnQuery->bind_param('ss', $dbName, $tableName);
        if ($columnQuery->execute()) {
            $result = $columnQuery->get_result();
            $existingColumns = [];
            while ($row = $result->fetch_assoc()) {
                $existingColumns[$row['COLUMN_NAME']] = true;
            }

            foreach ($desiredColumns as $column => $definition) {
                if (!isset($existingColumns[$column])) {
                    $alterSql = "ALTER TABLE products ADD COLUMN {$column} {$definition}";
                    if ($conn->query($alterSql)) {
                        $messages[] = "Added column: {$column}";
                    } else {
                        $errors[] = "Failed to add column {$column}: " . $conn->error;
                    }
                }
            }
            if (count($messages) === 1) {
                $messages[] = 'No additional product columns were needed.';
            }
        } else {
            $errors[] = 'Unable to inspect products table columns.';
        }
        $columnQuery->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Clicks Migration</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    .success { background: #e6ffed; border: 1px solid #9ad2a6; }
  </style>
</head>
<body>
  <h1>Clicks Migration</h1>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <?php foreach ($messages as $message): ?>
    <div class="message success"><?php echo h($message); ?></div>
  <?php endforeach; ?>
</body>
</html>
