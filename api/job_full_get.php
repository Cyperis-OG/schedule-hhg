<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// don't let PHP warnings break our JSON
ini_set('display_errors', '0');

require_once '/home/freeman/job_scheduler.php';  // <— absolute path
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'error' => 'forbidden']);
  exit;
}

// use job UID for lookups; resolve via day UID if needed
$jobUid     = isset($_GET['job']) ? preg_replace('/[^a-fA-F0-9]/', '', $_GET['job']) : null;
$fromDayUid = isset($_GET['from_day_uid']) ? preg_replace('/[^a-fA-F0-9]/', '', $_GET['from_day_uid']) : null;

function must_prepare(mysqli $db, string $sql): mysqli_stmt {
  $stmt = $db->prepare($sql);
  if (!$stmt) { throw new Exception('Prepare failed: '.$db->error); }
  return $stmt;
}

try {
  if (!$jobUid && $fromDayUid) {
    // resolve master job uid from a day uid
    $stmt = must_prepare($mysqli, 'SELECT job_uid FROM job_days WHERE uid = ?');
    $stmt->bind_param('s', $fromDayUid);
    $stmt->execute();
    $stmt->bind_result($jobUid);
    $stmt->fetch();
    $stmt->close();
    $jobUid = $jobUid ?: null;
  }

  if (!$jobUid) {
    echo json_encode(['ok' => false, 'error' => 'Missing job id']);
    exit;
  }

  // master job (return both numeric id and uid if present)
  $stmt = must_prepare(
    $mysqli,
    'SELECT id AS JobId, uid AS JobUID, title AS customer_name, job_number, salesman, status FROM jobs WHERE uid = ?'
  );
  $stmt->bind_param('s', $jobUid);
  $stmt->execute();
  $res = $stmt->get_result();
  $job = $res->fetch_assoc();
  $stmt->close();
  if (!$job) { echo json_encode(['ok'=>false,'error'=>'Job not found']); exit; }

  // all days for that job — join jobs to expose label columns expected by templates
  $stmt = must_prepare(
    $mysqli,
    "SELECT
      jd.uid           AS Id,
      jd.job_uid       AS JobUID,
      jd.work_date     AS work_date,
      CONCAT(jd.work_date, 'T', DATE_FORMAT(jd.start_time, '%H:%i')) AS StartTime,
      CONCAT(jd.work_date, 'T', DATE_FORMAT(jd.end_time, '%H:%i'))   AS EndTime,
      DATE_FORMAT(jd.start_time, '%H:%i') AS start_time,
      DATE_FORMAT(jd.end_time,   '%H:%i') AS end_time,
      jd.contractor_id AS contractor_id,
      jd.location      AS Location,
      jd.tractors, jd.bobtails, jd.movers, jd.drivers,
      jd.installers, jd.pctechs, jd.supervisors,
      jd.project_managers, jd.crew_transport, jd.electricians,
      jd.day_notes     AS day_notes,
      jd.status        AS status,
      j.title          AS customer,
      j.job_number     AS job,
      jd.location      AS task1
    FROM job_days jd
    JOIN jobs j ON j.uid = jd.job_uid
    WHERE jd.job_uid = ?
    ORDER BY jd.work_date, jd.start_time"
  );
  $stmt->bind_param('s', $jobUid);
  $stmt->execute();
  $res = $stmt->get_result();
  $days = [];
  while ($row = $res->fetch_assoc()) { $days[] = $row; }
  $stmt->close();

  // collect any previously uploaded files per day
  foreach ($days as &$d) {
    $uid = $d['Id'] ?? $d['uid'] ?? null;
    $d['files'] = ['bol' => [], 'extra' => []];
    if ($uid) {
      foreach (['bol', 'extra'] as $bucket) {
        $dir = __DIR__ . '/../uploads/' . $uid . '/' . $bucket . '/';
        if (is_dir($dir)) {
          foreach (scandir($dir) as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $d['files'][$bucket][] = '/095/schedule-ng/uploads/' . $uid . '/' . $bucket . '/' . $fn;
          }
        }
      }
    }
  }
  unset($d);

  echo json_encode(['ok'=>true, 'job'=>$job, 'days'=>$days]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}