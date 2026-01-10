<?php
/**
 * Admin Bulk CSV Import (MySQL)
 * - Upload CSV, preview rows, validate, and upsert into products table.
 * - Protected by env ADMIN_PASSWORD.
 */

declare(strict_types=1);

session_start();

$adminPassword = getenv('ADMIN_PASSWORD');
if (!$adminPassword || $adminPassword === 'CHANGE_ME_STRONG_PASSWORD') {
    http_response_code(500);
    echo 'Admin password not configured. Set ADMIN_PASSWORD in .htaccess.';
    exit;
}

if (!isset($_SERVER['PHP_AUTH_PW']) || !hash_equals($adminPassword, (string) $_SERVER['PHP_AUTH_PW'])) {
    header('WWW-Authenticate: Basic realm="Bulk Import"');
    header('HTTP/1.0 401 Unauthorized');
    echo 'Authentication required.';
    exit;
}

require_once __DIR__ . '/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$maxFileSize = 2 * 1024 * 1024;
$errors = [];
$status = '';
$previewRows = [];
$summary = [];

$csrfToken = $_SESSION['csrf_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$requiredColumns = ['name', 'category', 'website_url'];
$optionalColumns = [
    'slug',
    'tagline',
    'pricing_model',
    'api_available',
    'platforms',
    'affiliate_url',
    'featured_rank',
    'updated_at',
    'last_updated'
];

$slugify = static function (string $value): string {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
};

$getProductColumns = static function (mysqli $conn): array {
    $result = $conn->query('SELECT DATABASE() as db_name');
    $row = $result ? $result->fetch_assoc() : null;
    $dbName = $row['db_name'] ?? '';
    if ($dbName === '') {
        return [];
    }

    $stmt = $conn->prepare('SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?');
    if (!$stmt) {
        return [];
    }
    $tableName = 'products';
    $stmt->bind_param('ss', $dbName, $tableName);
    $stmt->execute();
    $result = $stmt->get_result();
    $columns = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['COLUMN_NAME']] = true;
        }
    }
    $stmt->close();
    return $columns;
};

$columns = $getProductColumns($conn);

$normalizeBoolean = static function (string $value): ?int {
    if ($value === '') {
        return null;
    }
    $normalized = strtolower($value);
    return in_array($normalized, ['1', 'true', 'yes', 'y'], true) ? 1 : 0;
};

