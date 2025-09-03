<?php
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cid = (int)($_POST['contractor_id'] ?? 0);
    $driver = trim($_POST['driver_id'] ?? '');
    if ($cid && $driver !== '') {
        $stmt = $mysqli->prepare("SELECT name FROM contractors WHERE id=? AND driver_id=? AND active=1");
        $stmt->bind_param('is', $cid, $driver);
        $stmt->execute();
        $stmt->bind_result($name);
        if ($stmt->fetch()) {
            $_SESSION['role'] = 'contractor';
            $_SESSION['contractor_id'] = $cid;
            $_SESSION['contractor_name'] = $name;
            header('Location: contractor/index.php');
            exit;
        } else {
            $error = 'Invalid driver ID';
        }
        $stmt->close();
    } else {
        $error = 'Please select your name and enter driver ID';
    }
}
$cons = [];
$res = $mysqli->query("SELECT id, name FROM contractors WHERE active=1 ORDER BY name ASC");
while ($row = $res->fetch_assoc()) { $cons[] = $row; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Contractor Login — <?= SCHEDULE_NAME ?></title>
  <style>
    body { font-family: sans-serif; padding:20px; }
    select, input { font-size:1.2rem; width:100%; padding:8px; margin-top:8px; }
    button { font-size:1.2rem; padding:10px 20px; margin-top:16px; width:100%; }
    .error { color:red; }
  </style>
</head>
<body>
  <h2><?= SCHEDULE_NAME ?> Contractor Login</h2>
  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <form method="post">
    <label>Select Your Name
      <select name="contractor_id" id="contractor">
        <option value="">-- Choose --</option>
        <?php foreach ($cons as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <div id="driverField" style="display:none;">
      <label>Driver ID
        <input type="text" name="driver_id" />
      </label>
    </div>
    <button type="submit">Login</button>
  </form>
  <script>
    const sel = document.getElementById('contractor');
    const df = document.getElementById('driverField');
    sel.addEventListener('change', ()=>{ df.style.display = sel.value ? '' : 'none'; });
  </script>
</body>
</html>