<?php
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Dashboard — Schedule NG</title>
</head>
<body>
  <h1>Admin Dashboard</h1>
  <ul>
    <li><a href="add_job.php">Add Job</a></li>
    <li><a href="contractors.php">Contractors</a></li>
  </ul>
  <p><a href="../">Back to Schedule</a> | <a href="../logout.php">Logout</a></p>
</body>
</html>