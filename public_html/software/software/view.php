<?php
include "../api/db.php";

if (!isset($_GET['id'])) {
    die("Invalid software");
}

$id = intval($_GET['id']);

// Fetch approved software only
$sql = "SELECT software.*, vendors.company
        FROM software
        JOIN vendors ON software.vendor_id = vendors.id
        WHERE software.id = $id AND software.status='approved'
        LIMIT 1";

$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) !== 1) {
    die("Software not found");
}

$data = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($data['name']); ?> – Software Details</title>
  <meta name="description" content="<?php echo htmlspecialchars(substr($data['description'],0,150)); ?>">
</head>
<body>

<h1><?php echo htmlspecialchars($data['name']); ?></h1>

<p><strong>Category:</strong> <?php echo htmlspecialchars($data['category']); ?></p>
<p><strong>Vendor:</strong> <?php echo htmlspecialchars($data['company']); ?></p>
<p><strong>Description:</strong><br>
<?php echo nl2br(htmlspecialchars($data['description'])); ?>
</p>
<p><strong>Pricing:</strong> <?php echo htmlspecialchars($data['price']); ?></p>

<a href="<?php echo htmlspecialchars($data['website']); ?>" target="_blank">
  👉 Visit Official Website
</a>

<hr>

<p>
  Are you the vendor?
  <a href="/vendor/dashboard.php">Upgrade to Featured Listing</a>
</p>

</body>
</html>
