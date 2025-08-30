<?php
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// Add new recipient
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name  = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    if ($name !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $stmt = $mysqli->prepare("INSERT INTO master_schedule_recipients (name,email,active) VALUES (?,?,1)");
        $stmt->bind_param('ss', $name, $email);
        $stmt->execute();
        $stmt->close();
    }
    header('Location: master_recipients.php');
    exit;
}

// Toggle active status
if (isset($_GET['toggle'])) {
    $id = (int)$_GET['toggle'];
    $mysqli->query("UPDATE master_schedule_recipients SET active = 1 - active WHERE id = {$id}");
    header('Location: master_recipients.php');
    exit;
}

// Delete recipient
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $mysqli->prepare("DELETE FROM master_schedule_recipients WHERE id=?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    header('Location: master_recipients.php');
    exit;
}

$recipients = [];
$res = $mysqli->query("SELECT id,name,email,active FROM master_schedule_recipients ORDER BY name");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $recipients[] = $row;
    }
    $res->close();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Master Schedule Recipients</title>
  <style>
    body { font-family:sans-serif; margin:0; background:#f5f5f5; }
    .container { max-width:800px; margin:0 auto; padding:20px; }
    table { width:100%; border-collapse:collapse; margin-bottom:2rem; background:#fff; }
    th, td { border:1px solid #ccc; padding:8px; text-align:left; }
    th { background:#eee; }
    form.add-form { background:#fff; padding:15px; border:1px solid #ccc; }
    label { display:block; margin-top:0.5rem; }
    input[type=text], input[type=email] { width:100%; padding:0.5rem; }
    button { margin-top:1rem; padding:0.5rem 1rem; }
    a.btn { padding:0.25rem 0.5rem; background:#0069d9; color:#fff; text-decoration:none; border-radius:3px; }
    a.btn:hover { background:#0053ba; }
  </style>
</head>
<body>
  <div class="container">
    <h1>Master Schedule Recipients</h1>
    <table>
      <tr><th>Name</th><th>Email</th><th>Active</th><th>Actions</th></tr>
      <?php foreach ($recipients as $r): ?>
      <tr>
        <td><?= htmlspecialchars($r['name']) ?></td>
        <td><?= htmlspecialchars($r['email']) ?></td>
        <td><?= $r['active'] ? 'Yes' : 'No' ?></td>
        <td>
          <a class="btn" href="?toggle=<?= $r['id'] ?>">Toggle</a>
          <a class="btn" href="?delete=<?= $r['id'] ?>" onclick="return confirm('Delete this recipient?')">Delete</a>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>

    <form method="post" class="add-form">
      <h2>Add Recipient</h2>
      <label>Name
        <input type="text" name="name" required />
      </label>
      <label>Email
        <input type="email" name="email" required />
      </label>
      <button type="submit">Add</button>
    </form>
  </div>
</body>
</html>