<?php
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

$productsFile = __DIR__ . '/../data/products.json';
$backupDir = __DIR__ . '/../data/backups';
$logosDir = __DIR__ . '/../assets/logos';
$clicksDir = __DIR__ . '/../data/clicks';

$configLocations = [
    dirname(__DIR__, 2) . '/admin_config.php',
    __DIR__ . '/admin_config.php',
];
$configLoaded = false;
$configPathUsed = null;

foreach ($configLocations as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = defined('ADMIN_PASSWORD_HASH');
        $configPathUsed = $path;
        break;
    }
}

$isAuthenticated = !empty($_SESSION['admin_authenticated']);
$error = '';
$success = '';

$maxFailedAttempts = 5;
$cooldownSeconds = 60;
$failCount = (int)($_SESSION['fail_count'] ?? 0);
$lastFailTime = (int)($_SESSION['last_fail_time'] ?? 0);

function inCooldown(int $failCount, int $lastFailTime, int $maxFailedAttempts, int $cooldownSeconds): bool
{
    if ($failCount < $maxFailedAttempts) {
        return false;
    }

    return (time() - $lastFailTime) < $cooldownSeconds;
}

if (isset($_POST['login_password'])) {
    if (!$configLoaded) {
        $error = 'Configuration file not found. Create admin_config.php before logging in.';
    } elseif (inCooldown($failCount, $lastFailTime, $maxFailedAttempts, $cooldownSeconds)) {
        $remaining = max(0, $cooldownSeconds - (time() - $lastFailTime));
        $error = "Too many failed attempts. Please wait {$remaining} seconds before trying again.";
    } elseif (password_verify($_POST['login_password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_authenticated'] = true;
        $_SESSION['fail_count'] = 0;
        $_SESSION['last_fail_time'] = 0;
        $isAuthenticated = true;
    } else {
        sleep(1);
        $failCount++;
        $_SESSION['fail_count'] = $failCount;
        $_SESSION['last_fail_time'] = time();
        $error = 'Invalid password.';
    }
}

function sanitizeList(string $value): array
{
    $items = array_map('trim', explode(',', $value));
    return array_values(array_filter($items, fn($item) => $item !== ''));
}

function loadProducts(string $file): array
{
    if (!file_exists($file)) {
        return [];
    }
    $contents = file_get_contents($file);
    $decoded = json_decode($contents, true);
    return is_array($decoded) ? $decoded : [];
}

function saveProducts(array $products, string $file, string $backupDir): bool
{
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0777, true);
    }

    $json = json_encode($products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        throw new RuntimeException('Failed to encode products JSON.');
    }

    $lockHandle = fopen($file, 'c+');
    if (!$lockHandle) {
        throw new RuntimeException('Unable to open products file for writing.');
    }

    if (!flock($lockHandle, LOCK_EX)) {
        fclose($lockHandle);
        throw new RuntimeException('Could not lock products file.');
    }

    if (file_exists($file)) {
        $timestamp = date('Ymd-His');
        $backupPath = rtrim($backupDir, '/')."/products-{$timestamp}.json";
        copy($file, $backupPath);
    }

    $tempFile = $file . '.tmp';
    if (file_put_contents($tempFile, $json) === false) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException('Failed to write temporary products file.');
    }

    if (!rename($tempFile, $file)) {
        flock($lockHandle, LOCK_UN);
        fclose($lockHandle);
        throw new RuntimeException('Failed to finalize products file.');
    }

    fflush($lockHandle);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    return true;
}

function getLatestClickCount(string $slug, string $clicksDir): int
{
    if ($slug === '' || !is_dir($clicksDir)) {
        return 0;
    }

    $files = glob(rtrim($clicksDir, '/') . '/clicks-*.jsonl');
    if (!$files) {
        return 0;
    }

    rsort($files, SORT_STRING);
    $latest = $files[0];
    if (!is_readable($latest)) {
        return 0;
    }

    $count = 0;
    $handle = fopen($latest, 'r');
    if (!$handle) {
        return 0;
    }

    while (($line = fgets($handle)) !== false) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        $entry = json_decode($line, true);
        if (is_array($entry) && ($entry['slug'] ?? '') === $slug) {
            $count++;
        }
    }

    fclose($handle);
    return $count;
}

