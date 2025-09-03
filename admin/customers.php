<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ids.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// Basic user-agent check to detect mobile devices
$isMobile = preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $pref = ($_POST['preferred_contractor_id'] ?? '') !== '' ? (int)$_POST['preferred_contractor_id'] : null;
  $sales = trim($_POST['default_salesman'] ?? '');
  $jobn = trim($_POST['last_job_number'] ?? '');
  $jobn = $jobn !== '' ? $jobn : null;
  $loc = trim($_POST['default_location'] ?? '');
  $notes = trim($_POST['standard_notes'] ?? '');
  if ($name !== '') {
    if ($id > 0) {
      $stmt = $mysqli->prepare('UPDATE customers SET name=?, preferred_contractor_id=?, default_salesman=?, last_job_number=?, default_location=?, standard_notes=? WHERE id=?');
      if ($stmt) {
        $stmt->bind_param('sissssi', $name, $pref, $sales, $jobn, $loc, $notes, $id);
        if (!$stmt->execute()) {
          $err = $stmt->error;
          error_log('DB execute failed: ' . $err);
          echo '<p>DB execute failed: ' . htmlspecialchars($err) . '</p>';
          exit;
        }
      } else {
        $err = $mysqli->error;
        error_log('DB prepare failed: ' . $err);
        echo '<p>DB prepare failed: ' . htmlspecialchars($err) . '</p>';
        exit;
      }
    } else {
      $uid = ulid();
      $stmt = $mysqli->prepare('INSERT INTO customers (uid, name, preferred_contractor_id, default_salesman, last_job_number, default_location, standard_notes) VALUES (?,?,?,?,?,?,?)');
      if ($stmt) {
        $stmt->bind_param('ssisiss', $uid, $name, $pref, $sales, $jobn, $loc, $notes);
        if (!$stmt->execute()) {
          $err = $stmt->error;
          error_log('DB execute failed: ' . $err);
          echo '<p>DB execute failed: ' . htmlspecialchars($err) . '</p>';
          exit;
        }
      } else {
        $err = $mysqli->error;
        error_log('DB prepare failed: ' . $err);
        echo '<p>DB prepare failed: ' . htmlspecialchars($err) . '</p>';
        exit;
      }
    }
  }
  header('Location: customers.php'); exit;
}

$contractors = [];
$resC = $mysqli->query('SELECT id, name FROM contractors ORDER BY name ASC');
if ($resC) { while($r=$resC->fetch_assoc()) $contractors[]=$r; }

$salesmen = [];
$resS = $mysqli->query('SELECT name FROM salesmen ORDER BY name ASC');
if ($resS) { while($r=$resS->fetch_assoc()) $salesmen[]=$r['name']; }

$res = $mysqli->query('SELECT id, name, preferred_contractor_id, default_salesman, last_job_number, default_location, standard_notes FROM customers ORDER BY name ASC');
$customers = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
function contractorSelect($current, $contractors){
  $html='<select name="preferred_contractor_id"><option value="">--</option>';
  foreach($contractors as $c){
    $sel = ($current && (int)$current == (int)$c['id']) ? 'selected' : '';
    $html .= '<option value="'.(int)$c['id'].'" '.$sel.'>'.htmlspecialchars($c['name']).'</option>';
  }
  return $html.'</select>';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Customers Admin</title>
  <link rel="stylesheet" href="admin.css" />
<style>
body{font-family:sans-serif;margin:20px;background:#f6f7fb;color:#0f172a}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #e5e7eb;padding:8px;vertical-align:top}
form.inline{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
form.inline input,form.inline textarea,form.inline select{padding:4px}
@media(max-width:600px){form.inline{flex-direction:column;align-items:stretch}}
</style>
</head>
<body class="<?= $isMobile ? 'mobile' : 'desktop' ?>">
<div class="admin-nav">
  <a class="btn" href="index.php">Back to Admin Panel</a>
  <a class="btn" href="../">Back to Schedule</a>
</div>
<h1>Customers</h1>
<datalist id="salesmen-list">
<?php foreach($salesmen as $s): ?>
  <option value="<?= htmlspecialchars($s) ?>"></option>
<?php endforeach; ?>
</datalist>
<table>
<tr><th>Name</th><th>Preferred Contractor</th><th>Salesman</th><th>Job #</th><th>Location</th><th>Notes</th><th>Action</th></tr>
<?php foreach($customers as $c): ?>
<tr><td colspan="7">
<form method="post" class="inline">
<input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
<input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" />
<?= contractorSelect($c['preferred_contractor_id'], $contractors) ?>
<input type="text" name="default_salesman" value="<?= htmlspecialchars($c['default_salesman']) ?>" list="salesmen-list" />
<input type="text" name="last_job_number" value="<?= htmlspecialchars($c['last_job_number']) ?>" />
<input type="text" name="default_location" value="<?= htmlspecialchars($c['default_location']) ?>" />
<textarea name="standard_notes"><?= htmlspecialchars($c['standard_notes']) ?></textarea>
<button type="submit" class="btn">Save</button>
</form>
</td></tr>
<?php endforeach; ?>
<tr><td colspan="7">
<form method="post" class="inline">
<input type="text" name="name" placeholder="Customer name" />
<?= contractorSelect(null, $contractors) ?>
<input type="text" name="default_salesman" placeholder="Salesman" list="salesmen-list" />
<input type="text" name="last_job_number" placeholder="Job #" />
<input type="text" name="default_location" placeholder="Typical location" />
<textarea name="standard_notes" placeholder="Standard notes"></textarea>
<button type="submit" class="btn">Add</button>
</form>
</td></tr>
</table>
</body>
</html>