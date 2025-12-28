<?php
$DB_HOST = "localhost";
$DB_USER = "kamazenn_user";
$DB_PASS = "Kamazennext@123";
$DB_NAME = "kamazenn_db";

$conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}
?>
