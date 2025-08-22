<?php
// /schedule-ng/api/job_full_save.php
header('Content-Type: application/json');
require_once __DIR__ . '/../../job_scheduler.php';

try {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  if (!$data) throw new Exception('Invalid JSON payload');

  $job  = $data['job']  ?? null;
  $days = $data['days'] ?? null;
  if (!$job || !is_array($days)) throw new Exception('Missing job or days');

  $pdo->beginTransaction();

  // update job master (adjust fields as needed)
  $stmt = $pdo->prepare("
    UPDATE jobs
       SET customer_name = ?, job_number = ?, salesman = ?, status = ?
     WHERE id = ?
  ");
  $stmt->execute([
    $job['customer_name'] ?? null,
    $job['job_number']    ?? null,
    $job['salesman']      ?? null,
    $job['status']        ?? 'scheduled',
    (int)$job['JobId']
  ]);

  // upsert days (very simple: update by Id)
  $stmtDay = $pdo->prepare("
    UPDATE job_days
       SET work_date=?, start_time=?, end_time=?, contractor_id=?, location=?,
           tractors=?, bobtails=?, movers=?, drivers=?,
           installers=?, pctechs=?, supervisors=?, project_managers=?, electricians=?,
           day_notes=?, status=?
     WHERE id=?
  ");

  foreach ($days as $d) {
    $stmtDay->execute([
      $d['work_date'], $d['start_time'], $d['end_time'], $d['contractor_id'], $d['location'],
      (int)$d['tractors'], (int)$d['bobtails'], (int)$d['movers'], (int)$d['drivers'],
      (int)$d['installers'], (int)$d['pctechs'], (int)$d['supervisors'],
      (int)$d['project_managers'], (int)$d['electricians'],
      $d['day_notes'], $d['status'],
      (int)$d['Id']   // needs Id in payload for each day row
    ]);
  }

  $pdo->commit();
  echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
