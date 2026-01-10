<?php
/**
 * Admin Bulk CSV Import
 * - Upload CSV, preview rows, validate, and import into products.json with upsert.
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

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$productsFile = __DIR__ . '/../data/products.json';
if (!file_exists($productsFile)) {
    $fallbackFile = __DIR__ . '/../../data/products.json';
    if (file_exists($fallbackFile)) {
        $productsFile = $fallbackFile;
    }
}

$products = [];
if (file_exists($productsFile)) {
    $raw = file_get_contents($productsFile);
    $decoded = $raw ? json_decode($raw, true) : null;
    if (is_array($decoded)) {
        $products = $decoded;
    }
}

$errors = [];
$status = '';
$previewRows = [];
$summary = [];

$csrfToken = $_SESSION['csrf_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$requiredColumns = ['name', 'category', 'tagline', 'website_url'];
$optionalColumns = [
    'slug',
    'pricing_model',
    'api_available',
    'platforms',
    'affiliate_url',
    'featured_rank',
    'last_updated'
];

$slugify = static function (string $value): string {
    $slug = strtolower(trim($value));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'preview') {
            if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'Upload failed. Please choose a valid CSV file.';
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
                    $existingBySlug = [];
                    $existingByName = [];
                    $existingIds = [];

                    foreach ($products as $index => $product) {
                        if (!is_array($product)) {
                            continue;
                        }
                        $existingId = (string) ($product['id'] ?? '');
                        if ($existingId !== '') {
                            $existingIds[$existingId] = true;
                        }
                        $productSlug = strtolower((string) ($product['slug'] ?? $product['id'] ?? ''));
                        if ($productSlug !== '') {
                            $existingBySlug[$productSlug] = $index;
                        }
                        $productName = strtolower((string) ($product['name'] ?? ''));
                        if ($productName !== '') {
                            $existingByName[$productName] = $index;
                        }
                    }

                    $created = 0;
                    $updated = 0;
                    $rowErrors = [];

                    $uniqueId = static function (string $base, array $existing) use ($slugify): string {
                        $candidate = $slugify($base);
                        if ($candidate === '') {
                            $candidate = 'tool';
                        }
                        $suffix = 1;
                        $final = $candidate;
                        while (isset($existing[$final])) {
                            $suffix++;
                            $final = $candidate . '-' . $suffix;
                        }
                        return $final;
                    };

                    foreach ($rows as $row) {
                        $name = trim((string) ($row['name'] ?? ''));
                        $category = trim((string) ($row['category'] ?? 'Automation')) ?: 'Automation';
                        $tagline = trim((string) ($row['tagline'] ?? ''));
                        $websiteUrl = trim((string) ($row['website_url'] ?? ''));

                        if ($name === '' || $tagline === '' || $websiteUrl === '') {
                            $rowErrors[] = 'Skipping row with missing required fields: ' . ($name ?: 'Unnamed');
                            continue;
                        }

                        $slug = trim((string) ($row['slug'] ?? ''));
                        if ($slug === '') {
                            $slug = $slugify($name);
                        } else {
                            $slug = $slugify($slug);
                        }

                        $slugKey = strtolower($slug);
                        $nameKey = strtolower($name);
                        $index = $slugKey !== '' && isset($existingBySlug[$slugKey])
                            ? $existingBySlug[$slugKey]
                            : ($existingByName[$nameKey] ?? null);

                        $product = $index !== null ? $products[$index] : [];
                        if (!is_array($product)) {
                            $product = [];
                        }

                        $product['name'] = $name;
                        $product['category'] = $category;
                        $product['tagline'] = $tagline;
                        $product['website'] = $websiteUrl;
                        $product['website_url'] = $websiteUrl;
                        if ($slug !== '') {
                            $product['slug'] = $slug;
                        }

                        $affiliateUrl = trim((string) ($row['affiliate_url'] ?? ''));
                        if ($affiliateUrl !== '') {
                            $product['affiliate_url'] = $affiliateUrl;
                        }

                        $pricingModel = trim((string) ($row['pricing_model'] ?? ''));
                        if ($pricingModel !== '') {
                            $product['pricing'] = $product['pricing'] ?? [];
                            if (!is_array($product['pricing'])) {
                                $product['pricing'] = [];
                            }
                            $product['pricing']['model'] = strtolower($pricingModel);
                        }

                        $apiAvailable = strtolower(trim((string) ($row['api_available'] ?? '')));
                        if ($apiAvailable !== '') {
                            $product['api'] = in_array($apiAvailable, ['yes', 'true', '1'], true);
                        }

                        $platforms = trim((string) ($row['platforms'] ?? ''));
                        if ($platforms !== '') {
                            $product['platforms'] = array_values(array_filter(array_map('trim', explode(',', $platforms))));
                        }

                        $featuredRank = trim((string) ($row['featured_rank'] ?? ''));
                        if ($featuredRank !== '') {
                            $product['featured_rank'] = (int) $featuredRank;
                        }

                        $lastUpdated = trim((string) ($row['last_updated'] ?? ''));
                        if ($lastUpdated !== '') {
                            $product['last_updated'] = $lastUpdated;
                        }

                        if (empty($product['id'])) {
                            $newId = $uniqueId($slug !== '' ? $slug : $name, $existingIds);
                            $product['id'] = $newId;
                            $existingIds[$newId] = true;
                        }

                        if ($index !== null) {
                            $products[$index] = $product;
                            $updated++;
                        } else {
                            $products[] = $product;
                            $created++;
                            if ($slug !== '') {
                                $existingBySlug[$slugKey] = count($products) - 1;
                            }
                            $existingByName[$nameKey] = count($products) - 1;
                        }
                    }

                    if (!empty($rowErrors)) {
                        $errors = array_merge($errors, $rowErrors);
                    }

                    if (empty($errors)) {
                        $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        if ($encoded === false) {
                            $errors[] = 'Failed to encode products.json.';
                        } else {
                            $backupsDir = dirname($productsFile) . '/backups';
                            if (!is_dir($backupsDir)) {
                                mkdir($backupsDir, 0750, true);
                            }

                            $fp = fopen($productsFile, 'c+');
                            if ($fp === false) {
                                $errors[] = 'Unable to open products.json for writing.';
                            } elseif (!flock($fp, LOCK_EX)) {
                                $errors[] = 'Unable to lock products.json.';
                            } else {
                                rewind($fp);
                                $existing = stream_get_contents($fp);
                                if ($existing === false) {
                                    $existing = '';
                                }
                                $backupPath = $backupsDir . '/products-' . date('Ymd-His') . '.json';
                                file_put_contents($backupPath, $existing);

                                rewind($fp);
                                ftruncate($fp, 0);
                                fwrite($fp, $encoded);
                                fflush($fp);
                                flock($fp, LOCK_UN);
                                fclose($fp);

                                $status = "Import complete: {$created} created, {$updated} updated.";
                                unset($_SESSION['bulk_import_rows'], $_SESSION['bulk_import_token']);
                            }
                        }
                    }
                }
            }
        }
    }
}

$isWritable = file_exists($productsFile) && is_writable($productsFile);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Bulk Import</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
    th { background: #f5f5f5; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    .success { background: #e6ffed; border: 1px solid #9ad2a6; }
    .warning { background: #fff7e0; border: 1px solid #e0c090; }
    label { display: block; margin-top: 0.75rem; font-weight: 600; }
    input[type="file"] { margin-top: 0.5rem; }
    .actions { margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Bulk CSV Import</h1>

  <?php if (!$isWritable): ?>
    <div class="message warning">products.json is not writable. Fix permissions in cPanel.</div>
  <?php endif; ?>

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
    <input id="csv_file" name="csv_file" type="file" accept=".csv" required>
    <div class="actions">
      <button type="submit" <?php echo $isWritable ? '' : 'disabled'; ?>>Preview Import</button>
    </div>
  </form>

  <?php if (!empty($previewRows)): ?>
    <h2>Preview (first 10 rows)</h2>
    <table>
      <thead>
        <tr>
          <?php foreach (array_keys($previewRows[0]) as $header): ?>
            <th><?php echo h($header); ?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($previewRows as $row): ?>
          <tr>
            <?php foreach ($row as $value): ?>
              <td><?php echo h((string) $value); ?></td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
      <input type="hidden" name="action" value="import">
      <input type="hidden" name="import_token" value="<?php echo h($summary['token'] ?? ''); ?>">
      <div class="actions">
        <button type="submit" <?php echo $isWritable ? '' : 'disabled'; ?>>Import <?php echo h((string) ($summary['total'] ?? 0)); ?> rows</button>
      </div>
    </form>
  <?php endif; ?>
</body>
</html>
