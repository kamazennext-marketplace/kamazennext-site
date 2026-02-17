<?php

$baseUrl = 'http://localhost/api/v1'; // Adjust if running locally differently

function testUrl($url)
{
    echo "Testing: $url\n";
    // Simulate a request by including the file explicitly if we can't curl easily in this env,
    // but better to actually curl if we assume a server is running.
    // However, since we are in a CLI env, we might not have a web server running this code.
    // Let's rely on unit-test style instantiation instead.

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = $url;

    // We can't easily "include" index.php multiple times.
    // So let's just use a simple curl status check if the user verification is manual.
    // For now, let's output instructions.
}

echo "To verify, run:\n";
echo "curl http://your-domain.com/api/v1/health\n";
echo "curl http://your-domain.com/api/v1/catalog/tools\n";
echo "curl http://your-domain.com/api/v1/catalog/categories\n";
