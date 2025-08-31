<?php
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// handle add/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $id = (int)($_POST['id'] ?? 0);
  $name = trim($_POST['name'] ?? '');
  $pref = ($_POST['preferred_contractor_id'] ?? '') !== '' ? (int)$_POST['preferred_contractor_id'] : null;
  $sales = trim($_POST['default_salesman'] ?? '');
  $jobn = trim($_POST['last_job_number'] ?? '');
  $loc = trim($_POST['default_location'] ?? '');
  $notes = trim($_POST['standard_notes'] ?? '');
  if ($name !== '') {
    if ($id > 0) {
      $stmt = $mysqli->prepare('UPDATE customers SET name=?, preferred_contractor_id=?, default_salesman=?, last_job_number=?, default_location=?, standard_notes=? WHERE id=?');
      $stmt->bind_param('sissssi', $name, $pref, $sales, $jobn, $loc, $notes, $id);
      $stmt->execute();
    } else {
      $stmt = $mysqli->prepare('INSERT INTO customers (name, preferred_contractor_id, default_salesman, last_job_number, default_location, standard_notes) VALUES (?,?,?,?,?,?)');
      $stmt->bind_param('sissss', $name, $pref, $sales, $jobn, $loc, $notes);
      $stmt->execute();
    }
  }
  header('Location: customers.php'); exit;
}

$contractors = [];
$resC = $mysqli->query('SELECT id, name FROM contractors ORDER BY name ASC');
if ($resC) { while($r=$resC->fetch_assoc()) $contractors[]=$r; }
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
<title>Customers Admin</title>
<style>
body{font-family:sans-serif;margin:20px;background:#f6f7fb;color:#0f172a}
table{border-collapse:collapse;width:100%;background:#fff}
th,td{border:1px solid #e5e7eb;padding:8px;vertical-align:top}
form.inline{display:flex;flex-wrap:wrap;gap:6px;align-items:center}
form.inline input,form.inline textarea,form.inline select{padding:4px}
</style>
</head>
<body>
<h1>Customers</h1>
<table>
<tr><th>Name</th><th>Preferred Contractor</th><th>Salesman</th><th>Job #</th><th>Location</th><th>Notes</th><th>Action</th></tr>
<?php foreach($customers as $c): ?>
<tr><td colspan="7">
<form method="post" class="inline">
<input type="hidden" name="id" value="<?= (int)$c['id'] ?>" />
<input type="text" name="name" value="<?= htmlspecialchars($c['name']) ?>" />
<?= contractorSelect($c['preferred_contractor_id'], $contractors) ?>
<input type="text" name="default_salesman" value="<?= htmlspecialchars($c['default_salesman']) ?>" />
<input type="text" name="last_job_number" value="<?= htmlspecialchars($c['last_job_number']) ?>" />
<input type="text" name="default_location" value="<?= htmlspecialchars($c['default_location']) ?>" />
<textarea name="standard_notes"><?= htmlspecialchars($c['standard_notes']) ?></textarea>
<button type="submit">Save</button>
</form>
</td></tr>
<?php endforeach; ?>
<tr><td colspan="7">
<form method="post" class="inline">
<input type="text" name="name" placeholder="Customer name" />
<?= contractorSelect(null, $contractors) ?>
<input type="text" name="default_salesman" placeholder="Salesman" />
<input type="text" name="last_job_number" placeholder="Job #" />
<input type="text" name="default_location" placeholder="Typical location" />
<textarea name="standard_notes" placeholder="Standard notes"></textarea>
<button type="submit">Add</button>
</form>
</td></tr>
</table>
</body>
</html>