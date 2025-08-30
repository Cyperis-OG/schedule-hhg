<?php
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim($_POST['email'] ?? '');
  $pass  = trim($_POST['password'] ?? '');
  if ($email !== '' && $pass !== '') {
    $stmt = $mysqli->prepare("SELECT id, password_hash, role FROM users WHERE email=? LIMIT 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $stmt->bind_result($id, $hash, $role);
    if ($stmt->fetch() && password_verify($pass, $hash) && $role === 'admin') {
      $_SESSION['user_id'] = $id;
      $_SESSION['role'] = $role;
      header('Location: ./admin/');
      exit;
    } else {
      $error = 'Invalid credentials';
    }
    $stmt->close();
  } else {
    $error = 'Email and password required';
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Login</title>
  <style>
    body { font-family: sans-serif; padding:20px; }
    label { display:block; margin-top:1rem; }
    input { font-size:1.2rem; width:100%; padding:8px; margin-top:0.5rem; }
    button { font-size:1.2rem; padding:10px 20px; margin-top:1.5rem; width:100%; }
    .error { color:red; }
  </style>
</head>
<body>
  <h1>Admin Login</h1>
  <?php if ($error): ?>
    <p class="error"><?= htmlspecialchars($error) ?></p>
  <?php endif; ?>
  <form method="post">
    <label>Email
      <input type="email" name="email" />
    </label>
    <label>Password
      <input type="password" name="password" />
    </label>
    <button type="submit">Login</button>
  </form>
</body>
</html>