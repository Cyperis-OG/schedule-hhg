<?php
/**
 * job_save.php — matches YOUR schema (jobs.uid, job_days.job_uid; NO job_id in job_days)
 * -------------------------------------------------------------------------------------
 * Accepts:
 *  - application/json: { job, days }
 *  - multipart/form-data: payload=<JSON {job,days}>, files[<i>][bol][], files[<i>][extra][]
 *
 * Returns:
 *  {
 *    ok: true,
 *    job_uid: "xxxxxxxxxxxxxxxxxxxxxxxxxx",      // 26-char UID
 *    days: { "0":"<dayUID>", "1":"<dayUID>", ... },
 *    files: { 0:{bol:[...],extra:[...]}, ... },
 *    events: [                                   // <-- for immediate render w/o refetch
 *      { Id, Subject, StartTime, EndTime, ContractorId }
 *    ]
 *  }
 */

header('Content-Type: application/json');
require_once '/home/freeman/job_scheduler.php';

/* ---------- Parse payload (JSON or multipart) ---------- */
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isJson = stripos($ct, 'application/json') !== false;

if ($isJson) {
  $payload   = json_decode(file_get_contents('php://input'), true);
  $filesRoot = null;                         // no files in pure JSON mode
} else {
  $payload   = json_decode($_POST['payload'] ?? '', true);
  $filesRoot = $_FILES['files'] ?? null;     // may be missing
}

if (!$payload || empty($payload['job']) || empty($payload['days']) || !is_array($payload['days'])) {
  http_response_code(400);
  echo json_encode(['ok'=>false, 'error'=>'bad payload']);
  exit;
}

$job  = $payload['job'];   // expects: title (customer), job_number, salesman, status
$days = $payload['days'];  // array of per-day objects

/* ---------- Small helpers ---------- */
function must_prepare(mysqli $db, string $sql): mysqli_stmt {
  $stmt = $db->prepare($sql);
  if (!$stmt) { throw new Exception('Prepare failed: '.$db->error.' | SQL: '.$sql); }
  return $stmt;
}

/** Make a 26-char hex UID for your CHAR(26) columns. */
function uid26(): string { return bin2hex(random_bytes(13)); }

function ensure_dir(string $dir){ if(!is_dir($dir)) @mkdir($dir, 0775, true); }
// Sanitize a user-supplied filename by stripping any directory components and
// replacing disallowed characters with underscores. Falls back to "file" if
// the resulting name is empty.
function sanitize_filename(string $name): string {
  $name = basename($name);
  $name = preg_replace('/[^A-Za-z0-9._-]/', '_', $name);
  return $name !== '' ? $name : 'file';
}

/** Rebuild PHP's nested $_FILES structure into a list for a given day bucket. */
function collect_uploaded($filesRoot, int $dayIdx, string $bucket): array {
  $out = []; if(!$filesRoot) return $out;
  $names = $filesRoot['name'][$dayIdx][$bucket] ?? null;
  $types = $filesRoot['type'][$dayIdx][$bucket] ?? null;
  $tmps  = $filesRoot['tmp_name'][$dayIdx][$bucket] ?? null;
  $errs  = $filesRoot['error'][$dayIdx][$bucket] ?? null;
  $sizes = $filesRoot['size'][$dayIdx][$bucket] ?? null;
  if ($names === null) return $out;
  if (!is_array($names)) {
    $names = [$names];
    $types = [$types];
    $tmps  = [$tmps];
    $errs  = [$errs];
    $sizes = [$sizes];
  }
  $n = count($names);
  for ($i=0; $i<$n; $i++) {
    if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
      $out[] = [
        'name'=>$names[$i],
        'type'=>$types[$i] ?? 'application/octet-stream',
        'tmp' =>$tmps[$i],
        'size'=>(int)($sizes[$i] ?? 0)
      ];
    }
  }
  return $out;
}

/** Build a Syncfusion-friendly event from one day row */
function build_event(string $dayUid, string $subject, ?int $contractorId, string $workDate, string $start, string $end): array {
  // StartTime/EndTime as ISO: Syncfusion will parse them into Date on addEvent
  return [
    'Id'           => $dayUid,
    'Subject'      => $subject,                              // you treat Customer as title
    'StartTime'    => $workDate . 'T' . $start,              // e.g. 2025-08-17T09:00:00
    'EndTime'      => $workDate . 'T' . $end,
    'ContractorId' => $contractorId                          // may be null
  ];
}

/* ---------- DB writes ---------- */
$mysqli->begin_transaction();