if ($isAuthenticated) {
    $products = loadProducts($productsFile);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        try {
            if ($_POST['action'] === 'save_product') {
                $id = trim($_POST['id'] ?? '');
                $originalId = trim($_POST['original_id'] ?? '');
                $name = trim($_POST['name'] ?? '');
                if ($id === '') {
                    throw new RuntimeException('Product ID is required.');
                }
                if ($name === '') {
                    throw new RuntimeException('Product name is required.');
                }

                $existingProduct = null;
                foreach ($products as $candidate) {
                    if (($candidate['id'] ?? '') === $originalId) {
                        $existingProduct = $candidate;
                        break;
                    }
                }

                $pricing = [
                    'model' => trim($_POST['pricing_model'] ?? ''),
                    'starting_price' => $_POST['pricing_starting_price'] !== '' ? (float) $_POST['pricing_starting_price'] : 0,
                    'free_trial' => isset($_POST['pricing_free_trial']),
                ];
                $featured = isset($_POST['featured']);
                $sponsoredRankInput = trim($_POST['sponsored_rank'] ?? '');
                $sponsoredRank = $sponsoredRankInput === '' ? null : (float) $sponsoredRankInput;

                $platforms = sanitizeList($_POST['platforms'] ?? '');
                $bestFor = sanitizeList($_POST['best_for'] ?? '');
                $keyFeatures = sanitizeList($_POST['key_features'] ?? '');
                $integrations = sanitizeList($_POST['integrations'] ?? '');
                $pros = sanitizeList($_POST['pros'] ?? '');
                $cons = sanitizeList($_POST['cons'] ?? '');
                $useCases = sanitizeList($_POST['use_cases'] ?? '');

                $productData = [
                    'id' => $id,
                    'name' => $name,
                    'category' => trim($_POST['category'] ?? ''),
                    'tagline' => trim($_POST['tagline'] ?? ''),
                    'pricing' => $pricing,
                    'platforms' => $platforms,
                    'api' => isset($_POST['api']),
                    'best_for' => $bestFor,
                    'key_features' => $keyFeatures,
                    'integrations' => $integrations,
                    'website' => trim($_POST['website'] ?? ''),
                    'logo' => trim($_POST['logo'] ?? ''),
                    'last_updated' => trim($_POST['last_updated'] ?? ''),
                    'featured' => $featured,
                    'sponsored_rank' => $sponsoredRank,
                    'rating' => $existingProduct['rating'] ?? null,
                    'review_count' => $existingProduct['review_count'] ?? null,
                    'reviews' => $existingProduct['reviews'] ?? [],
                    'pros' => $pros,
                    'cons' => $cons,
                    'use_cases' => $useCases,
                ];

                $reviewAuthors = $_POST['review_author'] ?? [];
                $reviewRatings = $_POST['review_rating'] ?? [];
                $reviewTitles = $_POST['review_title'] ?? [];
                $reviewBodies = $_POST['review_body'] ?? [];
                $reviewPros = $_POST['review_pros'] ?? [];
                $reviewCons = $_POST['review_cons'] ?? [];
                $reviewDates = $_POST['review_date'] ?? [];
                $reviewIds = $_POST['review_id'] ?? [];

                $reviews = [];
                foreach ($reviewAuthors as $idx => $author) {
                    $author = trim((string) $author);
                    $title = trim((string) ($reviewTitles[$idx] ?? ''));
                    $body = trim((string) ($reviewBodies[$idx] ?? ''));
                    $ratingValue = isset($reviewRatings[$idx]) && $reviewRatings[$idx] !== '' ? (float) $reviewRatings[$idx] : null;
                    $date = trim((string) ($reviewDates[$idx] ?? ''));

                    if ($author === '' && $title === '' && $body === '') {
                        continue;
                    }

                    $ratingValue = $ratingValue !== null ? max(1, min(5, $ratingValue)) : null;
                    $reviews[] = [
                        'id' => trim((string) ($reviewIds[$idx] ?? '')) ?: uniqid('rev-', true),
                        'author' => $author,
                        'rating' => $ratingValue,
                        'title' => $title,
                        'body' => $body,
                        'pros' => sanitizeList($reviewPros[$idx] ?? ''),
                        'cons' => sanitizeList($reviewCons[$idx] ?? ''),
                        'date' => $date,
                    ];
                }

                if ($reviews) {
                    $total = array_reduce($reviews, fn($carry, $review) => $carry + ((float) ($review['rating'] ?? 0)), 0);
                    $productData['rating'] = round($total / count($reviews), 1);
                    $productData['review_count'] = count($reviews);
                }
                $productData['reviews'] = $reviews;

                $editingIndex = null;
                foreach ($products as $index => $existing) {
                    if ($existing['id'] === $id && $originalId !== $id) {
                        throw new RuntimeException('Product ID must be unique.');
                    }
                    if ($existing['id'] === $originalId) {
                        $editingIndex = $index;
                    }
                }

                if ($editingIndex !== null) {
                    $products[$editingIndex] = array_merge($products[$editingIndex], $productData);
                } else {
                    foreach ($products as $existing) {
                        if ($existing['id'] === $id) {
                            throw new RuntimeException('Product ID must be unique.');
                        }
                    }
                    $products[] = $productData;
                }

                saveProducts($products, $productsFile, $backupDir);
                $success = 'Product saved successfully.';
            } elseif ($_POST['action'] === 'delete_product') {
                $id = trim($_POST['id'] ?? '');
                $products = array_values(array_filter($products, fn($p) => ($p['id'] ?? '') !== $id));
                saveProducts($products, $productsFile, $backupDir);
                $success = 'Product deleted.';
            }
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }
    }
} else {
    $products = [];
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$editingProduct = null;
if ($isAuthenticated && isset($_GET['edit'])) {
    foreach ($products as $product) {
        if ($product['id'] === $_GET['edit']) {
            $editingProduct = $product;
            break;
        }
    }
}
$isAdding = $isAuthenticated && isset($_GET['add']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Product Manager</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<div class="admin-container">
    <header class="admin-header">
        <h1>Product Admin</h1>
        <?php if ($isAuthenticated): ?>
            <div class="admin-header__actions">
                <a class="button" href="/admin/logout.php">Logout</a>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!$configLoaded): ?>
        <div class="alert alert-warning">
            <strong>Admin configuration missing.</strong> Create <code>admin_config.php</code> before logging in. The admin first checks <code><?= h(dirname(__DIR__, 2)) ?>/admin_config.php</code> and then <code><?= h(__DIR__) ?>/admin_config.php</code> as a fallback.
            <div style="margin-top:8px;">
                <a class="button" href="/admin/setup.php">Open Admin Setup</a>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$isAuthenticated): ?>
        <section class="panel">
            <h2>Login</h2>
            <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
            <form method="post">
                <label for="password">Password</label>
                <input type="password" id="password" name="login_password" required>
                <button type="submit" class="button">Login</button>
            </form>
        </section>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?= h($error) ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success"><?= h($success) ?></div><?php endif; ?>

        <section class="panel">
            <div class="panel-header">
                <h2>Products</h2>
                <div class="panel-actions">
                    <a class="button" href="?add=1">Add new product</a>
                </div>
            </div>
            <div class="table-wrapper">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Pricing Model</th>
                        <th>API</th>
                        <th>Featured/Sponsored</th>
                        <th>Updated</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td><?= h($product['name'] ?? '') ?></td>
                            <td><?= h($product['category'] ?? '') ?></td>
                            <td><?= h($product['pricing']['model'] ?? '') ?></td>
                            <td><?= !empty($product['api']) ? 'Yes' : 'No' ?></td>
                            <td><?= !empty($product['sponsored_rank']) ? 'Sponsored (#'.h($product['sponsored_rank']).')' : (!empty($product['featured']) ? 'Featured' : '') ?></td>
                            <td><?= h($product['last_updated'] ?? '') ?></td>
                            <td class="row-actions">
                                <a class="link" href="?edit=<?= urlencode($product['id']) ?>">Edit</a>
                                <form method="post" style="display:inline" onsubmit="return confirm('Delete this product?');">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= h($product['id']) ?>">
                                    <button type="submit" class="link danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <?php if ($editingProduct || $isAdding):
            $formData = $editingProduct ?? [
                'id' => '',
                'name' => '',
                'category' => '',
                'tagline' => '',
                'pricing' => ['model' => '', 'starting_price' => '', 'free_trial' => false],
                'platforms' => [],
                'api' => false,
                'best_for' => [],
                'key_features' => [],
                'integrations' => [],
                'website' => '',
                'logo' => '',
                'last_updated' => '',
                'featured' => false,
                'sponsored_rank' => '',
                'rating' => '',
                'review_count' => '',
                'reviews' => [],
                'pros' => [],
                'cons' => [],
                'use_cases' => [],
            ];
            $clickCount = getLatestClickCount($formData['id'] ?? '', $clicksDir);
            ?>
            <section class="panel">
                <div class="panel-header">
                    <h2><?= $editingProduct ? 'Edit Product' : 'Add Product' ?></h2>
                    <a class="link" href="/admin/index.php">Back to list</a>
                </div>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="original_id" value="<?= h($editingProduct['id'] ?? '') ?>">

                    <label>Product ID*<input type="text" name="id" value="<?= h($formData['id']) ?>" required></label>
                    <label>Name*<input type="text" name="name" value="<?= h($formData['name']) ?>" required></label>
                    <label>Category<input type="text" name="category" value="<?= h($formData['category']) ?>"></label>
                    <label>Tagline<input type="text" name="tagline" value="<?= h($formData['tagline']) ?>"></label>

                    <label>Pricing Model<input type="text" name="pricing_model" value="<?= h($formData['pricing']['model'] ?? '') ?>"></label>
                    <label>Starting Price<input type="number" step="0.01" name="pricing_starting_price" value="<?= h($formData['pricing']['starting_price'] ?? '') ?>"></label>
                    <label class="checkbox">
                        <input type="checkbox" name="pricing_free_trial" <?= !empty($formData['pricing']['free_trial']) ? 'checked' : '' ?>> Free Trial
                    </label>

                    <label>Platforms (comma separated)<input type="text" name="platforms" value="<?= h(implode(', ', $formData['platforms'] ?? [])) ?>"></label>
                    <label class="checkbox">
                        <input type="checkbox" name="api" <?= !empty($formData['api']) ? 'checked' : '' ?>> API Available
                    </label>
                    <label>Best For (comma separated)<input type="text" name="best_for" value="<?= h(implode(', ', $formData['best_for'] ?? [])) ?>"></label>
                    <label>Key Features (comma separated)<input type="text" name="key_features" value="<?= h(implode(', ', $formData['key_features'] ?? [])) ?>"></label>
                    <label>Integrations (comma separated)<input type="text" name="integrations" value="<?= h(implode(', ', $formData['integrations'] ?? [])) ?>"></label>
                    <label>Website<input type="url" name="website" value="<?= h($formData['website']) ?>"></label>
                    <label>Logo URL / Path<input type="text" name="logo" id="logo" value="<?= h($formData['logo']) ?>">
                        <button type="button" class="button secondary" id="uploadLogoButton">Upload logo</button>
                        <input type="file" id="logoFile" accept="image/png,image/jpeg,image/svg+xml,image/webp" style="display:none">
                    </label>
                    <label>Last Updated<input type="date" name="last_updated" value="<?= h($formData['last_updated']) ?>"></label>
                    <label class="checkbox">
                        <input type="checkbox" name="featured" <?= !empty($formData['featured']) ? 'checked' : '' ?>> Featured
                    </label>
                    <label>Sponsored Rank<input type="number" name="sponsored_rank" step="1" min="1" value="<?= h($formData['sponsored_rank'] ?? '') ?>" placeholder="Lower shows first"></label>
                    <label>Clicks (latest log)<input type="text" value="<?= h((string) $clickCount) ?>" readonly></label>
                    <label>Pros (comma separated)<input type="text" name="pros" value="<?= h(implode(', ', $formData['pros'] ?? [])) ?>"></label>
                    <label>Cons (comma separated)<input type="text" name="cons" value="<?= h(implode(', ', $formData['cons'] ?? [])) ?>"></label>
                    <label>Use Cases (comma separated)<input type="text" name="use_cases" value="<?= h(implode(', ', $formData['use_cases'] ?? [])) ?>"></label>

                    <div style="grid-column: 1 / -1; margin-top: 10px;">
                        <h3>Reviews</h3>
                        <p class="meta" style="margin-bottom:8px;">Curated reviews power on-site badges and aggregate rating.</p>
                        <div id="reviewsList" class="form-grid" style="gap:12px;">
                            <?php foreach (($formData['reviews'] ?? []) as $review): ?>
                                <div class="review-item" style="border:1px solid #e2e8f0; border-radius:10px; padding:12px;">
                                    <input type="hidden" name="review_id[]" value="<?= h($review['id'] ?? '') ?>">
                                    <label>Author<input type="text" name="review_author[]" value="<?= h($review['author'] ?? '') ?>"></label>
                                    <label>Rating (1-5)<input type="number" step="0.1" min="1" max="5" name="review_rating[]" value="<?= h($review['rating'] ?? '') ?>"></label>
                                    <label>Title<input type="text" name="review_title[]" value="<?= h($review['title'] ?? '') ?>"></label>
                                    <label style="grid-column:1 / -1;">Body<textarea name="review_body[]" rows="2" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; font: inherit;"><?= h($review['body'] ?? '') ?></textarea></label>
                                    <label>Pros (comma)<input type="text" name="review_pros[]" value="<?= h(implode(', ', $review['pros'] ?? [])) ?>"></label>
                                    <label>Cons (comma)<input type="text" name="review_cons[]" value="<?= h(implode(', ', $review['cons'] ?? [])) ?>"></label>
                                    <label>Date<input type="date" name="review_date[]" value="<?= h($review['date'] ?? '') ?>"></label>
                                    <button type="button" class="button secondary remove-review" style="justify-self:start;">Remove</button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="button" class="button secondary" id="addReviewBtn">Add review</button>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="button">Save Product</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
