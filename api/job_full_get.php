<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

// don't let PHP warnings break our JSON
ini_set('display_errors', '0');

require_once '/home/freeman/job_scheduler.php';  // <— absolute path


$jobId        = isset($_GET['job']) ? intval($_GET['job']) : null;
$fromDayUid   = isset($_GET['from_day_uid']) ? intval($_GET['from_day_uid']) : null;

try {
  if (!$jobId && $fromDayUid) {
    // resolve master job id from a job day uid
    $sql = "SELECT job_id FROM job_days WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$fromDayUid]);
    $jobId = (int)$stmt->fetchColumn();
  }

  if (!$jobId) {
    echo json_encode(['ok' => false, 'error' => 'Missing job id']); exit;
  }

  // master job
  $stmt = $pdo->prepare("
    SELECT id AS JobId, customer_name, job_number, salesman, status
    FROM jobs
    WHERE id = ?
  ");
  $stmt->execute([$jobId]);
  $job = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$job) { echo json_encode(['ok'=>false,'error'=>'Job not found']); exit; }

  // all days for that job (add/rename columns to match your schema)
  $stmt = $pdo->prepare("
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
  $stmt->execute([$jobId]);
  $days = $stmt->fetchAll(PDO::FETCH_ASSOC);

  echo json_encode(['ok'=>true, 'job'=>$job, 'days'=>$days]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
