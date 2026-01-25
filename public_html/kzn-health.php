<?php
header('Content-Type: text/plain; charset=utf-8');
echo "KZN ROOT PHP OK\n";
echo "uri=" . ($_SERVER['REQUEST_URI'] ?? '') . "\n";
echo "time=" . date('c') . "\n";
?>
