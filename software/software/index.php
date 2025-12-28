<?php
include "../api/db.php";

// Fetch only approved software
$result = mysqli_query($conn, "
    SELECT software.*, vendors.company 
    FROM software 
    JOIN vendors ON software.vendor_id = vendors.id
    WHERE software.status='approved'
    ORDER BY software.created_at DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Software Marketplace | KAMA ZENNEXT</title>
  <meta name="description" content="Browse approved software tools on KAMA ZENNEXT marketplace">
</head>
<body>

<h1>Approved Software</h1>

<?php
if (mysqli_num_rows($result) === 0) {
    echo "<p>No software available yet.</p>";
}

while ($row = mysqli_fetch_assoc($result)) {
    echo "<hr>";
    echo "<h3><a href='view.php?id=".$row['id']."'>" . htmlspecialchars($row['name']) . "</a></h3>";
    echo "<p><strong>Category:</strong> " . htmlspecialchars($row['category']) . "</p>";
    echo "<p><strong>Vendor:</strong> " . htmlspecialchars($row['company']) . "</p>";
    echo "<p>" . nl2br(htmlspecialchars($row['description'])) . "</p>";
    echo "<p><strong>Pricing:</strong> " . htmlspecialchars($row['price']) . "</p>";
    echo "<a href='" . htmlspecialchars($row['website']) . "' target='_blank'>Visit Website</a>";
}
?>

</body>
</html>
