<?php
// api/job_update_timeslot.php
include '/home/freeman/job_scheduler.php';
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { echo json_encode(['error'=>'forbidden']); exit; }

$payload = json_decode(file_get_contents('php://input'), true);
$uid  = $payload['job_day_uid'] ?? '';
$start = $payload['start'] ?? ''; // 'YYYY-MM-DD HH:MM:SS'
$end   = $payload['end']   ?? '';
$contractor_id = isset($payload['contractor_id']) ? (int)$payload['contractor_id'] : null;

if ($uid === '' || !preg_match('/^\d{4}-\d{2}-\d{2} \d\d:\d\d:\d\d$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2} \d\d:\d\d:\d\d$/', $end)) {
    http_response_code(400); echo json_encode(['error'=>'bad input']); exit;
}

[$dateS, $timeS] = explode(' ', $start, 2);
[$dateE, $timeE] = explode(' ', $end, 2);

$stmt = $mysqli->prepare("UPDATE job_days SET work_date=?, start_time=?, end_time=?, contractor_id=COALESCE(?, contractor_id) WHERE uid=? LIMIT 1");
$stmt->bind_param('sssis', $dateS, $timeS, $timeE, $contractor_id, $uid);
$stmt->execute();

echo json_encode(['ok' => true]);
