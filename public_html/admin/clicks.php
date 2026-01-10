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

require_once __DIR__ . '/db.php';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$productMap = [];
$productResult = $conn->query('SELECT id, name FROM products ORDER BY name');
if ($productResult) {
    while ($row = $productResult->fetch_assoc()) {
        $productMap[(string) $row['id']] = (string) ($row['name'] ?? $row['id']);
    }
}

$startDate = trim((string) ($_GET['start'] ?? ''));
$endDate = trim((string) ($_GET['end'] ?? ''));
$productId = trim((string) ($_GET['product_id'] ?? ''));
$fromPage = trim((string) ($_GET['from_page'] ?? ''));

$clicks = [];
$totals = [];
$errors = [];

$filters = [];
$params = [];
$types = '';

if ($startDate !== '') {
    $filters[] = 'DATE(ts) >= ?';
    $params[] = $startDate;
    $types .= 's';
}
if ($endDate !== '') {
    $filters[] = 'DATE(ts) <= ?';
    $params[] = $endDate;
    $types .= 's';
}
if ($productId !== '') {
    $filters[] = 'product_id = ?';
    $params[] = (int) $productId;
    $types .= 'i';
}
if ($fromPage !== '') {
    $filters[] = 'from_page = ?';
    $params[] = $fromPage;
    $types .= 's';
}

$where = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

$bindParams = static function (mysqli_stmt $stmt, string $types, array $params): void {
    if ($types === '' || $params === []) {
        return;
    }
    $bindParams = [$types];
    foreach ($params as $key => $param) {
        $bindParams[] = $params[$key];
    }
    $stmt->bind_param(...$bindParams);
};

$totalSql = "SELECT product_id, COUNT(*) as total FROM clicks {$where} GROUP BY product_id ORDER BY total DESC";
$clickSql = "SELECT * FROM clicks {$where} ORDER BY ts DESC LIMIT 200";

$totalStmt = $conn->prepare($totalSql);
$clickStmt = $conn->prepare($clickSql);

if (!$totalStmt || !$clickStmt) {
    $errors[] = 'Click table not available yet. Run migration to create it.';
} else {
    $bindParams($totalStmt, $types, $params);
    if ($totalStmt->execute()) {
        $result = $totalStmt->get_result();
        $totals = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $bindParams($clickStmt, $types, $params);
    if ($clickStmt->execute()) {
        $result = $clickStmt->get_result();
        $clicks = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }

    $totalStmt->close();
    $clickStmt->close();
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
            <td><?php echo h($productMap[(string) $row['product_id']] ?? (string) $row['product_id']); ?></td>
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
            <td><?php echo h($productMap[(string) ($row['product_id'] ?? '')] ?? (string) ($row['product_id'] ?? '')); ?></td>
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
