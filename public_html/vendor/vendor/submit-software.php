<?php
session_start();

// Allow only logged-in vendors
if (!isset($_SESSION['vendor_id'])) {
    header("Location: /forms/vendor-login.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Submit Software | KAMA ZENNEXT</title>
</head>
<body>

<h2>Submit Your Software</h2>

<form action="/api/save-software.php" method="POST">

  <label>Software Name</label><br>
  <input type="text" name="name" required><br><br>

  <label>Category</label><br>
  <select name="category" required>
    <option value="CRM">CRM</option>
    <option value="Accounting">Accounting</option>
    <option value="AI & Automation">AI & Automation</option>
    <option value="Security">Security</option>
  </select><br><br>

  <label>Description</label><br>
  <textarea name="description" required></textarea><br><br>

  <label>Website URL</label><br>
  <input type="url" name="website" required><br><br>

  <label>Pricing</label><br>
  <input type="text" name="price" placeholder="₹ / Free / Trial"><br><br>

  <button type="submit">Submit Software</button>

</form>

</body>
</html>
