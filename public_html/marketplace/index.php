<?php
$search = trim($_GET['q'] ?? '');
$items = [];
$dbReady = false;
$pdo = null;
$mysqli = null;

$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__);
$possibleConfigs = [
    $documentRoot . '/config/db.php',
    $documentRoot . '/backend/db.php',
    $documentRoot . '/api/db.php',
];

foreach ($possibleConfigs as $configPath) {
    if (is_file($configPath)) {
        include_once $configPath;
    }
}

foreach (['pdo', 'db', 'conn'] as $candidate) {
    if (isset($GLOBALS[$candidate]) && $GLOBALS[$candidate] instanceof PDO) {
        $pdo = $GLOBALS[$candidate];
        break;
    }
}

if (!$pdo) {
    foreach (['mysqli', 'conn', 'db'] as $candidate) {
        if (isset($GLOBALS[$candidate]) && $GLOBALS[$candidate] instanceof mysqli) {
            $mysqli = $GLOBALS[$candidate];
            break;
        }
    }
}

if (!$pdo && !$mysqli && class_exists('PDO')) {
    $host = defined('DB_HOST') ? DB_HOST : (defined('DB_SERVER') ? DB_SERVER : null);
    $name = defined('DB_NAME') ? DB_NAME : null;
    $user = defined('DB_USER') ? DB_USER : (defined('DB_USERNAME') ? DB_USERNAME : null);
    $pass = defined('DB_PASS') ? DB_PASS : (defined('DB_PASSWORD') ? DB_PASSWORD : null);

    if ($host && $name && $user) {
        try {
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', $host, $name);
            $pdo = new PDO($dsn, $user, $pass ?: '');
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            $pdo = null;
        }
    }
}

$tables = ['software', 'tools'];

if ($pdo) {
    foreach ($tables as $table) {
        try {
            if ($search !== '') {
                $stmt = $pdo->prepare("SELECT id, name, description FROM {$table} WHERE name LIKE :q OR description LIKE :q LIMIT 20");
                $stmt->bindValue(':q', '%' . $search . '%', PDO::PARAM_STR);
            } else {
                $stmt = $pdo->prepare("SELECT id, name, description FROM {$table} LIMIT 20");
            }
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($items)) {
                $dbReady = true;
                break;
            }
        } catch (Exception $e) {
        }
    }
} elseif ($mysqli) {
    foreach ($tables as $table) {
        try {
            if ($search !== '') {
                $stmt = $mysqli->prepare("SELECT id, name, description FROM {$table} WHERE name LIKE ? OR description LIKE ? LIMIT 20");
                $like = '%' . $search . '%';
                $stmt->bind_param('ss', $like, $like);
            } else {
                $stmt = $mysqli->prepare("SELECT id, name, description FROM {$table} LIMIT 20");
            }
            if ($stmt && $stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    $items = $result->fetch_all(MYSQLI_ASSOC);
                }
            }
            if (!empty($items)) {
                $dbReady = true;
                break;
            }
        } catch (Exception $e) {
        }
    }
}

if (!$dbReady) {
    $items = [
        ['name' => 'Zen Productivity Suite', 'description' => 'Calm task management and focus tools.'],
        ['name' => 'Flow Tracker', 'description' => 'Simple time tracking for mindful teams.'],
        ['name' => 'Insight CRM', 'description' => 'Lightweight customer notes and follow-ups.'],
        ['name' => 'Pulse Analytics', 'description' => 'Quick dashboards for daily KPIs.'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kama ZenNext Marketplace</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 24px; background: #f7f7f9; color: #222; }
        .notice { background: #e8f5e9; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; }
        form { margin-bottom: 20px; }
        input[type="search"] { padding: 8px 10px; width: 280px; max-width: 100%; }
        button { padding: 8px 14px; margin-left: 6px; }
        ul { list-style: none; padding: 0; }
        li { background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .meta { font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class="notice">Marketplace page loaded OK</div>

    <form method="get">
        <label for="q">Search tools</label><br>
        <input id="q" name="q" type="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Search by name">
        <button type="submit">Search</button>
    </form>

    <div class="meta">
        <?php if ($dbReady): ?>
            Showing live results from database.
        <?php else: ?>
            Database not available. Showing placeholder items.
        <?php endif; ?>
    </div>

    <ul>
        <?php foreach ($items as $item): ?>
            <li>
                <strong><?php echo htmlspecialchars($item['name'] ?? 'Unnamed tool', ENT_QUOTES, 'UTF-8'); ?></strong><br>
                <?php echo htmlspecialchars($item['description'] ?? 'No description available.', ENT_QUOTES, 'UTF-8'); ?>
            </li>
        <?php endforeach; ?>
    </ul>
</body>
</html>
