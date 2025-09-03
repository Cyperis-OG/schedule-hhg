<?php
// api/accept_invite.php  (or /invite/accept.php)
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ids.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  // Show simple signup form
  $token = $_GET['token'] ?? '';
  // fetch token row and verify not expired/used
  // render form with email prefilled -> posts back here
  // (Omitted: full HTML; keep your site look & feel)
} else {
  $token = $_POST['token'] ?? '';
  $name  = trim($_POST['name'] ?? '');
  $pass  = $_POST['password'] ?? '';

  $stmt = $mysqli->prepare("SELECT * FROM invite_tokens WHERE token=? AND used_at IS NULL AND expires_at > NOW()");
  $stmt->bind_param('s', $token);
  $stmt->execute();
  $tok = $stmt->get_result()->fetch_assoc();
  if (!$tok) { http_response_code(400); echo "Invalid or expired token."; exit; }

  $email = $tok['email'];
  $role  = $tok['role'];
  $contractor_id = $tok['contractor_id'];

  $uid = ulid();
  $hash = password_hash($pass, PASSWORD_DEFAULT);

  $u = $mysqli->prepare("INSERT INTO users (uid,email,name,password_hash,role,contractor_id,status) VALUES (?,?,?,?,?,?, 'active')");
  $u->bind_param('sssssi', $uid, $email, $name, $hash, $role, $contractor_id);
  $u->execute();

  $mysqli->query("UPDATE invite_tokens SET used_at=NOW() WHERE id=".(int)$tok['id']." LIMIT 1");

  // Log them in (set $_SESSION) then redirect to appropriate dashboard (contractor vs admin)
  // ...
  header('Location: ' . BASE_PATH . '/'); exit;
}
