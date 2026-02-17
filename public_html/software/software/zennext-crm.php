<?php
$software = [
  "name" => "ZenNext CRM",
  "rating" => "4.8",
  "price" => "₹1,499 / month",
  "website" => "https://example.com"
];
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title><?= $software['name'] ?> – Pricing, Reviews & Alternatives</title>
<meta name="description" content="ZenNext CRM pricing, features, reviews and alternatives">
</head>
<body>

<h1><?= $software['name'] ?></h1>
<p>⭐ Rating: <?= $software['rating'] ?>/5</p>
<p>💰 Pricing: <?= $software['price'] ?></p>

<h3>Why choose ZenNext?</h3>
<ul>
  <li>AI-powered lead scoring</li>
  <li>Easy CRM automation</li>
  <li>Indian GST-friendly</li>
</ul>

<h3>Pros</h3>
<ul>
  <li>Fast setup</li>
  <li>Good support</li>
</ul>

<h3>Cons</h3>
<ul>
  <li>No lifetime plan</li>
</ul>

<a href="<?= $software['website'] ?>" target="_blank">
  👉 Try ZenNext CRM
</a>

</body>
</html>