$bindValues = static function (mysqli_stmt $stmt, string $types, array $values): void {
    if ($types === '') {
        return;
    }
    $stmt->bind_param($types, ...$values);
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'preview') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed. Please choose a valid CSV file.';
            } elseif ($_FILES['csv_file']['size'] > $maxFileSize) {
                $errors[] = 'File too large. Limit is 2MB.';
            } else {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
                if ($handle === false) {
                    $errors[] = 'Unable to read uploaded file.';
                } else {
                    $headers = fgetcsv($handle);
                    if ($headers === false) {
                        $errors[] = 'CSV file is empty.';
                    } else {
                        $headerMap = [];
                        foreach ($headers as $index => $header) {
                            $key = strtolower(trim((string) $header));
                            if ($key !== '') {
                                $headerMap[$key] = $index;
                            }
                        }

                        foreach ($requiredColumns as $column) {
                            if (!array_key_exists($column, $headerMap)) {
                                $errors[] = 'Missing required column: ' . $column;
                            }
                        }

                        if (empty($errors)) {
                            $rows = [];
                            $lineNumber = 2;
                            while (($data = fgetcsv($handle)) !== false) {
                                if (count(array_filter($data, static fn($value) => trim((string) $value) !== '')) === 0) {
                                    $lineNumber++;
                                    continue;
                                }

                                $row = [];
                                foreach (array_merge($requiredColumns, $optionalColumns) as $column) {
                                    if (array_key_exists($column, $headerMap)) {
                                        $row[$column] = trim((string) ($data[$headerMap[$column]] ?? ''));
                                    }
                                }

                                foreach ($requiredColumns as $column) {
                                    if (($row[$column] ?? '') === '') {
                                        $errors[] = "Row {$lineNumber}: missing {$column}.";
                                    }
                                }

                                $row['category'] = $row['category'] !== '' ? $row['category'] : 'Automation';
                                $row['slug'] = $row['slug'] !== '' ? $row['slug'] : $slugify($row['name'] ?? '');
                                $rows[] = $row;
                                $lineNumber++;
                            }

                            if (empty($rows)) {
                                $errors[] = 'No data rows found.';
                            }

                            if (empty($errors)) {
                                $_SESSION['bulk_import_rows'] = $rows;
                                $importToken = bin2hex(random_bytes(16));
                                $_SESSION['bulk_import_token'] = $importToken;
                                $previewRows = array_slice($rows, 0, 10);
                                $summary = [
                                    'total' => count($rows),
                                    'token' => $importToken
                                ];
                            }
                        }
                    }
                    fclose($handle);
                }
            }
        }

        if ($action === 'import' && empty($errors)) {
            $importToken = (string) ($_POST['import_token'] ?? '');
            $storedToken = (string) ($_SESSION['bulk_import_token'] ?? '');
            if ($importToken === '' || !hash_equals($storedToken, $importToken)) {
                $errors[] = 'Import token mismatch. Please preview again.';
            } else {
                $rows = $_SESSION['bulk_import_rows'] ?? [];
                if (!is_array($rows) || empty($rows)) {
                    $errors[] = 'No rows available for import.';
                } else {
                    $allowedColumns = array_intersect(
                        array_keys($columns),
                        ['name', 'slug', 'category', 'tagline', 'pricing_model', 'api_available', 'platforms', 'website_url', 'affiliate_url', 'featured_rank', 'updated_at', 'last_updated']
                    );

                    if (empty($allowedColumns)) {
                        $errors[] = 'Products table not ready. Run migration first.';
                    } else {
                        $created = 0;
                        $updated = 0;
                        $rowErrors = [];
                        $timestamp = date('Y-m-d H:i:s');

                        $selectBySlug = $conn->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
                        $selectByName = $conn->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');

                        foreach ($rows as $index => $row) {
                            $lineNumber = $index + 2;
                            $data = [
                                'name' => $row['name'] ?? '',
                                'slug' => $row['slug'] ?? $slugify($row['name'] ?? ''),
                                'category' => $row['category'] ?? 'Automation',
                                'tagline' => $row['tagline'] ?? null,
                                'pricing_model' => $row['pricing_model'] ?? null,
                                'api_available' => $normalizeBoolean($row['api_available'] ?? ''),
                                'platforms' => $row['platforms'] ?? null,
                                'website_url' => $row['website_url'] ?? null,
                                'affiliate_url' => $row['affiliate_url'] ?? null,
                                'featured_rank' => ($row['featured_rank'] ?? '') !== '' ? (int) $row['featured_rank'] : null,
                                'updated_at' => ($row['updated_at'] ?? '') !== '' ? $row['updated_at'] : $timestamp,
                                'last_updated' => ($row['last_updated'] ?? '') !== '' ? $row['last_updated'] : $timestamp
                            ];

                            if ($data['name'] === '' || $data['website_url'] === '') {
                                $rowErrors[] = "Row {$lineNumber}: missing required fields.";
                                continue;
                            }

                            $matchId = null;
                            if ($data['slug'] !== '' && $selectBySlug) {
                                $selectBySlug->bind_param('s', $data['slug']);
                                if ($selectBySlug->execute()) {
                                    $result = $selectBySlug->get_result();
                                    $match = $result ? $result->fetch_assoc() : null;
                                    $matchId = $match['id'] ?? null;
                                }
                            }

                            if ($matchId === null && $selectByName) {
                                $selectByName->bind_param('s', $data['name']);
                                if ($selectByName->execute()) {
                                    $result = $selectByName->get_result();
                                    $match = $result ? $result->fetch_assoc() : null;
                                    $matchId = $match['id'] ?? null;
                                }
                            }

                            $insertColumns = [];
                            $insertValues = [];
                            $insertTypes = '';

                            foreach ($allowedColumns as $column) {
                                if (!array_key_exists($column, $data)) {
                                    continue;
                                }
                                $insertColumns[] = $column;
                                $insertValues[] = $data[$column];
                                $insertTypes .= in_array($column, ['api_available', 'featured_rank'], true) ? 'i' : 's';
                            }

                            if ($matchId !== null) {
                                $updateColumns = $insertColumns;
                                $setParts = array_map(static fn($col) => "{$col} = ?", $updateColumns);
                                $updateSql = 'UPDATE products SET ' . implode(', ', $setParts) . ' WHERE id = ?';
                                $updateStmt = $conn->prepare($updateSql);
                                if (!$updateStmt) {
                                    $rowErrors[] = "Row {$lineNumber}: unable to prepare update.";
                                    continue;
                                }
                                $updateValues = $insertValues;
                                $updateValues[] = (int) $matchId;
                                $updateTypes = $insertTypes . 'i';
                                $bindValues($updateStmt, $updateTypes, $updateValues);
                                if ($updateStmt->execute()) {
                                    $updated++;
                                } else {
                                    $rowErrors[] = "Row {$lineNumber}: update failed.";
                                }
                                $updateStmt->close();
                            } else {
                                $placeholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                                $insertSql = 'INSERT INTO products (' . implode(', ', $insertColumns) . ') VALUES (' . $placeholders . ')';
                                $insertStmt = $conn->prepare($insertSql);
                                if (!$insertStmt) {
                                    $rowErrors[] = "Row {$lineNumber}: unable to prepare insert.";
                                    continue;
                                }
                                $bindValues($insertStmt, $insertTypes, $insertValues);
                                if ($insertStmt->execute()) {
                                    $created++;
                                } else {
                                    $rowErrors[] = "Row {$lineNumber}: insert failed.";
                                }
                                $insertStmt->close();
                            }
                        }

                        if ($selectBySlug) {
                            $selectBySlug->close();
                        }
                        if ($selectByName) {
                            $selectByName->close();
                        }

                        if (!empty($rowErrors)) {
                            $errors = array_merge($errors, $rowErrors);
                        } else {
                            $status = "Import complete. Created {$created}, updated {$updated}.";
                            unset($_SESSION['bulk_import_rows'], $_SESSION['bulk_import_token']);
                        }
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bulk Import (CSV)</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    .success { background: #e6ffed; border: 1px solid #9ad2a6; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
    th { background: #f5f5f5; }
    label { display: block; margin-top: 0.75rem; font-weight: 600; }
    input[type=file] { margin-top: 0.5rem; }
    .actions { margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Bulk Import (CSV)</h1>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <?php if ($status !== ''): ?>
    <div class="message success"><?php echo h($status); ?></div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
    <input type="hidden" name="action" value="preview">
    <label for="csv_file">Upload CSV</label>
    <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
    <div class="actions">
      <button type="submit">Preview</button>
    </div>
  </form>

  <?php if (!empty($previewRows)): ?>
    <h2>Preview (first 10 rows)</h2>
    <table>
      <thead>
        <tr>
          <?php foreach (array_merge($requiredColumns, $optionalColumns) as $column): ?>
            <th><?php echo h($column); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($previewRows as $row): ?>
          <tr>
            <?php foreach (array_merge($requiredColumns, $optionalColumns) as $column): ?>
              <td><?php echo h((string) ($row[$column] ?? '')); ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
      <input type="hidden" name="action" value="import">
      <input type="hidden" name="import_token" value="<?php echo h((string) ($summary['token'] ?? '')); ?>">
      <div class="actions">
        <button type="submit">Import <?php echo h((string) ($summary['total'] ?? 0)); ?> rows</button>
      </div>
    </form>
  <?php endif; ?>
</body>
</html>
