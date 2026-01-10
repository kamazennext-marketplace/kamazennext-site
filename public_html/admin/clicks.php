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
    header('WWW-Authenticate: Basic realm="Click Report"');
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

$productMap = [];
foreach ($products as $product) {
    if (!is_array($product)) {
        continue;
    }
    $productId = (string) ($product['id'] ?? '');
    if ($productId !== '') {
        $productMap[$productId] = (string) ($product['name'] ?? $productId);
    }
}

$startDate = trim((string) ($_GET['start'] ?? ''));
$endDate = trim((string) ($_GET['end'] ?? ''));
$productId = trim((string) ($_GET['product_id'] ?? ''));
$fromPage = trim((string) ($_GET['from_page'] ?? ''));

$clicks = [];
$totals = [];
$errors = [];

$clickDb = __DIR__ . '/../data/clicks.sqlite';
if (!file_exists($clickDb)) {
    $errors[] = 'Click database not found yet. Outbound clicks will create it automatically.';
} else {
    try {
        $pdo = new PDO('sqlite:' . $clickDb, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $filters = [];
        $params = [];
        if ($startDate !== '') {
            $filters[] = 'date(ts) >= date(:start)';
            $params[':start'] = $startDate;
        }
        if ($endDate !== '') {
            $filters[] = 'date(ts) <= date(:end)';
            $params[':end'] = $endDate;
        }
        if ($productId !== '') {
            $filters[] = 'product_id = :product_id';
            $params[':product_id'] = $productId;
        }
        if ($fromPage !== '') {
            $filters[] = 'from_page = :from_page';
            $params[':from_page'] = $fromPage;
        }

        $where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

        $totalStmt = $pdo->prepare("SELECT product_id, COUNT(*) as total FROM clicks {$where} GROUP BY product_id ORDER BY total DESC");
        $totalStmt->execute($params);
        $totals = $totalStmt->fetchAll();

        $clickStmt = $pdo->prepare("SELECT * FROM clicks {$where} ORDER BY ts DESC LIMIT 200");
        $clickStmt->execute($params);
        $clicks = $clickStmt->fetchAll();
    } catch (Throwable $e) {
        $errors[] = 'Unable to read click database.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Click Report</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; color: #111; }
    table { border-collapse: collapse; width: 100%; margin-top: 1rem; }
    th, td { border: 1px solid #ddd; padding: 0.5rem; text-align: left; }
    th { background: #f5f5f5; }
    .message { padding: 0.75rem 1rem; margin-bottom: 1rem; border-radius: 6px; }
    .error { background: #ffe0e0; border: 1px solid #e0a0a0; }
    label { display: block; margin-top: 0.75rem; font-weight: 600; }
    input, select { padding: 0.4rem; width: 100%; max-width: 320px; }
    .filters { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }
    .actions { margin-top: 1rem; }
  </style>
</head>
<body>
  <h1>Affiliate Click Report</h1>

  <?php foreach ($errors as $error): ?>
    <div class="message error"><?php echo h($error); ?></div>
  <?php endforeach; ?>

  <form method="get">
    <div class="filters">
      <div>
        <label for="start">Start date</label>
        <input id="start" type="date" name="start" value="<?php echo h($startDate); ?>">
      </div>
      <div>
        <label for="end">End date</label>
        <input id="end" type="date" name="end" value="<?php echo h($endDate); ?>">
      </div>
      <div>
        <label for="product_id">Product</label>
        <select id="product_id" name="product_id">
          <option value="">All products</option>
          <?php foreach ($productMap as $id => $name): ?>
            <option value="<?php echo h($id); ?>" <?php echo $productId === $id ? 'selected' : ''; ?>>
              <?php echo h($name); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="from_page">From page</label>
        <input id="from_page" type="text" name="from_page" placeholder="automation, home, product" value="<?php echo h($fromPage); ?>">
      </div>
    </div>
    <div class="actions">
      <button type="submit">Filter</button>
    </div>
  </form>

  <h2>Totals by product</h2>
  <table>
    <thead>
      <tr>
        <th>Product</th>
        <th>Clicks</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($totals)): ?>
        <tr><td colspan="2">No clicks yet.</td></tr>
      <?php else: ?>
        <?php foreach ($totals as $row): ?>
          <tr>
            <td><?php echo h($productMap[$row['product_id']] ?? $row['product_id']); ?></td>
            <td><?php echo h((string) $row['total']); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <h2>Recent clicks</h2>
  <table>
    <thead>
      <tr>
        <th>Timestamp (UTC)</th>
        <th>Product</th>
        <th>From</th>
        <th>Referrer</th>
        <th>Destination</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($clicks)): ?>
        <tr><td colspan="5">No click data to display.</td></tr>
      <?php else: ?>
        <?php foreach ($clicks as $row): ?>
          <tr>
            <td><?php echo h((string) $row['ts']); ?></td>
            <td><?php echo h($productMap[$row['product_id']] ?? $row['product_id']); ?></td>
            <td><?php echo h((string) ($row['from_page'] ?? '')); ?></td>
            <td><?php echo h((string) ($row['referrer'] ?? '')); ?></td>
            <td><?php echo h((string) ($row['dest_url'] ?? '')); ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</body>
</html>
