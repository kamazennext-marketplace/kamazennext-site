<?php
$host = "localhost";
$user = "kamazenn_user";
$pass = "Kamazennext@123";
$dbname = "kamazenn_db";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if(!$conn){
    die("Database Connection Failed: " . mysqli_connect_error());
}
?>
