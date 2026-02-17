<?php
session_start();
include "../api/db.php";

// LOGIN PROCESS
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    // Hard-coded admin credentials
    $ADMIN_USER = "admin";
    $ADMIN_PASS = "Kamazennext@123";

    if ($username == $ADMIN_USER && $password == $ADMIN_PASS) {
        $_SESSION['admin'] = true;
        header("Location: dashboard.php");
        exit;
    } else {
        $error = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Login</title>
    <style>
        body {
            font-family: Arial;
            background: #f2f2f2;
        }
        .box {
            width: 350px;
            margin:100px auto;
            padding: 20px;
            background:white;
            box-shadow: 0 0 10px rgba(0,0,0,0.2);
            border-radius: 8px;
        }
        input {
            width: 100%;
            padding: 10px;
            margin: 8px 0;
            border:1px solid #ccc;
        }
        button {
            width:100%;
            padding:10px;
            background:#7e3af2;
            color:white;
            border:none;
            cursor:pointer;
        }
        button:hover {
            background:#5b28b8;
        }
        .error { 
            color:red; 
            margin-bottom:10px;
        }
    </style>
</head>
<body>

<div class="box">
    <h2>Admin Login</h2>

    <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>

    <form method="POST">
        <input type="text" name="username" placeholder="Admin Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
    </form>
</div>

</body>
</html>
