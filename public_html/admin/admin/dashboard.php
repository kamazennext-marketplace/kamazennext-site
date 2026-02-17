<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>

    <style>
        body {
            margin: 0;
            font-family: 'Inter', sans-serif;
            background: #f4f4ff;
        }

        /* TOP NAV BAR */
        .topbar {
            background: linear-gradient(90deg, #7e3af2, #00b7ff);
            padding: 15px 30px;
            color: white;
            font-size: 1.4rem;
            font-weight: 700;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 15px;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            transition: .3s;
            font-weight: 600;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.35);
        }

        /* CONTENT BOX */
        .box {
            width: 80%;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 14px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }

        h2 {
            margin-bottom: 15px;
            font-size: 1.8rem;
            color: #1e1b4b;
        }

        /* TABLE STYLE */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }

        th {
            background: #7e3af2;
            color: white;
            padding: 12px;
            text-align: left;
            font-size: 0.95rem;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            font-size: 0.95rem;
            color: #333;
        }

        tr:hover {
            background: #f8f0ff;
        }

        /* BUTTONS */
        .btn {
            padding: 8px 14px;
            text-decoration: none;
            color: white;
            border-radius: 6px;
            font-size: 0.88rem;
            font-weight: 600;
            margin-right: 6px;
            display: inline-block;
        }

        .approve {
            background: #10b981;
        }
        .approve:hover {
            background: #059669;
        }

        .reject {
            background: #ef4444;
        }
        .reject:hover {
            background: #dc2626;
        }

        /* EMPTY STATE */
        .empty {
            text-align: center;
            padding: 30px;
            font-size: 1.1rem;
            color: #666;
        }

    </style>
</head>

<body>

<div class="topbar">
    Admin Dashboard
    <a class="logout-btn" href="logout.php">Logout</a>
</div>

<div class="box">
    <h2>Pending Software Submissions</h2>

    <?php
    include "../api/db.php";
    $result = mysqli_query($conn, "SELECT * FROM software WHERE status='pending'");
    ?>

    <table>
        <tr>
            <th>Title</th>
            <th>Vendor ID</th>
            <th>Action</th>
        </tr>

        <?php 
        if (mysqli_num_rows($result) == 0) {
            echo "<tr><td colspan='3' class='empty'>No pending submissions</td></tr>";
        }

        while ($row = mysqli_fetch_assoc($result)) { ?>
            <tr>
                <td><?php echo $row['name']; ?></td>
                <td><?php echo $row['vendor_id']; ?></td>
                <td>
                    <a class='btn approve' href="approve.php?id=<?php echo $row['id']; ?>&action=approve">Approve</a>
                    <a class='btn reject' href="approve.php?id=<?php echo $row['id']; ?>&action=reject">Reject</a>
                </td>
            </tr>
        <?php } ?>
    </table>
</div>

</body>
</html>
