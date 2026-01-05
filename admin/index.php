<?php
session_start();

$productsFile = __DIR__ . '/../data/products.json';
$backupDir = __DIR__ . '/../data/backups';
$logosDir = __DIR__ . '/../assets/logos';

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

if (isset($_POST['login_password'])) {
    if (!$configLoaded) {
        $error = 'Configuration file not found. Create admin_config.php before logging in.';
    } elseif (password_verify($_POST['login_password'], ADMIN_PASSWORD_HASH)) {
        $_SESSION['admin_authenticated'] = true;
        $isAuthenticated = true;
    } else {
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

                $pricing = [
                    'model' => trim($_POST['pricing_model'] ?? ''),
                    'starting_price' => $_POST['pricing_starting_price'] !== '' ? (float) $_POST['pricing_starting_price'] : 0,
                    'free_trial' => isset($_POST['pricing_free_trial']),
                ];

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
                    'pros' => $pros,
                    'cons' => $cons,
                    'use_cases' => $useCases,
                ];

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
            <strong>Configuration missing.</strong> Create <code>admin_config.php</code> outside the web root (recommended path: <code><?= h(dirname(__DIR__, 2)) ?>/admin_config.php</code>) based on <code>admin/admin_config.example.php</code>.
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
                'pros' => [],
                'cons' => [],
                'use_cases' => [],
            ];
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
                    <label>Pros (comma separated)<input type="text" name="pros" value="<?= h(implode(', ', $formData['pros'] ?? [])) ?>"></label>
                    <label>Cons (comma separated)<input type="text" name="cons" value="<?= h(implode(', ', $formData['cons'] ?? [])) ?>"></label>
                    <label>Use Cases (comma separated)<input type="text" name="use_cases" value="<?= h(implode(', ', $formData['use_cases'] ?? [])) ?>"></label>

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
</script>
</body>
</html>
