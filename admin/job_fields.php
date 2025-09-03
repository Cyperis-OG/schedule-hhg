<?php
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// Basic user-agent check to detect mobile devices
$isMobile = preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

$cfgPath = SCHEDULE_DIR . 'config/day_fields.json';
$fields = [];
if (file_exists($cfgPath)) {
    $json = file_get_contents($cfgPath);
    $fields = json_decode($json, true) ?: [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = $_POST['key'] ?? [];
    $labels = $_POST['label'] ?? [];
    $enabled = $_POST['enabled'] ?? [];
    $out = [];
    foreach ($keys as $i => $key) {
        $k = preg_replace('/[^a-z0-9_]/i', '', trim($key));
        $label = trim($labels[$i] ?? '');
        if ($k === '' || $label === '') continue;
        $out[] = [
            'key' => $k,
            'label' => $label,
            'enabled' => isset($enabled[$i])
        ];
    }
    file_put_contents($cfgPath, json_encode($out, JSON_PRETTY_PRINT));
    $fields = $out;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Job Fields — <?= SCHEDULE_NAME ?></title>
  <link rel="stylesheet" href="admin.css" />
  <style>
    table{border-collapse:collapse;width:100%;background:#fff}
    td,th{border:1px solid #ccc;padding:4px 8px}
    input[type="text"]{width:160px}
  </style>
  <script>
    function addRow(){
      const tbody=document.getElementById('rows');
      const i=tbody.children.length;
      const tr=document.createElement('tr');
      tr.innerHTML=`<td><input name="key[${i}]" type="text"></td>
                     <td><input name="label[${i}]" type="text"></td>
                     <td style="text-align:center"><input name="enabled[${i}]" type="checkbox" checked></td>`;
      tbody.appendChild(tr);
    }
  </script>
</head>
<body class="<?= $isMobile ? 'mobile' : 'desktop' ?>">
  <div class="admin-nav">
    <a class="btn" href="index.php">Back to Admin Panel</a>
    <a class="btn" href="../">Back to Schedule</a>
  </div>
  <h1>Manage Job Day Fields</h1>
  <form method="post">
    <table>
      <thead><tr><th>Key</th><th>Label</th><th>Enabled</th></tr></thead>
      <tbody id="rows">
        <?php foreach ($fields as $i => $f): ?>
        <tr>
          <td><input name="key[<?= $i ?>]" type="text" value="<?= htmlspecialchars($f['key']) ?>"></td>
          <td><input name="label[<?= $i ?>]" type="text" value="<?= htmlspecialchars($f['label']) ?>"></td>
          <td style="text-align:center"><input name="enabled[<?= $i ?>]" type="checkbox" <?= !empty($f['enabled']) ? 'checked' : '' ?>></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <p>
      <button type="button" class="btn" onclick="addRow()">Add Field</button>
      <button type="submit" class="btn">Save</button>
    </p>
  </form>
</body>
</html>