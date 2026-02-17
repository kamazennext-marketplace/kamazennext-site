<?php
ini_set('session.cookie_httponly', '1');
if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
    ini_set('session.cookie_secure', '1');
}

session_start();

$configLocations = [
    dirname(__DIR__, 2) . '/admin_config.php',
    __DIR__ . '/admin_config.php',
];

$configLoaded = false;
foreach ($configLocations as $path) {
    if (file_exists($path)) {
        require_once $path;
        $configLoaded = defined('ADMIN_PASSWORD_HASH');
        if ($configLoaded) {
            header('Location: /admin/index.php');
            exit;
        }
    }
}

$hash = '';
$configSnippet = '';
$error = '';
$primaryPath = $configLocations[0];
$fallbackPath = $configLocations[1];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($password === '' || $confirm === '') {
        $error = 'Both password fields are required.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $configSnippet = "<?php\n" .
            "define('ADMIN_PASSWORD_HASH', '{$hash}');\n";
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <style>
        body { max-width: 840px; margin: 0 auto; padding: 32px 16px; font-family: Arial, sans-serif; }
        .panel { background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .panel h1 { margin-top: 0; }
        label { display: block; margin-bottom: 12px; font-weight: 600; }
        input[type="password"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .button { display: inline-block; padding: 10px 16px; background: #007bff; color: #fff; text-decoration: none; border-radius: 4px; border: none; cursor: pointer; }
        .button.secondary { background: #6c757d; }
        .alert { padding: 12px; border-radius: 6px; margin-bottom: 16px; }
        .alert-warning { background: #fff8e1; border: 1px solid #ffe082; }
        .alert-error { background: #ffebee; border: 1px solid #ef9a9a; }
        .alert-success { background: #e8f5e9; border: 1px solid #a5d6a7; }
        textarea { width: 100%; min-height: 80px; padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-family: monospace; }
        .copy-row { display: flex; gap: 10px; align-items: center; margin-top: 8px; }
        code { background: #f5f5f5; padding: 2px 4px; border-radius: 4px; }
        ul { margin-top: 4px; }
    </style>
</head>
<body>
<div class="panel">
    <h1>Admin Setup</h1>
    <p>This helper generates a bcrypt hash for your admin password and shows where to place <code>admin_config.php</code>. This page only works while no valid configuration exists and does not store your password.</p>

    <div class="alert alert-warning">
        <strong>Config file location</strong>
        <ul>
            <li>Primary: <code><?= h($primaryPath) ?></code></li>
            <li>Fallback: <code><?= h($fallbackPath) ?></code></li>
        </ul>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" required autocomplete="new-password">

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required autocomplete="new-password">

        <button type="submit" class="button">Generate Hash</button>
    </form>

    <?php if ($hash): ?>
        <div class="alert alert-success" style="margin-top:16px;">Hash generated. Copy it into your <code>admin_config.php</code>.</div>
        <h3>Your bcrypt hash</h3>
        <textarea readonly id="hashOutput"><?= h($hash) ?></textarea>
        <div class="copy-row">
            <button class="button" type="button" id="copyHash">Copy Hash</button>
        </div>

        <h3>admin_config.php contents</h3>
        <textarea readonly id="configSnippet"><?= h($configSnippet) ?></textarea>
        <div class="copy-row">
            <button class="button" type="button" id="copyConfig">Copy Snippet</button>
        </div>
    <?php endif; ?>

    <div class="alert alert-warning" style="margin-top:16px;">
        <strong>Security reminder:</strong> Once <code>admin_config.php</code> is created, this setup page will redirect to the login. You may delete or disable <code>setup.php</code> after setup.
    </div>

    <p><a class="button secondary" href="/admin/index.php">Back to Admin Login</a></p>
</div>

<script>
const copyButton = document.getElementById('copyHash');
const hashOutput = document.getElementById('hashOutput');
const copyConfigButton = document.getElementById('copyConfig');
const configSnippet = document.getElementById('configSnippet');

if (copyButton && hashOutput) {
    copyButton.addEventListener('click', () => {
        navigator.clipboard.writeText(hashOutput.value)
            .then(() => copyButton.textContent = 'Copied!')
            .catch(() => copyButton.textContent = 'Copy failed');

        setTimeout(() => copyButton.textContent = 'Copy Hash', 1500);
    });
}

if (copyConfigButton && configSnippet) {
    copyConfigButton.addEventListener('click', () => {
        navigator.clipboard.writeText(configSnippet.value)
            .then(() => copyConfigButton.textContent = 'Snippet copied!')
            .catch(() => copyConfigButton.textContent = 'Copy failed');

        setTimeout(() => copyConfigButton.textContent = 'Copy Snippet', 1500);
    });
}
</script>
</body>
</html>
