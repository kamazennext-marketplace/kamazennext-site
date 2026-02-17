<?php
$amount = $_GET['amount'] ?? 0;
?>
<!DOCTYPE html>
<html>
<head>
<title>Complete Payment</title>
</head>
<body>

<h2>Complete Your Payment</h2>
<p>Amount: ₹<?= $amount ?></p>

<p>
👉 Integrate Razorpay here  
(Use Razorpay Checkout script)
</p>

<form action="/api/payment_success.php" method="post">
  <input type="hidden" name="amount" value="<?= $amount ?>">
  <button>Mark Payment Successful (TEST)</button>
</form>

</body>
</html>
