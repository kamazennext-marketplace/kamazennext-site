<?php
/**
 * Admin Catalog Editor
 * - Edits products.json safely with backups + file locking.
 * - Protected by env ADMIN_PASSWORD (no hardcoded secrets).
 * - Data folders blocked from public access via .htaccess.
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
    header('WWW-Authenticate: Basic realm="Catalog Editor"');
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

$errors = [];
$status = '';

$csrfToken = $_SESSION['csrf_token'] ?? '';
if ($csrfToken === '') {
    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;
}

$products = [];
if (file_exists($productsFile)) {
    $raw = file_get_contents($productsFile);
    if ($raw === false) {
        $errors[] = 'Unable to read products.json.';
    } else {
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            $errors[] = 'products.json is not valid JSON.';
        } else {
            $products = $decoded;
        }
    }
} else {
    $errors[] = 'products.json not found.';
}

$backupsDir = dirname($productsFile) . '/backups';
if (file_exists($productsFile) && !is_dir($backupsDir)) {
    mkdir($backupsDir, 0750, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($csrfToken, (string) $_POST['csrf_token'])) {
        $errors[] = 'Invalid CSRF token.';
    }

    $slug = trim((string) ($_POST['slug'] ?? ''));
    if ($slug === '') {
        $errors[] = 'Missing product slug.';
    }

    if (empty($errors)) {
        $productIndex = null;
        foreach ($products as $index => $product) {
            if (is_array($product) && ($product['slug'] ?? '') === $slug) {
                $productIndex = $index;
                break;
            }
        }

        if ($productIndex === null) {
            $errors[] = 'Product not found.';
        }
    }

    $website = trim((string) ($_POST['website'] ?? ''));
    if ($website !== '' && !preg_match('/^https:\/\//i', $website)) {
        $errors[] = 'Website must start with https://';
    }

    $logo = trim((string) ($_POST['logo'] ?? ''));
    $category = trim((string) ($_POST['category'] ?? ''));
    $tagline = trim((string) ($_POST['tagline'] ?? ''));
    $api = isset($_POST['api']);
    $featured = isset($_POST['featured']);

    $pricingModel = trim((string) ($_POST['pricing_model'] ?? 'Varies'));
    $allowedPricing = ['Varies', 'Free', 'Freemium', 'Paid'];
    if (!in_array($pricingModel, $allowedPricing, true)) {
        $pricingModel = 'Varies';
    }

    $audienceInput = $_POST['audience'] ?? [];
    if (!is_array($audienceInput)) {
        $audienceInput = [];
    }
    $allowedAudience = ['business', 'builder'];
    $audience = array_values(array_intersect($allowedAudience, $audienceInput));

    $sponsoredRankInput = trim((string) ($_POST['sponsored_rank'] ?? ''));
    $sponsoredRank = null;
    if ($sponsoredRankInput !== '') {
        if (!is_numeric($sponsoredRankInput)) {
            $errors[] = 'Sponsored rank must be a number.';
        } else {
            $sponsoredRank = (int) $sponsoredRankInput;
        }
    }

    if (empty($errors) && $productIndex !== null) {
        $product = $products[$productIndex];
        if (!is_array($product)) {
            $product = [];
        }

        $product['website'] = $website;
        if ($logo !== '') {
            $product['logo'] = $logo;
        } else {
            unset($product['logo']);
        }
        $product['category'] = $category;
        $product['tagline'] = $tagline;
        $product['api'] = $api;
        $product['featured'] = $featured;
        $product['audience'] = $audience;

        if (!isset($product['pricing']) || !is_array($product['pricing'])) {
            $product['pricing'] = [];
        }
        $product['pricing']['model'] = $pricingModel;

        if ($sponsoredRank === null) {
            unset($product['sponsored_rank']);
        } else {
            $product['sponsored_rank'] = $sponsoredRank;
        }

        $products[$productIndex] = $product;

        $seenSlugs = [];
        foreach ($products as $item) {
            if (!is_array($item)) {
                $errors[] = 'Invalid product entry detected.';
                break;
            }
            $itemSlug = $item['slug'] ?? '';
            if ($itemSlug === '') {
                $errors[] = 'Every product must have a slug.';
                break;
            }
            if (in_array($itemSlug, $seenSlugs, true)) {
                $errors[] = 'Duplicate slug detected.';
                break;
            }
            $seenSlugs[] = $itemSlug;
        }
    }

    if (empty($errors)) {
        $encoded = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $errors[] = 'Failed to encode products.json.';
        } else {
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

                if (!is_dir($backupsDir)) {
                    mkdir($backupsDir, 0750, true);
                }

                $backupPath = $backupsDir . '/products-' . date('Ymd-His') . '.json';
                if (file_put_contents($backupPath, $existing) === false) {
                    $errors[] = 'Failed to create backup.';
                } else {
                    rewind($fp);
                    ftruncate($fp, 0);
                    fwrite($fp, $encoded);
                    fflush($fp);
                    $status = 'Saved';
                }

                flock($fp, LOCK_UN);
                fclose($fp);
            }
        }
    }
}

$query = trim((string) ($_GET['q'] ?? ''));
$selectedSlug = trim((string) ($_GET['slug'] ?? ($_POST['slug'] ?? '')));

$filteredProducts = $products;
if ($query !== '') {
    $needle = mb_strtolower($query);
    $filteredProducts = array_values(array_filter($products, function ($product) use ($needle) {
        if (!is_array($product)) {
            return false;
        }
        $name = mb_strtolower((string) ($product['name'] ?? ''));
        $slug = mb_strtolower((string) ($product['slug'] ?? ''));
        $category = mb_strtolower((string) ($product['category'] ?? ''));
        return str_contains($name, $needle) || str_contains($slug, $needle) || str_contains($category, $needle);
    }));
}

$selectedProduct = null;
foreach ($products as $product) {
    if (is_array($product) && ($product['slug'] ?? '') === $selectedSlug) {
        $selectedProduct = $product;
        break;
    }
}

$isWritable = file_exists($productsFile) && is_writable($productsFile);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Catalog Editor</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
    th { background: #f5f5f5; }
    .layout { display: grid; grid-template-columns: 1fr 1.2fr; gap: 2rem; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    .success { background: #e6ffed; border: 1px solid #9ad2a6; }
    .warning { background: #fff7e0; border: 1px solid #e0c090; }
    label { display: block; margin-top: 0.75rem; font-weight: 600; }
    input[type="text"], input[type="number"], select { width: 100%; padding: 0.4rem; }
    .checkboxes label { display: inline-block; margin-right: 1rem; font-weight: 400; }
    .readonly { background: #f3f3f3; }
    .actions { margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Admin Catalog Editor</h1>

  <?php if (!$isWritable): ?>
    <div class="message warning">products.json is not writable. Fix permissions in cPanel.</div>
  <?php endif; ?>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <?php if ($status !== ''): ?>
    <div class="message success"><?php echo h($status); ?></div>
  <?php endif; ?>

  <form method="get">
    <label for="search">Search products</label>
    <input id="search" type="text" name="q" value="<?php echo h($query); ?>" placeholder="Search by name, slug, category">
    <div class="actions">
      <button type="submit">Search</button>
    </div>
  </form>

  <div class="layout">
    <div>
      <h2>Products</h2>
      <table>
        <thead>
          <tr>
            <th>Name</th>
            <th>Slug</th>
            <th>Category</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($filteredProducts)): ?>
            <tr><td colspan="4">No products found.</td></tr>
          <?php else: ?>
            <?php foreach ($filteredProducts as $product): ?>
              <?php if (!is_array($product)) { continue; } ?>
              <tr>
                <td><?php echo h((string) ($product['name'] ?? '')); ?></td>
                <td><?php echo h((string) ($product['slug'] ?? '')); ?></td>
                <td><?php echo h((string) ($product['category'] ?? '')); ?></td>
                <td><a href="?slug=<?php echo h((string) ($product['slug'] ?? '')); ?>">Edit</a></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    <div>
      <h2>Edit Product</h2>
      <?php if ($selectedProduct === null): ?>
        <p>Select a product to edit.</p>
      <?php else: ?>
        <form method="post">
          <input type="hidden" name="csrf_token" value="<?php echo h($csrfToken); ?>">
          <label for="name">Name</label>
          <input id="name" class="readonly" type="text" name="name" value="<?php echo h((string) ($selectedProduct['name'] ?? '')); ?>" readonly>

          <label for="slug">Slug</label>
          <input id="slug" class="readonly" type="text" name="slug" value="<?php echo h((string) ($selectedProduct['slug'] ?? '')); ?>" readonly>

          <label for="website">Website</label>
          <input id="website" type="text" name="website" value="<?php echo h((string) ($selectedProduct['website'] ?? '')); ?>" placeholder="https://example.com">

          <label for="logo">Logo</label>
          <input id="logo" type="text" name="logo" value="<?php echo h((string) ($selectedProduct['logo'] ?? '')); ?>" placeholder="Optional">

          <label for="category">Category</label>
          <input id="category" type="text" name="category" value="<?php echo h((string) ($selectedProduct['category'] ?? '')); ?>">

          <label for="tagline">Tagline</label>
          <input id="tagline" type="text" name="tagline" value="<?php echo h((string) ($selectedProduct['tagline'] ?? '')); ?>">

          <label class="checkboxes">
            <input type="checkbox" name="api" <?php echo !empty($selectedProduct['api']) ? 'checked' : ''; ?>> API available
          </label>

          <label class="checkboxes">
            <input type="checkbox" name="featured" <?php echo !empty($selectedProduct['featured']) ? 'checked' : ''; ?>> Featured
          </label>

          <label>Audience</label>
          <div class="checkboxes">
            <?php $audienceValues = $selectedProduct['audience'] ?? []; ?>
            <label><input type="checkbox" name="audience[]" value="business" <?php echo in_array('business', $audienceValues, true) ? 'checked' : ''; ?>> Business</label>
            <label><input type="checkbox" name="audience[]" value="builder" <?php echo in_array('builder', $audienceValues, true) ? 'checked' : ''; ?>> Builder</label>
          </div>

          <label for="pricing_model">Pricing Model</label>
          <?php $pricingModelValue = $selectedProduct['pricing']['model'] ?? 'Varies'; ?>
          <select id="pricing_model" name="pricing_model">
            <?php foreach (['Varies', 'Free', 'Freemium', 'Paid'] as $model): ?>
              <option value="<?php echo h($model); ?>" <?php echo $pricingModelValue === $model ? 'selected' : ''; ?>><?php echo h($model); ?></option>
            <?php endforeach; ?>
          </select>

          <label for="sponsored_rank">Sponsored Rank</label>
          <input id="sponsored_rank" type="number" name="sponsored_rank" value="<?php echo h((string) ($selectedProduct['sponsored_rank'] ?? '')); ?>">

          <div class="actions">
            <button type="submit" <?php echo $isWritable ? '' : 'disabled'; ?>>Save</button>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
