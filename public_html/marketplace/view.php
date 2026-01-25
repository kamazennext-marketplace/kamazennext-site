<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';

function kzn_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$connection = kzn_get_db_connection();
$columnMap = $connection ? kzn_get_software_column_map($connection) : [];
$slugColumn = $columnMap['slug'] ?? null;
$idColumn = $columnMap['id'] ?? null;

$slug = trim((string) ($_GET['slug'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

$record = null;
$error = null;

if (!$connection) {
    $error = 'Database connection unavailable. Please configure /home2/kamazennext/db_config.php.';
} else {
    $columns = kzn_get_software_columns($connection);
    $selectSql = $columns !== []
        ? implode(', ', array_map(static fn (string $column): string => sprintf('`%s`', $column), $columns))
        : '*';

    if ($slugColumn && $slug !== '') {
        $sql = sprintf('SELECT %s FROM software WHERE `%s` = ? LIMIT 1', $selectSql, $slugColumn);
        $stmt = $connection->prepare($sql);
        if ($stmt) {
            kzn_stmt_bind($stmt, 's', [$slug]);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $record = $result ? $result->fetch_assoc() : null;
            }
            $stmt->close();
        }
    } elseif ($idColumn && $id > 0) {
        $sql = sprintf('SELECT %s FROM software WHERE `%s` = ? LIMIT 1', $selectSql, $idColumn);
        $stmt = $connection->prepare($sql);
        if ($stmt) {
            kzn_stmt_bind($stmt, 'i', [$id]);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $record = $result ? $result->fetch_assoc() : null;
            }
            $stmt->close();
        }
    } else {
        $error = 'No software selected.';
    }

    if ($record === null && $error === null) {
        $error = 'Software entry not found.';
    }
}

$nameColumn = $columnMap['name'] ?? null;
$descriptionColumn = $columnMap['description'] ?? null;
$categoryColumn = $columnMap['category'] ?? null;
$pricingColumn = $columnMap['pricing'] ?? null;
$websiteColumn = $columnMap['website'] ?? null;
$imageColumn = $columnMap['image'] ?? null;

$name = $record && $nameColumn ? (string) ($record[$nameColumn] ?? '') : '';
$description = $record && $descriptionColumn ? (string) ($record[$descriptionColumn] ?? '') : '';
$category = $record && $categoryColumn ? (string) ($record[$categoryColumn] ?? '') : '';
$pricing = $record && $pricingColumn ? (string) ($record[$pricingColumn] ?? '') : '';
$website = $record && $websiteColumn ? (string) ($record[$websiteColumn] ?? '') : '';
$image = $record && $imageColumn ? (string) ($record[$imageColumn] ?? '') : '';

$detailMeta = [];
if ($category !== '') {
    $detailMeta[] = ['label' => 'Category', 'value' => $category];
}
if ($pricing !== '') {
    $detailMeta[] = ['label' => 'Pricing', 'value' => $pricing];
}
if ($website !== '') {
    $detailMeta[] = ['label' => 'Website', 'value' => $website];
}

?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo kzn_escape($name !== '' ? $name : 'Marketplace item'); ?> | Kama ZenNext</title>
    <link rel="stylesheet" href="/assets/css/styles.css?v=20260106" />
    <link rel="stylesheet" href="/assets/css/theme-light.css?v=20260106" />
    <style>
      .marketplace-detail { padding: 40px 0 60px; }
      .marketplace-detail-card { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; background: #fff; border-radius: 20px; padding: 24px; box-shadow: 0 8px 24px rgba(0,0,0,.08); border: 1px solid rgba(0,0,0,.08); }
      .marketplace-back { margin-bottom: 20px; display: inline-block; text-decoration: none; color: #1f4cff; }
      .marketplace-detail-header { display: flex; gap: 18px; align-items: center; flex-wrap: wrap; }
      .marketplace-detail-title h1 { margin: 0 0 8px; }
      .marketplace-detail-meta { display: flex; flex-wrap: wrap; gap: 8px; }
      .marketplace-detail-meta span { background: #f3f4f6; padding: 6px 12px; border-radius: 999px; font-size: 0.9rem; }
      .marketplace-detail-thumb { width: 80px; height: 80px; border-radius: 18px; object-fit: cover; background: #f3f4f6; }
      .marketplace-detail-actions { display: flex; gap: 12px; flex-wrap: wrap; }
      .marketplace-detail-actions a { padding: 10px 16px; border-radius: 10px; text-decoration: none; border: 1px solid #111; color: #111; }
      .marketplace-detail-actions a.primary { background: #111; color: #fff; }
      .marketplace-error { text-align: center; padding: 40px 0; color: #b00020; }
    </style>
    <script src="/assets/js/header.js" defer></script>
    <script src="/assets/js/mobile-nav.js" defer></script>
  </head>
  <body>
    <div id="site-header"></div>
    <main class="container marketplace-detail">
      <a class="marketplace-back" href="/marketplace/">← Back to marketplace</a>

      <?php if ($error !== null): ?>
        <div class="marketplace-error"><?php echo kzn_escape($error); ?></div>
      <?php else: ?>
        <section class="marketplace-detail-card">
          <div class="marketplace-detail-header">
            <?php if ($image !== ''): ?>
              <img class="marketplace-detail-thumb" src="<?php echo kzn_escape($image); ?>" alt="" />
            <?php else: ?>
              <span class="marketplace-detail-thumb"></span>
            <?php endif; ?>
            <div class="marketplace-detail-title">
              <h1><?php echo kzn_escape($name !== '' ? $name : 'Untitled software'); ?></h1>
              <?php if ($description !== ''): ?>
                <p><?php echo kzn_escape($description); ?></p>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($detailMeta !== []): ?>
            <div class="marketplace-detail-meta">
              <?php foreach ($detailMeta as $meta): ?>
                <?php if ($meta['label'] === 'Website'): ?>
                  <span><?php echo kzn_escape($meta['label']); ?>: <a href="<?php echo kzn_escape($meta['value']); ?>" rel="noopener" target="_blank"><?php echo kzn_escape($meta['value']); ?></a></span>
                <?php else: ?>
                  <span><?php echo kzn_escape($meta['label']); ?>: <?php echo kzn_escape($meta['value']); ?></span>
                <?php endif; ?>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if ($website !== ''): ?>
            <div class="marketplace-detail-actions">
              <a class="primary" href="<?php echo kzn_escape($website); ?>" rel="noopener" target="_blank">Visit Website</a>
              <?php if ($slugColumn && $slug !== ''): ?>
                <a href="/marketplace/?q=<?php echo kzn_escape($slug); ?>">Search similar</a>
              <?php elseif ($name !== ''): ?>
                <a href="/marketplace/?q=<?php echo kzn_escape($name); ?>">Search similar</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </section>
      <?php endif; ?>
    </main>
  </body>
</html>
