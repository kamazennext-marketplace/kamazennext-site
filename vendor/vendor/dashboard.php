<?php
session_start();

// Protect page — allow only logged-in vendors
if (!isset($_SESSION['vendor_id'])) {
    header("Location: /forms/vendor-login.html");
    exit;
}

$vendorName = $_SESSION['vendor_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vendor Dashboard | KAMA ZENNEXT</title>
</head>
<body>

  <h2>Welcome, <?php echo htmlspecialchars($vendorName); ?></h2>

  <p>This is your vendor dashboard.</p>

  <ul>
    <li><a href="/vendor/submit-software.php">Submit Software</a></li>
    <li><a href="/vendor/logout.php">Logout</a></li>
  </ul>

</body>
</html>
