<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'contractor') { header('Location: ../contractor_login.php'); exit; }
$name = $_SESSION['contractor_name'] ?? '';
$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime('+1 day'));
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contractor Portal</title>
  <style>
    body { font-family: sans-serif; padding:20px; text-align:center; }
    h2 { margin-bottom:30px; }
    a.btn { display:block; margin:10px 0; padding:20px; background:#0e4baa; color:#fff; text-decoration:none; border-radius:8px; font-size:1.2rem; }
  </style>
</head>
<body>
  <h2>Welcome <?= htmlspecialchars($name) ?></h2>
  <a class="btn" href="schedule.php?date=<?= $today ?>">Today's Schedule</a>
  <a class="btn" href="schedule.php?date=<?= $tomorrow ?>">Tomorrow's Schedule</a>
  <p><a href="../logout.php">Logout</a></p>
</body>
</html>