<?php
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// Basic user-agent check to detect mobile devices
$isMobile = preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  if ($name !== '') {
    if ($id > 0) {
      $stmt = $mysqli->prepare('UPDATE salesmen SET name=?, phone=? WHERE id=?');
      if ($stmt) {
        $stmt->bind_param('ssi', $name, $phone, $id);
        $stmt->execute();
      } else {
        error_log('DB prepare failed: ' . $mysqli->error);
      }
    } else {
      $stmt = $mysqli->prepare('INSERT INTO salesmen (name, phone) VALUES (?, ?)');
      if ($stmt) {
        $stmt->bind_param('ss', $name, $phone);
        $stmt->execute();
      } else {
        error_log('DB prepare failed: ' . $mysqli->error);
      }
    }
  }
  header('Location: salesmen.php'); exit;
}

$res = $mysqli->query('SELECT id, name, phone FROM salesmen ORDER BY name ASC');
$salesmen = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Salesmen Admin</title>
  <link rel="stylesheet" href="admin.css" />
<style>
body{font-family:sans-serif;margin:20px;background:#f6f7fb;color:#0f172a}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #e5e7eb;padding:8px}
form.inline{display:flex;gap:6px;align-items:center}
@media(max-width:600px){form.inline{flex-direction:column;align-items:stretch}}
</style>
</head>
<body class="<?= $isMobile ? 'mobile' : 'desktop' ?>">
<div class="admin-nav">
  <a class="btn" href="index.php">Back to Admin Panel</a>
  <a class="btn" href="../">Back to Schedule</a>
</div>
<h1>Salesmen</h1>
<table>
<tr><th>Name</th><th>Phone</th><th>Action</th></tr>
<?php foreach($salesmen as $s): ?>
<tr>
<td colspan="3">
<form method="post" class="inline">
<input type="hidden" name="id" value="<?= (int)$s['id'] ?>" />
<input type="text" name="name" value="<?= htmlspecialchars($s['name']) ?>" />
<input type="text" name="phone" value="<?= htmlspecialchars($s['phone']) ?>" />
<button type="submit" class="btn">Save</button>
</form>
</td>
</tr>
<?php endforeach; ?>
<tr>
<td colspan="3">
<form method="post" class="inline">
<input type="text" name="name" placeholder="New salesman" />
<input type="text" name="phone" placeholder="Phone" />
<button type="submit" class="btn">Add</button>
</form>
</td>
</tr>
</table>
</body>
</html>