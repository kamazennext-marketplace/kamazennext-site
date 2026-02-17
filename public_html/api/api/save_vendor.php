<?php
include "db.php";

$company = $_POST['company'];
$email   = $_POST['email'];
$plan    = $_POST['plan'];

$conn->query("INSERT INTO vendors (company,email,plan)
VALUES ('$company','$email','$plan')");

header("Location: /vendor/payment.php?amount=$plan");
exit;