const uploadButton = document.getElementById('uploadLogoButton');
const fileInput = document.getElementById('logoFile');
const logoField = document.getElementById('logo');

if (uploadButton && fileInput && logoField) {
    uploadButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', async () => {
        if (!fileInput.files.length) return;
        const formData = new FormData();
        formData.append('logo', fileInput.files[0]);
        uploadButton.disabled = true;
        uploadButton.textContent = 'Uploading...';
        try {
            const response = await fetch('/admin/upload.php', {method: 'POST', body: formData});
            const result = await response.json();
            if (response.ok && result.path) {
                logoField.value = result.path;
            } else {
                alert(result.error || 'Upload failed');
            }
        } catch (e) {
            alert('Upload failed');
        } finally {
            uploadButton.disabled = false;
            uploadButton.textContent = 'Upload logo';
            fileInput.value = '';
        }
    });
}

const reviewsContainer = document.getElementById('reviewsList');
const addReviewBtn = document.getElementById('addReviewBtn');

const buildReviewRow = (data = {}) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'review-item';
    wrapper.style.border = '1px solid #e2e8f0';
    wrapper.style.borderRadius = '10px';
    wrapper.style.padding = '12px';
    wrapper.innerHTML = `
        <input type="hidden" name="review_id[]" value="${data.id || ''}">
        <label>Author<input type="text" name="review_author[]" value="${data.author || ''}"></label>
        <label>Rating (1-5)<input type="number" step="0.1" min="1" max="5" name="review_rating[]" value="${data.rating || ''}"></label>
        <label>Title<input type="text" name="review_title[]" value="${data.title || ''}"></label>
        <label style="grid-column:1 / -1;">Body<textarea name="review_body[]" rows="2" style="width:100%; padding:8px; border-radius:8px; border:1px solid #e2e8f0; font: inherit;">${data.body || ''}</textarea></label>
        <label>Pros (comma)<input type="text" name="review_pros[]" value="${(data.pros || []).join(', ')}"></label>
        <label>Cons (comma)<input type="text" name="review_cons[]" value="${(data.cons || []).join(', ')}"></label>
        <label>Date<input type="date" name="review_date[]" value="${data.date || ''}"></label>
        <button type="button" class="button secondary remove-review" style="justify-self:start;">Remove</button>
    `;
    const removeBtn = wrapper.querySelector('.remove-review');
    removeBtn.addEventListener('click', () => wrapper.remove());
    return wrapper;
};

if (reviewsContainer && addReviewBtn) {
    addReviewBtn.addEventListener('click', () => {
        reviewsContainer.appendChild(buildReviewRow());
    });
    reviewsContainer.querySelectorAll('.remove-review').forEach(btn => {
        btn.addEventListener('click', () => {
            const item = btn.closest('.review-item');
            if (item) item.remove();
        });
    });
}
</script>
</body>
</html>
