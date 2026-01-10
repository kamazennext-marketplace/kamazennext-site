<?php

declare(strict_types=1);

session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    http_response_code(500);
    echo 'Database connection unavailable.';
    exit;
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function slugIsValid(string $slug): bool
{
    return $slug !== '' && preg_match('/^[a-z0-9-]+$/', $slug) === 1;
}

function sanitizeValue(?string $value): string
{
    return trim((string) $value);
}

function normalizeBoolean(string $value): ?int
{
    $normalized = strtolower(trim($value));
    if ($normalized === '') {
        return null;
    }
    if (in_array($normalized, ['1', 'true', 'yes', 'y'], true)) {
        return 1;
    }
    if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
        return 0;
    }
    return null;
}

function isValidUrl(string $value): bool
{
    return filter_var($value, FILTER_VALIDATE_URL) !== false;
}

$requiredColumns = ['name', 'slug', 'category', 'website_url'];
$optionalColumns = [
    'tagline',
    'pricing_model',
    'api_available',
    'platforms',
    'affiliate_url',
    'best_for',
    'key_features',
    'last_updated',
    'logo_url'
];

$maxFileSize = 2 * 1024 * 1024;
$errors = [];
$previewRows = [];
$summary = [];
$report = [];

$csrfToken = $_SESSION['import_tools_csrf'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['import_tools_csrf'] = $csrfToken;
}

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

                                $row = ['line' => $lineNumber];
                                foreach (array_merge($requiredColumns, $optionalColumns) as $column) {
                                    if (array_key_exists($column, $headerMap)) {
                                        $row[$column] = sanitizeValue($data[$headerMap[$column]] ?? '');
                                    }
                                }

                                $rowErrors = [];
                                foreach ($requiredColumns as $column) {
                                    if (($row[$column] ?? '') === '') {
                                        $rowErrors[] = "Missing {$column}.";
                                    }
                                }

                                if (($row['slug'] ?? '') !== '' && !slugIsValid($row['slug'])) {
                                    $rowErrors[] = 'Slug must be lower-kebab-case.';
                                }

                                if (($row['website_url'] ?? '') !== '' && !isValidUrl($row['website_url'])) {
                                    $rowErrors[] = 'Website URL is invalid.';
                                }

                                if (($row['affiliate_url'] ?? '') !== '' && !isValidUrl($row['affiliate_url'])) {
                                    $rowErrors[] = 'Affiliate URL is invalid.';
                                }

                                if (($row['logo_url'] ?? '') !== '' && !isValidUrl($row['logo_url'])) {
                                    $rowErrors[] = 'Logo URL is invalid.';
                                }

                                $row['errors'] = $rowErrors;
                                $rows[] = $row;
                                $lineNumber++;
                            }

                            if (empty($rows)) {
                                $errors[] = 'No data rows found.';
                            }

                            if (empty($errors)) {
                                $_SESSION['import_tools_rows'] = $rows;
                                $importToken = bin2hex(random_bytes(16));
                                $_SESSION['import_tools_token'] = $importToken;
                                $previewRows = array_slice($rows, 0, 20);
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
            $storedToken = (string) ($_SESSION['import_tools_token'] ?? '');
            if ($importToken === '' || !hash_equals($storedToken, $importToken)) {
                $errors[] = 'Import token mismatch. Please preview again.';
            } else {
                $rows = $_SESSION['import_tools_rows'] ?? [];
                if (!is_array($rows) || empty($rows)) {
                    $errors[] = 'No rows available for import.';
                } else {
                    $requiredDbColumns = ['name', 'slug', 'category', 'website_url'];
                    $allowedColumns = array_intersect(
                        array_keys($columns),
                        [
                            'name',
                            'slug',
                            'category',
                            'tagline',
                            'pricing_model',
                            'api_available',
                            'platforms',
                            'website_url',
                            'affiliate_url',
                            'best_for',
                            'key_features',
                            'last_updated',
                            'logo_url'
                        ]
                    );

                    foreach ($requiredDbColumns as $column) {
                        if (!in_array($column, $allowedColumns, true)) {
                            $errors[] = 'Products table missing required column: ' . $column;
                        }
                    }

                    if (empty($errors)) {
                        $inserted = 0;
                        $updated = 0;
                        $skipped = 0;
                        $skipReasons = [];

                        $selectStmt = $conn->prepare('SELECT id FROM products WHERE slug = ? LIMIT 1');
                        $now = date('Y-m-d H:i:s');

                        $insertColumns = $allowedColumns;
                        $insertPlaceholders = implode(', ', array_fill(0, count($insertColumns), '?'));
                        $insertSql = 'INSERT INTO products (' . implode(', ', $insertColumns) . ') VALUES (' . $insertPlaceholders . ')';
                        $insertStmt = $conn->prepare($insertSql);

                        $updateColumns = $allowedColumns;
                        $setParts = array_map(static fn($col) => $col . ' = ?', $updateColumns);
                        $updateSql = 'UPDATE products SET ' . implode(', ', $setParts) . ' WHERE slug = ?';
                        $updateStmt = $conn->prepare($updateSql);

                        foreach ($rows as $row) {
                            $lineNumber = (int) ($row['line'] ?? 0);
                            $rowErrors = $row['errors'] ?? [];

                            if (!empty($rowErrors)) {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: " . implode(' ', $rowErrors);
                                continue;
                            }

                            $data = [
                                'name' => sanitizeValue($row['name'] ?? ''),
                                'slug' => sanitizeValue($row['slug'] ?? ''),
                                'category' => sanitizeValue($row['category'] ?? ''),
                                'tagline' => sanitizeValue($row['tagline'] ?? ''),
                                'pricing_model' => sanitizeValue($row['pricing_model'] ?? ''),
                                'api_available' => normalizeBoolean($row['api_available'] ?? ''),
                                'platforms' => sanitizeValue($row['platforms'] ?? ''),
                                'website_url' => sanitizeValue($row['website_url'] ?? ''),
                                'affiliate_url' => sanitizeValue($row['affiliate_url'] ?? ''),
                                'best_for' => sanitizeValue($row['best_for'] ?? ''),
                                'key_features' => sanitizeValue($row['key_features'] ?? ''),
                                'last_updated' => sanitizeValue($row['last_updated'] ?? '') !== '' ? sanitizeValue($row['last_updated'] ?? '') : $now,
                                'logo_url' => sanitizeValue($row['logo_url'] ?? '')
                            ];

                            if ($data['name'] === '' || $data['slug'] === '' || $data['category'] === '' || $data['website_url'] === '') {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: missing required fields.";
                                continue;
                            }

                            if (!slugIsValid($data['slug'])) {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: slug must be lower-kebab-case.";
                                continue;
                            }

                            if (!isValidUrl($data['website_url'])) {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: website URL invalid.";
                                continue;
                            }

                            if ($data['affiliate_url'] !== '' && !isValidUrl($data['affiliate_url'])) {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: affiliate URL invalid.";
                                continue;
                            }

                            if ($data['logo_url'] !== '' && !isValidUrl($data['logo_url'])) {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: logo URL invalid.";
                                continue;
                            }

                            $matchId = null;
                            if ($selectStmt) {
                                $selectStmt->bind_param('s', $data['slug']);
                                if ($selectStmt->execute()) {
                                    $result = $selectStmt->get_result();
                                    $match = $result ? $result->fetch_assoc() : null;
                                    $matchId = $match['id'] ?? null;
                                }
                            }

                            $values = [];
                            $types = '';
                            foreach ($insertColumns as $column) {
                                $value = $data[$column] ?? null;
                                $values[] = $value;
                                $types .= $column === 'api_available' ? 'i' : 's';
                            }

                            if ($matchId !== null && $updateStmt) {
                                $updateValues = $values;
                                $updateValues[] = $data['slug'];
                                $updateTypes = $types . 's';
                                $updateStmt->bind_param($updateTypes, ...$updateValues);
                                if ($updateStmt->execute()) {
                                    $updated++;
                                } else {
                                    $skipped++;
                                    $skipReasons[] = "Row {$lineNumber}: update failed.";
                                }
                            } elseif ($insertStmt) {
                                $insertStmt->bind_param($types, ...$values);
                                if ($insertStmt->execute()) {
                                    $inserted++;
                                } else {
                                    $skipped++;
                                    $skipReasons[] = "Row {$lineNumber}: insert failed.";
                                }
                            } else {
                                $skipped++;
                                $skipReasons[] = "Row {$lineNumber}: database prepare failed.";
                            }
                        }

                        if ($selectStmt) {
                            $selectStmt->close();
                        }
                        if ($insertStmt) {
                            $insertStmt->close();
                        }
                        if ($updateStmt) {
                            $updateStmt->close();
                        }

                        $report = [
                            'inserted' => $inserted,
                            'updated' => $updated,
                            'skipped' => $skipped,
                            'reasons' => $skipReasons
                        ];

                        unset($_SESSION['import_tools_rows'], $_SESSION['import_tools_token']);
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
  <title>Import Tools (CSV)</title>
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
    .note { font-size: 0.9rem; color: #555; }
  </style>
</head>
<body>
  <h1>Import Tools (CSV)</h1>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <?php if (!empty($report)): ?>
    <div class="message success">
      Import complete. Inserted <?php echo h((string) $report['inserted']); ?>,
      updated <?php echo h((string) $report['updated']); ?>,
      skipped <?php echo h((string) $report['skipped']); ?>.
    </div>
  <?php endif; ?>

  <?php if (!empty($report['reasons'])): ?>
    <div class="message error">
      <strong>Skipped rows:</strong>
      <ul>
        <?php foreach ($report['reasons'] as $reason): ?>
          <li><?php echo h($reason); ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
    <input type="hidden" name="action" value="preview">
    <label for="csv_file">Upload CSV</label>
    <input id="csv_file" name="csv_file" type="file" accept=".csv,text/csv" required>
    <p class="note">Required columns: name, slug, category, website_url.</p>
    <div class="actions">
      <button type="submit">Preview</button>
    </div>
  </form>

  <?php if (!empty($previewRows)): ?>
    <h2>Preview (first 20 rows)</h2>
    <table>
      <thead>
        <tr>
          <?php foreach (array_merge($requiredColumns, $optionalColumns) as $column): ?>
            <th><?php echo h($column); ?></th>
          <?php endforeach; ?>
          <th>Issues</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($previewRows as $row): ?>
          <tr>
            <?php foreach (array_merge($requiredColumns, $optionalColumns) as $column): ?>
              <td><?php echo h((string) ($row[$column] ?? '')); ?></td>
            <?php endforeach; ?>
            <td><?php echo h(implode(' ', $row['errors'] ?? [])); ?></td>
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
