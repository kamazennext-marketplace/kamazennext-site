<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/db.php';

function kzn_escape(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$connection = kzn_get_db_connection();
$columnMap = $connection ? kzn_get_software_column_map($connection) : [];
$searchTerm = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

$items = [];
$total = 0;
$error = null;

if (!$connection) {
    $error = 'Database connection unavailable. Please configure /home2/kamazennext/db_config.php.';
} else {
    $searchableColumns = array_values(array_unique(array_filter([
        $columnMap['name'] ?? null,
        $columnMap['description'] ?? null,
        $columnMap['category'] ?? null,
    ])));

    $whereSql = '';
    $params = [];
    $types = '';

    if ($searchTerm !== '' && $searchableColumns !== []) {
        $conditions = [];
        foreach ($searchableColumns as $column) {
            $conditions[] = sprintf('`%s` LIKE ?', $column);
            $params[] = '%' . $searchTerm . '%';
            $types .= 's';
        }
        $whereSql = ' WHERE ' . implode(' OR ', $conditions);
    }

    $countSql = 'SELECT COUNT(*) AS total FROM software' . $whereSql;
    $countStmt = $connection->prepare($countSql);
    if ($countStmt) {
        if ($types !== '') {
            kzn_stmt_bind($countStmt, $types, $params);
        }
        if ($countStmt->execute()) {
            $result = $countStmt->get_result();
            if ($result) {
                $row = $result->fetch_assoc();
                $total = (int) ($row['total'] ?? 0);
            }
        }
        $countStmt->close();
    }

    $selectColumns = array_values(array_unique(array_filter([
        $columnMap['id'] ?? null,
        $columnMap['slug'] ?? null,
        $columnMap['name'] ?? null,
        $columnMap['description'] ?? null,
        $columnMap['website'] ?? null,
        $columnMap['category'] ?? null,
        $columnMap['pricing'] ?? null,
        $columnMap['image'] ?? null,
    ])));
    $selectSql = $selectColumns !== []
        ? implode(', ', array_map(static fn (string $column): string => sprintf('`%s`', $column), $selectColumns))
        : '*';

    if (!empty($columnMap['id'])) {
        $orderBy = sprintf('`%s` DESC', $columnMap['id']);
    } elseif (!empty($columnMap['name'])) {
        $orderBy = sprintf('`%s` ASC', $columnMap['name']);
    } else {
        $orderBy = '1';
    }

    $listSql = sprintf('SELECT %s FROM software%s ORDER BY %s LIMIT ? OFFSET ?', $selectSql, $whereSql, $orderBy);
    $listStmt = $connection->prepare($listSql);
    if ($listStmt) {
        $listTypes = $types . 'ii';
        $listParams = array_merge($params, [$perPage, $offset]);
        kzn_stmt_bind($listStmt, $listTypes, $listParams);

        if ($listStmt->execute()) {
            $result = $listStmt->get_result();
            $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        }
        $listStmt->close();
    }
}

$totalPages = max(1, (int) ceil($total / $perPage));

$buildPageUrl = static function (int $targetPage) use ($searchTerm): string {
    $params = [];
    if ($searchTerm !== '') {
        $params['q'] = $searchTerm;
    }
    $params['page'] = $targetPage;

    return '/marketplace/?' . http_build_query($params);
};

$slugColumn = $columnMap['slug'] ?? null;
$idColumn = $columnMap['id'] ?? null;
$nameColumn = $columnMap['name'] ?? null;
$descriptionColumn = $columnMap['description'] ?? null;
$categoryColumn = $columnMap['category'] ?? null;
$pricingColumn = $columnMap['pricing'] ?? null;
$websiteColumn = $columnMap['website'] ?? null;
$imageColumn = $columnMap['image'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Marketplace | Kama ZenNext</title>
    <link rel="stylesheet" href="/assets/css/styles.css?v=20260106" />
    <link rel="stylesheet" href="/assets/css/theme-light.css?v=20260106" />
    <style>
      .marketplace-hero { padding: 48px 0 24px; text-align: center; }
      .marketplace-hero h1 { margin-bottom: 8px; }
      .marketplace-search { display: flex; justify-content: center; gap: 12px; flex-wrap: wrap; }
      .marketplace-search input { min-width: min(420px, 100%); padding: 12px 16px; border-radius: 10px; border: 1px solid rgba(0,0,0,.15); }
      .marketplace-search button { padding: 12px 18px; border-radius: 10px; border: none; background: #111; color: #fff; }
      .marketplace-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; padding: 24px 0 40px; }
      .marketplace-card { border: 1px solid rgba(0,0,0,.08); border-radius: 16px; padding: 18px; background: #fff; box-shadow: 0 6px 18px rgba(0,0,0,.05); display: flex; flex-direction: column; gap: 10px; }
      .marketplace-card h3 { margin: 0; font-size: 1.05rem; }
      .marketplace-card p { margin: 0; color: #333; font-size: 0.95rem; }
      .marketplace-meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 0.85rem; color: #555; }
      .marketplace-meta span { background: #f3f4f6; padding: 4px 10px; border-radius: 999px; }
      .marketplace-actions { margin-top: auto; display: flex; justify-content: space-between; align-items: center; gap: 8px; }
      .marketplace-actions a { text-decoration: none; font-weight: 600; color: #1f4cff; }
      .marketplace-actions .visit-link { font-weight: 500; color: #111; }
      .marketplace-pagination { display: flex; justify-content: center; gap: 10px; padding-bottom: 50px; }
      .marketplace-pagination a, .marketplace-pagination span { padding: 8px 12px; border-radius: 10px; border: 1px solid rgba(0,0,0,.12); text-decoration: none; color: #111; }
      .marketplace-pagination .active { background: #111; color: #fff; border-color: #111; }
      .marketplace-empty { text-align: center; padding: 40px 0; color: #555; }
      .marketplace-error { text-align: center; padding: 20px; color: #b00020; }
      .marketplace-thumb { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; background: #f3f4f6; }
      .marketplace-title { display: flex; align-items: center; gap: 12px; }
    </style>
    <script src="/assets/js/header.js" defer></script>
    <script src="/assets/js/mobile-nav.js" defer></script>
  </head>
  <body>
    <div id="site-header"></div>
    <main class="container">
      <section class="marketplace-hero">
        <h1>Marketplace</h1>
        <p>Browse and discover software from the Kama ZenNext database.</p>
        <form class="marketplace-search" action="/marketplace/" method="get">
          <input type="search" name="q" value="<?php echo kzn_escape($searchTerm); ?>" placeholder="Search by name, description, or category" />
          <button type="submit">Search</button>
        </form>
      </section>

      <?php if ($error !== null): ?>
        <div class="marketplace-error"><?php echo kzn_escape($error); ?></div>
      <?php elseif ($items === []): ?>
        <div class="marketplace-empty">No software found. Try adjusting your search.</div>
      <?php else: ?>
        <section class="marketplace-grid">
          <?php foreach ($items as $item): ?>
            <?php
              $name = $nameColumn ? (string) ($item[$nameColumn] ?? '') : '';
              $description = $descriptionColumn ? (string) ($item[$descriptionColumn] ?? '') : '';
              $category = $categoryColumn ? (string) ($item[$categoryColumn] ?? '') : '';
              $pricing = $pricingColumn ? (string) ($item[$pricingColumn] ?? '') : '';
              $website = $websiteColumn ? (string) ($item[$websiteColumn] ?? '') : '';
              $image = $imageColumn ? (string) ($item[$imageColumn] ?? '') : '';
              $slugValue = $slugColumn ? (string) ($item[$slugColumn] ?? '') : '';
              $idValue = $idColumn ? (string) ($item[$idColumn] ?? '') : '';
              $detailUrl = '#';
              if ($slugValue !== '') {
                  $detailUrl = '/marketplace/view.php?slug=' . rawurlencode($slugValue);
              } elseif ($idValue !== '') {
                  $detailUrl = '/marketplace/view.php?id=' . rawurlencode($idValue);
              }
            ?>
            <article class="marketplace-card">
              <div class="marketplace-title">
                <?php if ($image !== ''): ?>
                  <img class="marketplace-thumb" src="<?php echo kzn_escape($image); ?>" alt="" loading="lazy" />
                <?php else: ?>
                  <span class="marketplace-thumb"></span>
                <?php endif; ?>
                <h3><?php echo kzn_escape($name !== '' ? $name : 'Untitled software'); ?></h3>
              </div>
              <?php if ($description !== ''): ?>
                <p><?php echo kzn_escape($description); ?></p>
              <?php endif; ?>
              <div class="marketplace-meta">
                <?php if ($category !== ''): ?>
                  <span><?php echo kzn_escape($category); ?></span>
                <?php endif; ?>
                <?php if ($pricing !== ''): ?>
                  <span><?php echo kzn_escape($pricing); ?></span>
                <?php endif; ?>
              </div>
              <div class="marketplace-actions">
                <a href="<?php echo kzn_escape($detailUrl); ?>">View details</a>
                <?php if ($website !== ''): ?>
                  <a class="visit-link" href="<?php echo kzn_escape($website); ?>" rel="noopener" target="_blank">Visit site</a>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </section>

        <?php if ($totalPages > 1): ?>
          <nav class="marketplace-pagination" aria-label="Marketplace pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <?php if ($i === $page): ?>
                <span class="active"><?php echo $i; ?></span>
              <?php else: ?>
                <a href="<?php echo kzn_escape($buildPageUrl($i)); ?>"><?php echo $i; ?></a>
              <?php endif; ?>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </body>
</html>
