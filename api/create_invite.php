<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ids.php';
header('Content-Type: application/json');

$email = trim($_POST['email'] ?? '');
$role  = $_POST['role'] ?? 'viewer';
$contractor_id = isset($_POST['contractor_id']) ? (int)$_POST['contractor_id'] : null;
$days = max(1, (int)($_POST['valid_days'] ?? 7));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  http_response_code(400); echo json_encode(['error'=>'bad email']); exit;
}

$token = bin2hex(random_bytes(24)); // 48 chars
$exp   = (new DateTime("+{$days} days"))->format('Y-m-d H:i:s');

$stmt = $mysqli->prepare("INSERT INTO invite_tokens (token,email,role,contractor_id,expires_at,created_by) VALUES (?,?,?,?,?,NULL)");
$stmt->bind_param('sssds', $token, $email, $role, $contractor_id, $exp);
$stmt->execute();

// Email the link yourself or echo it:
$link = BASE_URL . '/invite/accept.php?token=' . $token;
echo json_encode(['ok'=>true, 'invite_link'=>$link]);
