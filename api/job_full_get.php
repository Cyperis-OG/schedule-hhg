<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// don't let PHP warnings break our JSON
ini_set('display_errors', '0');

require_once '/home/freeman/job_scheduler.php';  // <— absolute path


$jobId      = isset($_GET['job']) ? intval($_GET['job']) : null;
$fromDayUid = isset($_GET['from_day_uid'])
  ? preg_replace('/[^a-fA-F0-9]/', '', $_GET['from_day_uid'])
  : null;

try {
  if (!$jobId && $fromDayUid) {
    // resolve master job id from a job day uid
    // job_days uses `uid` for the public identifier exposed to the client,
    // so look up the master job by that column.
    $sql = "SELECT job_id FROM job_days WHERE uid = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $fromDayUid);
    $stmt->execute();
    $stmt->bind_result($jobId);
    $stmt->fetch();
    $stmt->close();
    $jobId = (int)$jobId;
  }

  if (!$jobId) {
    echo json_encode(['ok' => false, 'error' => 'Missing job id']); exit;
  }

  // master job
  $stmt = $mysqli->prepare("
    SELECT id AS JobId, customer_name, job_number, salesman, status
    FROM jobs
    WHERE id = ?
  ");
  $stmt->bind_param('i', $jobId);
  $stmt->execute();
  $res = $stmt->get_result();
  $job = $res->fetch_assoc();
  $stmt->close();
  if (!$job) { echo json_encode(['ok'=>false,'error'=>'Job not found']); exit; }

  // all days for that job (add/rename columns to match your schema)
  $stmt = $mysqli->prepare("
    SELECT
      jd.id           AS Id,
      jd.job_id       AS JobId,
      jd.work_date    AS WorkDate,
      jd.start_time   AS StartTime,
      jd.end_time     AS EndTime,
      jd.contractor_id AS ContractorId,
      jd.location     AS Location,
      jd.tractors, jd.bobtails, jd.movers, jd.drivers,
      jd.installers, jd.pctechs, jd.supervisors,
      jd.project_managers, jd.electricians,
      jd.day_notes    AS DayNotes,
      jd.status       AS Status
    FROM job_days jd
    WHERE jd.job_id = ?
    ORDER BY jd.work_date, jd.start_time
  ");
  $stmt->bind_param('i', $jobId);
  $stmt->execute();
  $res = $stmt->get_result();
  $days = [];
  while ($row = $res->fetch_assoc()) { $days[] = $row; }
  $stmt->close();

  echo json_encode(['ok'=>true, 'job'=>$job, 'days'=>$days]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
