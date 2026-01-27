<?php
header('Content-Type: text/plain; charset=utf-8');
echo "MARKETPLACE VIEW OK\n";
echo "query=" . ($_GET['slug'] ?? ($_GET['id'] ?? 'none')) . "\n";
?>
