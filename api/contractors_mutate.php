<?php
/**
 * POST /095/schedule-ng/api/contractors_mutate.php
 * --------------------------------------------
 * Mutations for contractors:
 *  - add        {action:'add', name, color_hex?}
 *  - update     {action:'update', id, name, color_hex?}
 *  - toggle     {action:'toggle', id}            // flips active 1<->0
 *  - reorder    {action:'reorder', ids:[id1,id2,...]} // new top->bottom order
 *
 * Always returns {ok:true} or {error:'msg'}
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../lib/ids.php';
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['error'=>'forbidden']); exit; }

$raw = file_get_contents('php://input');
$payload = json_decode($raw, true);
if (!is_array($payload)) $payload = $_POST; // allow form-encoded fallback

$action = $payload['action'] ?? '';

function respond($arr) { echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }

if ($action === 'add') {
    $name  = trim($payload['name'] ?? '');
    $driver = trim($payload['driver_id'] ?? '');
    $color = trim($payload['color_hex'] ?? '');
    $email = trim($payload['email_notify'] ?? '');
    if ($name === '') respond(['error' => 'Name required']);

    // Find the next display_order (max + 10) to keep spacing
    $max = 1000;
    if ($r = $GLOBALS['mysqli']->query("SELECT COALESCE(MAX(display_order), 990) AS m FROM contractors")->fetch_assoc()) {
        $max = (int)$r['m'] + 10;
    }
    $uid = ulid();
    $stmt = $mysqli->prepare("INSERT INTO contractors (uid,name,driver_id,active,display_order,color_hex,email_notify) VALUES (?,?,?,?,?,?,?)");
    $active = 1;
    $stmt->bind_param('sssiiss', $uid, $name, $driver, $active, $max, $color, $email);
    $stmt->execute();

    respond(['ok' => true, 'id' => (int)$mysqli->insert_id, 'uid' => $uid, 'display_order' => $max]);
}

if ($action === 'update') {
    $id     = (int)($payload['id'] ?? 0);
    $name   = trim($payload['name'] ?? '');
    $driver = trim($payload['driver_id'] ?? '');
    $color  = trim($payload['color_hex'] ?? '');
    $email  = trim($payload['email_notify'] ?? '');
    if ($id <= 0 || $name === '') respond(['error' => 'Bad input']);
    $stmt = $mysqli->prepare("UPDATE contractors SET name=?, driver_id=?, color_hex=?, email_notify=? WHERE id=?");
    $stmt->bind_param('ssssi', $name, $driver, $color, $email, $id);
    $stmt->execute();
    respond(['ok' => true]);
}

if ($action === 'toggle') {
    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) respond(['error' => 'Bad id']);
    $mysqli->query("UPDATE contractors SET active = 1 - active WHERE id={$id} LIMIT 1");
    respond(['ok' => true]);
}

if ($action === 'reorder') {
    $ids = $payload['ids'] ?? [];
    if (!is_array($ids) || empty($ids)) respond(['error' => 'No ids']);
    // Assign 10,20,30... so we preserve gaps for future inserts
    $order = 10;
    $stmt = $mysqli->prepare("UPDATE contractors SET display_order=? WHERE id=?");
    foreach ($ids as $rawId) {
        $id = (int)$rawId;
        $stmt->bind_param('ii', $order, $id);
        $stmt->execute();
        $order += 10;
    }
    respond(['ok' => true]);
}

respond(['error' => 'Unknown action']);
