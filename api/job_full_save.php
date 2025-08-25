<?php
// /095/schedule-ng/api/job_full_save.php
// Update an existing job and its days by job_uid/day_uid, handling optional file uploads

header('Content-Type: application/json');

require_once '/home/freeman/job_scheduler.php';  // absolute path used elsewhere

// ----------- Payload parsing (JSON or multipart with payload field) -----------
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
$isJson = stripos($ct, 'application/json') !== false;

if ($isJson) {
    $payload   = json_decode(file_get_contents('php://input'), true);
    $filesRoot = null; // no files in pure JSON mode
} else {
    $payload   = json_decode($_POST['payload'] ?? '', true);
    $filesRoot = $_FILES['files'] ?? null; // may be missing
}

if (!$payload || empty($payload['job_uid']) || empty($payload['job']) || empty($payload['days']) || !is_array($payload['days'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'bad payload']);
    exit;
}

$job_uid     = preg_replace('/[^a-fA-F0-9]/', '', $payload['job_uid']);
$job         = $payload['job'];
$days        = $payload['days'];
$delete_uids = $payload['delete_uids'] ?? [];

// ----------- helpers (borrowed from job_save.php) -----------
function must_prepare(mysqli $db, string $sql): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    return $stmt;
}

function uid26(): string { return bin2hex(random_bytes(13)); }

function ensure_dir(string $dir) {
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
}

function safe_filename(string $n) {
    $n = preg_replace('/[^\w.\-]+/u', '_', $n);
    return ltrim($n, '.') ?: ('file_' . bin2hex(random_bytes(4)));
}

