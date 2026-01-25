<?php
header('Content-Type: text/plain; charset=utf-8');
echo "MARKETPLACE VIEW OK\n";
echo "slug=" . ($_GET['slug'] ?? 'none') . "\n";
echo "id=" . ($_GET['id'] ?? 'none') . "\n";
?>