try {
  /* ===== Insert JOB (your columns) =====
     jobs: id, uid, customer_id_id, title, job_number, salesman, status, notes, ...
     We fill: uid, title, job_number, salesman, status, notes=NULL
  */
  $job_uid = uid26();

  $sqlJob = "INSERT INTO jobs (uid, title, job_number, salesman, status, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())";
  $st = must_prepare($mysqli, $sqlJob);

  $title  = $job['title']      ?? '';               // Customer is the title
  $jobnum = $job['job_number'] ?? null;
  $sales  = $job['salesman']   ?? null;
  $status = $job['status']     ?? 'scheduled';
  $notes  = null;                                    // job-level notes disabled (per your request)

  if (!$st->bind_param('ssssss', $job_uid, $title, $jobnum, $sales, $status, $notes)) {
    throw new Exception('bind_param failed (jobs): '.$st->error);
  }
  if (!$st->execute()) throw new Exception('Execute failed (jobs): '.$st->error);
  $st->close();

  /* ===== Insert JOB DAYS (NO job_id; uses job_uid) ===== */
  $sqlDay = "INSERT INTO job_days (
               uid, job_uid, work_date, start_time, end_time,
               contractor_id, location,
               tractors, bobtails, movers, drivers, installers, pctechs, supervisors, project_managers, crew_transport, electricians,
               day_notes, status, created_at
            ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
  $types = 'sssssis' . str_repeat('i', 10) . 'ss';

  $stDay = must_prepare($mysqli, $sqlDay);

  $day_uid_by_index = [];
  $newEvents        = [];

  foreach ($days as $idx => $d) {
    $day_uid = uid26();
    $day_uid_by_index[$idx] = $day_uid;

    $work_date     = $d['work_date'];     // 'YYYY-MM-DD'
    $start_time    = $d['start_time'];    // 'HH:MM:SS'
    $end_time      = $d['end_time'];      // 'HH:MM:SS'
    $contractor_id = isset($d['contractor_id']) && $d['contractor_id'] !== '' ? (int)$d['contractor_id'] : null;
    $location      = $d['location'] ?? null;

    $tractors      = (int)($d['tractors'] ?? 0);
    $bobtails      = (int)($d['bobtails'] ?? 0);
    $movers        = (int)($d['movers'] ?? 0);
    $drivers       = (int)($d['drivers'] ?? 0);
    $installers    = (int)($d['installers'] ?? 0);
    $pctechs       = (int)($d['pctechs'] ?? 0);
    $supervisors   = (int)($d['supervisors'] ?? 0);
    $pms           = (int)($d['project_managers'] ?? 0);
    $crew          = (int)($d['crew_transport'] ?? 0);
    $elec          = (int)($d['electricians'] ?? 0);

    $day_notes     = $d['day_notes'] ?? null;
    $dstatus       = $d['status'] ?? $status;

    $params = [$day_uid, $job_uid, $work_date, $start_time, $end_time,
      $contractor_id, $location,
      $tractors, $bobtails, $movers, $drivers, $installers, $pctechs, $supervisors, $pms, $crew, $elec,
      $day_notes, $dstatus];

    if (!$stDay->bind_param($types, ...$params)) {
      throw new Exception('bind_param failed (job_days): '.$stDay->error);
    }

    if (!$stDay->execute()) { throw new Exception('Execute failed (job_days): '.$stDay->error); }

    // Prepare an event for instant UI rendering
    $newEvents[] = build_event($day_uid, $title, $contractor_id, $work_date, $start_time, $end_time);
  }
  $stDay->close();

  /* ===== Files (optional) ===== */
  $saved = [];
  if ($filesRoot) {
    foreach ($day_uid_by_index as $i => $uid) {
      $saved[$i] = ['bol'=>[], 'extra'=>[]];

      // BOL / CSO -> accept only PDFs. Replace any existing BOL if new uploaded.
      $bolFiles = collect_uploaded($filesRoot, $i, 'bol');
      if ($bolFiles) {
        $dir = __DIR__ . '/../uploads/' . $uid . '/bol/';
        if (is_dir($dir)) {
          foreach (scandir($dir) as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            @unlink($dir . $fn);
          }
        }
        ensure_dir($dir);
        foreach ($bolFiles as $f) {
          $ext = strtolower(trim(pathinfo($f['name'], PATHINFO_EXTENSION)));
          if ($ext !== 'pdf') continue;
          $name = sanitize_filename($f['name']);
          if (move_uploaded_file($f['tmp'], $dir.$name)) {
            $saved[$i]['bol'][] = '/095/schedule-ng/uploads/' . $uid . '/bol/' . $name;
          }
        }
      }

      // Additional files (any)
      foreach (collect_uploaded($filesRoot, $i, 'extra') as $f) {
        $dir = __DIR__ . '/../uploads/' . $uid . '/extra/';
        ensure_dir($dir);
        $name = sanitize_filename($f['name']);
        if (move_uploaded_file($f['tmp'], $dir.$name)) {
          $saved[$i]['extra'][] = '/095/schedule-ng/uploads/' . $uid . '/extra/' . $name;
        }
      }
    }
  }

  $mysqli->commit();

  echo json_encode([
    'ok'       => true,
    'job_uid'  => $job_uid,
    'days'     => $day_uid_by_index,
    'files'    => $saved,
    'events'   => $newEvents          // <-- front-end can add these directly
  ]);
  exit;

} catch (Throwable $e) {
  $mysqli->rollback();
  http_response_code(500);
  echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
  exit;
}