/** Rebuild PHP's nested $_FILES structure into a list for a given day bucket. */
function collect_uploaded($filesRoot, int $dayIdx, string $bucket): array {
    $out = [];
    if (!$filesRoot) return $out;
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
    for ($i = 0; $i < $n; $i++) {
        if (($errs[$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $out[] = [
                'name' => $names[$i],
                'type' => $types[$i] ?? 'application/octet-stream',
                'tmp'  => $tmps[$i],
                'size' => (int)($sizes[$i] ?? 0)
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
        'Subject'      => $subject,
        'StartTime'    => $workDate . 'T' . $start,
        'EndTime'      => $workDate . 'T' . $end,
        'ContractorId' => $contractorId
    ];
}

// ----------- DB writes -----------
$mysqli->begin_transaction();

try {
    // Update master job
    $sqlJob = "UPDATE jobs SET title = ?, job_number = ?, salesman = ?, status = ? WHERE uid = ?";
    $stJob = must_prepare($mysqli, $sqlJob);
    $title  = $job['title']      ?? $job['customer_name'] ?? '';
    $jobnum = $job['job_number'] ?? null;
    $sales  = $job['salesman']   ?? null;
    $status = $job['status']     ?? 'scheduled';
    if (!$stJob->bind_param('sssss', $title, $jobnum, $sales, $status, $job_uid)) {
        throw new Exception('bind_param failed (jobs): ' . $stJob->error);
    }
    if (!$stJob->execute()) {
        throw new Exception('Execute failed (jobs): ' . $stJob->error);
    }
    $stJob->close();

    // Prepared statements for day insert/update
    $sqlIns = "INSERT INTO job_days (
                uid, job_uid, work_date, start_time, end_time,
                contractor_id, location,
                tractors, bobtails, movers, drivers, installers, pctechs, supervisors, project_managers, electricians,
                day_notes, status, created_at
              ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";
    $stIns = must_prepare($mysqli, $sqlIns);
    $typesIns = 'sssssis' . str_repeat('i', 9) . 'ss';

    $sqlUpd = "UPDATE job_days SET
                work_date=?, start_time=?, end_time=?, contractor_id=?, location=?,
                tractors=?, bobtails=?, movers=?, drivers=?, installers=?, pctechs=?, supervisors=?, project_managers=?, electricians=?,
                day_notes=?, status=?
              WHERE uid=?";
    $stUpd = must_prepare($mysqli, $sqlUpd);
    $typesUpd = 'sssis' . str_repeat('i', 9) . 'sss';

    $day_uid_by_index = [];
    $newEvents        = [];

    foreach ($days as $idx => $d) {
        $day_uid = preg_replace('/[^a-fA-F0-9]/', '', $d['day_uid'] ?? '');

        $work_date     = $d['work_date'];
        $start_time    = $d['start_time'];
        $end_time      = $d['end_time'];
        $contractor_id = isset($d['contractor_id']) && $d['contractor_id'] !== '' ? (int)$d['contractor_id'] : null;
        $location      = $d['location'] ?? null;

        $tractors    = (int)($d['tractors']    ?? 0);
        $bobtails    = (int)($d['bobtails']    ?? 0);
        $movers      = (int)($d['movers']      ?? 0);
        $drivers     = (int)($d['drivers']     ?? 0);
        $installers  = (int)($d['installers']  ?? 0);
        $pctechs     = (int)($d['pctechs']     ?? 0);
        $supervisors = (int)($d['supervisors'] ?? 0);
        $pms         = (int)($d['project_managers'] ?? 0);
        $elec        = (int)($d['electricians'] ?? 0);

        $day_notes = $d['day_notes'] ?? null;
        $dstatus   = $d['status'] ?? $status;

        if ($day_uid) {
            if (!$stUpd->bind_param($typesUpd,
                $work_date, $start_time, $end_time, $contractor_id, $location,
                $tractors, $bobtails, $movers, $drivers, $installers, $pctechs, $supervisors, $pms, $elec,
                $day_notes, $dstatus, $day_uid
            )) {
                throw new Exception('bind_param failed (job_days update): ' . $stUpd->error);
            }
            if (!$stUpd->execute()) {
                throw new Exception('Execute failed (job_days update): ' . $stUpd->error);
            }
        } else {
            $day_uid = uid26();
            if (!$stIns->bind_param($typesIns,
                $day_uid, $job_uid, $work_date, $start_time, $end_time,
                $contractor_id, $location,
                $tractors, $bobtails, $movers, $drivers, $installers, $pctechs, $supervisors, $pms, $elec,
                $day_notes, $dstatus
            )) {
                throw new Exception('bind_param failed (job_days insert): ' . $stIns->error);
            }
            if (!$stIns->execute()) {
                throw new Exception('Execute failed (job_days insert): ' . $stIns->error);
            }
        }

        $day_uid_by_index[$idx] = $day_uid;
        $newEvents[] = build_event($day_uid, $title, $contractor_id, $work_date, $start_time, $end_time);
    }

    $stIns->close();
    $stUpd->close();

    // Delete removed days
    $delete_uids = array_filter(array_map(fn($u) => preg_replace('/[^a-fA-F0-9]/', '', $u), $delete_uids));
    if ($delete_uids) {
        $in = implode(',', array_fill(0, count($delete_uids), '?'));
        $sqlDel = "DELETE FROM job_days WHERE uid IN ($in)";
        $stDel = must_prepare($mysqli, $sqlDel);
        $typesDel = str_repeat('s', count($delete_uids));
        $stDel->bind_param($typesDel, ...$delete_uids);
        if (!$stDel->execute()) {
            throw new Exception('Execute failed (job_days delete): ' . $stDel->error);
        }
        $stDel->close();
    }

    // Files (BOL / extra) -- optional
    $saved = [];
    if ($filesRoot) {
        foreach ($day_uid_by_index as $i => $uid) {
            $saved[$i] = ['bol' => [], 'extra' => []];

            // BOL / CSO -> accept only PDFs. If new BOL uploaded, replace existing.
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
                    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
                    if ($ext !== 'pdf') continue;
                    $name = safe_filename($f['name']);
                    if (move_uploaded_file($f['tmp'], $dir . $name)) {
                        $saved[$i]['bol'][] = 'uploads/' . $uid . '/bol/' . $name;
                    }
                }
            }

            // Additional files (any type, append)
            foreach (collect_uploaded($filesRoot, $i, 'extra') as $f) {
                $dir = __DIR__ . '/../uploads/' . $uid . '/extra/';
                ensure_dir($dir);
                $name = safe_filename($f['name']);
                if (move_uploaded_file($f['tmp'], $dir . $name)) {
                    $saved[$i]['extra'][] = 'uploads/' . $uid . '/extra/' . $name;
                }
            }
        }
    }

    $mysqli->commit();
    echo json_encode(['ok' => true, 'job_uid' => $job_uid, 'days' => $day_uid_by_index, 'files' => $saved, 'events' => $newEvents]);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
