<?php
session_start();

// CHECK LOGIN
if (!isset($_SESSION['admin'])) {
    echo "Unauthorized";
    exit;
}

include "../api/db.php";

// Get ID and Action
$id = $_GET['id'];
$action = $_GET['action'];

if ($action == "approve") {
    $sql = "UPDATE software SET status='approved' WHERE id=$id";
} elseif ($action == "reject") {
    $sql = "DELETE FROM software WHERE id=$id";
}

mysqli_query($conn, $sql);

// Redirect back to dashboard
header("Location: dashboard.php");
exit;
?>
