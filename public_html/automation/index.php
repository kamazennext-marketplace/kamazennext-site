<?php

declare(strict_types=1);

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

$automationTools = array_values(array_filter($products, static function ($product): bool {
    return is_array($product) && (($product['category'] ?? '') === 'Automation');
}));

usort($automationTools, static function (array $a, array $b): int {
    $rankA = $a['featured_rank'] ?? null;
    $rankB = $b['featured_rank'] ?? null;
    if ($rankA !== null || $rankB !== null) {
        $rankA = $rankA === null ? PHP_INT_MAX : (int) $rankA;
        $rankB = $rankB === null ? PHP_INT_MAX : (int) $rankB;
        if ($rankA !== $rankB) {
            return $rankA <=> $rankB;
        }
    }

    $featuredA = !empty($a['featured']);
    $featuredB = !empty($b['featured']);
    if ($featuredA !== $featuredB) {
        return $featuredA ? -1 : 1;
    }

    return strtotime((string) ($b['last_updated'] ?? '')) <=> strtotime((string) ($a['last_updated'] ?? ''));
});

$topPicks = array_slice($automationTools, 0, 10);
$compareIds = array_values(array_filter(array_map(static function ($product): string {
    return (string) ($product['id'] ?? $product['slug'] ?? '');
}, $topPicks)));
$compareIds = array_slice($compareIds, 0, 4);

$buildOutUrl = static function (array $product, string $from): string {
    $identifier = (string) ($product['slug'] ?? $product['id'] ?? '');
    if ($identifier === '') {
        return '#';
    }
    $query = http_build_query([
        'slug' => $identifier,
        'from' => $from
    ]);
    return '/out.php?' . $query;
};

$baseUrl = 'https://kamazennext.com';
$canonicalUrl = $baseUrl . '/automation/';

$faqItems = [
    [
        'q' => 'What is an automation tool?',
        'a' => 'Automation tools help teams connect apps, trigger workflows, and remove manual steps with rules, integrations, and AI-driven actions.'
    ],
    [
        'q' => 'Which automation tools are best Zapier alternatives?',
        'a' => 'Look for platforms that match your integration needs, pricing model, and developer features like webhooks, API access, and custom steps.'
    ],
    [
        'q' => 'How do I compare automation tools quickly?',
        'a' => 'Shortlist 3–4 tools, review pricing and integrations, then test the same workflow to see which fits your team.'
    ],
    [
        'q' => 'Do automation tools support API access?',
        'a' => 'Many top automation tools provide APIs or webhook support, which is essential for custom workflows and advanced integrations.'
    ]
];

$itemListElements = array_values(array_map(static function (array $product, int $index) use ($baseUrl): array {
    $identifier = (string) ($product['slug'] ?? $product['id'] ?? '');
    return [
        '@type' => 'ListItem',
        'position' => $index + 1,
        'name' => (string) ($product['name'] ?? 'Automation tool'),
        'url' => $identifier !== '' ? $baseUrl . '/p/' . rawurlencode($identifier) : $baseUrl . '/software.html'
    ];
}, $topPicks, array_keys($topPicks)));

$faqSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => array_map(static function (array $faq): array {
        return [
            '@type' => 'Question',
            'name' => $faq['q'],
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['a']
            ]
        ];
    }, $faqItems)
];

$breadcrumbSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        [
            '@type' => 'ListItem',
            'position' => 1,
            'name' => 'Home',
            'item' => $baseUrl . '/'
        ],
        [
            '@type' => 'ListItem',
            'position' => 2,
            'name' => 'Automation',
            'item' => $canonicalUrl
        ]
    ]
];

$itemListSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ItemList',
    'itemListElement' => $itemListElements
];

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Best Automation Tools (2026) | Kama ZenNext</title>
  <meta name="description" content="Compare the best automation tools of 2026. Review Zapier alternatives, workflow automation platforms, and integration-first tools before you buy.">
  <link rel="canonical" href="<?php echo h($canonicalUrl); ?>">
  <link rel="stylesheet" href="/assets/css/styles.css?v=20260106">
  <link rel="stylesheet" href="/assets/css/theme-light.css?v=20260106">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <script src="/assets/js/header.js" defer></script>
  <script src="/assets/js/mobile-nav.js" defer></script>
  <style>
    body { background: #F6F7FB; }
    main { max-width: 1200px; margin: 32px auto 96px; padding: 0 20px; }
    .hero { display: grid; gap: 18px; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); align-items: center; }
    .hero-card { background: white; border-radius: 18px; padding: 28px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08); position: relative; overflow: hidden; }
    .hero-accent { width: 120px; height: 120px; border-radius: 999px; background: linear-gradient(135deg, rgba(99, 102, 241, 0.22), rgba(236, 72, 153, 0.2)); position: absolute; top: -40px; right: -30px; }
    .hero h1 { margin: 0; font-size: 2.6rem; letter-spacing: -0.02em; }
    .hero p { margin: 0; color: var(--muted); }
    .hero-actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 18px; }
    .btn-primary-gradient { background: linear-gradient(135deg, #6366f1, #ec4899); border: none; color: white; }
    .btn-primary-gradient:hover { filter: brightness(1.05); }
    .section { margin-top: 48px; }
    .section h2 { margin: 0 0 10px; font-size: 1.8rem; }
    .section p { margin: 0 0 20px; color: var(--muted); }
    .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 18px; }
    .card { background: white; border-radius: 16px; padding: 18px; box-shadow: 0 12px 24px rgba(15, 23, 42, 0.06); border: 1px solid rgba(226, 232, 240, 0.9); }
    .card h3 { margin: 12px 0 6px; font-size: 1.1rem; }
    .card .meta-row { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
    .badge-soft { background: #eef2ff; color: #4338ca; }
    .card-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 12px; }
    .disclosure { font-size: 0.92rem; color: var(--muted); background: #eef2ff; border-radius: 10px; padding: 10px 14px; margin-bottom: 16px; }
    .faq details { background: white; border-radius: 12px; padding: 14px 16px; border: 1px solid rgba(226, 232, 240, 0.9); box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05); }
    .faq summary { font-weight: 600; cursor: pointer; }
    .faq details + details { margin-top: 12px; }
    @media (max-width: 700px) {
      .hero h1 { font-size: 2.1rem; }
    }
  </style>
</head>
<body>
  <div id="site-header"></div>
  <main>
    <section class="hero">
      <div>
        <h1>Best Automation Tools (2026)</h1>
        <p>Compare Zapier alternatives, workflow automation platforms, and integration-first tools built for modern teams.</p>
        <p>Pick the right automation stack to eliminate manual work, connect your apps, and scale operations faster.</p>
        <div class="hero-actions">
          <a class="btn btn-primary btn-primary-gradient" href="#top-picks" id="compareCta" data-compare='<?php echo h(json_encode($compareIds)); ?>'>Compare now</a>
          <a class="btn btn-secondary" href="/software.html?cat=Automation">Browse all automation tools</a>
        </div>
      </div>
      <div class="hero-card">
        <div class="hero-accent" aria-hidden="true"></div>
        <h3 style="margin:0 0 8px;">Workflow-ready top picks</h3>
        <p style="margin:0 0 18px; color: var(--muted);">Hand-picked automation platforms for integrations, reliability, and modern workflows.</p>
        <div style="display:flex; gap:12px; flex-wrap: wrap;">
          <?php foreach (array_slice($topPicks, 0, 3) as $pick): ?>
            <span class="badge badge-soft"><?php echo h((string) ($pick['name'] ?? 'Automation tool')); ?></span>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section" id="top-picks">
      <h2>Top picks</h2>
      <p>Our highest-impact automation tools for integration depth, workflow flexibility, and team visibility.</p>
      <div class="disclosure">Affiliate disclosure: Some links may earn us a commission if you choose a partner product at no extra cost to you.</div>
      <div class="grid">
        <?php foreach ($topPicks as $tool): ?>
          <article class="card">
            <div class="meta-row">
              <span class="badge">Automation</span>
              <?php if (!empty($tool['pricing']['model'])): ?>
                <span class="badge badge-soft"><?php echo h((string) $tool['pricing']['model']); ?></span>
              <?php endif; ?>
              <?php if (!empty($tool['api'])): ?>
                <span class="badge badge-soft">API</span>
              <?php endif; ?>
            </div>
            <h3><?php echo h((string) ($tool['name'] ?? 'Automation tool')); ?></h3>
            <p class="muted"><?php echo h((string) ($tool['tagline'] ?? '')); ?></p>
            <div class="card-actions">
              <a class="btn" href="/p/<?php echo rawurlencode((string) ($tool['slug'] ?? $tool['id'] ?? '')); ?>">View</a>
              <a class="btn btn-primary btn-primary-gradient" href="<?php echo h($buildOutUrl($tool, 'automation')); ?>" target="_blank" rel="nofollow sponsored noopener">Visit</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section" id="all-tools">
      <h2>All Automation Tools</h2>
      <p>Explore the full automation catalog filtered by the Automation category.</p>
      <div class="grid">
        <?php foreach ($automationTools as $tool): ?>
          <article class="card">
            <div class="meta-row">
              <span class="badge">Automation</span>
              <?php if (!empty($tool['platforms'])): ?>
                <span class="badge badge-soft"><?php echo h(is_array($tool['platforms']) ? implode(', ', $tool['platforms']) : (string) $tool['platforms']); ?></span>
              <?php endif; ?>
            </div>
            <h3><?php echo h((string) ($tool['name'] ?? 'Automation tool')); ?></h3>
            <p class="muted"><?php echo h((string) ($tool['tagline'] ?? '')); ?></p>
            <div class="card-actions">
              <a class="btn" href="/p/<?php echo rawurlencode((string) ($tool['slug'] ?? $tool['id'] ?? '')); ?>">View</a>
              <a class="btn btn-secondary" href="<?php echo h($buildOutUrl($tool, 'automation')); ?>" target="_blank" rel="nofollow sponsored noopener">Visit</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="section faq" id="faq">
      <h2>FAQ</h2>
      <?php foreach ($faqItems as $faq): ?>
        <details>
          <summary><?php echo h($faq['q']); ?></summary>
          <p><?php echo h($faq['a']); ?></p>
        </details>
      <?php endforeach; ?>
    </section>
  </main>

  <script>
    const compareButton = document.getElementById('compareCta');
    if (compareButton) {
      compareButton.addEventListener('click', (event) => {
        const payload = compareButton.getAttribute('data-compare');
        if (!payload) return;
        event.preventDefault();
        try {
          const ids = JSON.parse(payload);
          if (Array.isArray(ids) && ids.length) {
            localStorage.setItem('kz_compare_ids', JSON.stringify(ids.slice(0, 4)));
          }
        } catch (error) {
          console.warn('Unable to set compare list', error);
        }
        window.location.href = '/compare.html';
      });
    }
  </script>

  <script type="application/ld+json">
    <?php echo json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
  </script>
  <script type="application/ld+json">
    <?php echo json_encode($itemListSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
  </script>
  <script type="application/ld+json">
    <?php echo json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT); ?>
  </script>
</body>
</html>
