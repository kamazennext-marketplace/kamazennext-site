<?php
// Minimal page just to confirm routing works (no DB dependency yet)
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Marketplace (Test) – KamazenNext</title>
  <style>
    body{font-family:system-ui;margin:0;background:#0b0f14;color:#eaf0ff}
    .c{max-width:900px;margin:0 auto;padding:24px}
    .card{background:#121824;border:1px solid #202a3a;border-radius:16px;padding:16px;margin-top:14px}
    a{color:#a8b3cf}
  </style>
</head>
<body>
  <div class="c">
    <h1>Marketplace route is working ✅</h1>
    <p>If you can see this page, /marketplace/ is no longer being redirected to the spinner/loader.</p>
    <div class="card">
      <div>Health check: <a href="/marketplace/health.php">/marketplace/health.php</a></div>
      <div>Text check: <a href="/marketplace/test.txt">/marketplace/test.txt</a></div>
    </div>
    <p><a href="/">← Back to home</a></p>
  </div>
</body>
</html>
