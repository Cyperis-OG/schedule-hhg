<?php
// api/import_schedule.php
// Import jobs from uploaded CSV file.
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

if (!isset($_FILES['csv']) || $_FILES['csv']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing file']);
    exit;
}

$fh = fopen($_FILES['csv']['tmp_name'], 'r');
if (!$fh) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unable to open file']);
    exit;
}

$mysqli->begin_transaction();

try {
    $imported = 0;
    $header = fgetcsv($fh); // skip header row

    $stJob = $mysqli->prepare("INSERT INTO jobs (uid,title,job_number,salesman,status,notes,meta,created_at) VALUES (?,?,?,?,?,?,?,NOW())");
    $stDay = $mysqli->prepare("INSERT INTO job_days (uid,job_uid,work_date,start_time,end_time,contractor_id,location,tractors,bobtails,movers,drivers,installers,pctechs,supervisors,project_managers,crew_transport,electricians,day_notes,status,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
    $stCtr = $mysqli->prepare("SELECT id FROM contractors WHERE LOWER(name) = LOWER(?) LIMIT 1");
    $defaultCtr = null;
    if ($res = $mysqli->query("SELECT id FROM contractors WHERE LOWER(name) = 'will advise' LIMIT 1")) {
        if ($row = $res->fetch_assoc()) $defaultCtr = (int)$row['id'];
        $res->free();
    }

    while (($row = fgetcsv($fh)) !== false) {
        if (!$row) continue;
        $status      = trim($row[0]  ?? ''); // A: Status
        $dateStr     = trim($row[2]  ?? ''); // C: Date
        $jobnum      = trim($row[3]  ?? ''); // D: Order #
        $customer    = trim($row[4]  ?? ''); // E: Customer Name
        $weight      = trim($row[6]  ?? ''); // G: Weight
        $arrival     = trim($row[8]  ?? ''); // I: Job Time
        $locationRaw = trim($row[9]  ?? ''); // J: Location
        $equipment   = trim($row[11] ?? ''); // L: Equipment
        $crewLead    = trim($row[13] ?? ''); // N: Contractor Name
        $notes       = trim($row[17] ?? ''); // R: Notes
        $salesman    = trim($row[19] ?? ''); // T: Salesman

        if ($customer === '' || $dateStr === '') continue; // minimal required

        $work_date = date('Y-m-d', strtotime($dateStr));
        $start_time = '08:00:00';
        $end_time   = '12:00:00';
        if ($arrival !== '') {
            $parts = explode('-', $arrival);
            if (count($parts) === 2) {
                $start_time = date('H:i:00', strtotime($parts[0]));
                $end_time   = date('H:i:00', strtotime($parts[1]));
            }
        }

        $contractor_id = null;
        if ($crewLead !== '') {
            $stCtr->bind_param('s', $crewLead);
            $stCtr->execute();
            $stCtr->bind_result($cid);
            if ($stCtr->fetch()) $contractor_id = (int)$cid;
            $stCtr->free_result();
        }
        if ($contractor_id === null && $defaultCtr !== null) {
            $contractor_id = $defaultCtr;
        }

        $job_uid = bin2hex(random_bytes(13));
        $day_uid = bin2hex(random_bytes(13));

        $meta = [];
        if ($weight !== '') $meta['weight'] = $weight;
        if ($equipment !== '') $meta['equipment'] = $equipment;
        $metaJson = $meta ? json_encode($meta) : null;

        $job_number = $jobnum !== '' ? $jobnum : null;
        $salesman_val = $salesman !== '' ? $salesman : null;
        $status_slug = strtolower(str_replace([' ', '-'], '_', $status));
        $valid_statuses = ['placeholder','needs_paperwork','scheduled','dispatched','canceled','completed','paid'];
        $status_val = $status_slug !== '' && in_array($status_slug, $valid_statuses, true) ? $status_slug : 'scheduled';
        $notes_val = $notes !== '' ? $notes : null;

        $stJob->bind_param('sssssss', $job_uid, $customer, $job_number, $salesman_val, $status_val, $notes_val, $metaJson);
        if (!$stJob->execute()) throw new Exception('job insert failed: ' . $stJob->error);

        $day_notes = null; // notes stored at job level
        $location = $locationRaw !== '' ? $locationRaw : null;
        $tractors = 0; $bobtails = 0; $movers = 0; $drivers = 0;
        $installers = 0; $pctechs = 0; $supervisors = 0; $project_managers = 0;
        $crew_transport = 0; $electricians = 0;

        $stDay->bind_param(
            'sssssisiiiiiiiiiiss',
            $day_uid,
            $job_uid,
            $work_date,
            $start_time,
            $end_time,
            $contractor_id,
            $location,
            $tractors,
            $bobtails,
            $movers,
            $drivers,
            $installers,
            $pctechs,
            $supervisors,
            $project_managers,
            $crew_transport,
            $electricians,
            $day_notes,
            $status_val
        );
        if (!$stDay->execute()) throw new Exception('day insert failed: ' . $stDay->error);

        $imported++;
    }

    $stJob->close();
    $stDay->close();
    $stCtr->close();
    fclose($fh);
    $mysqli->commit();
    echo json_encode(['ok' => true, 'imported' => $imported]);
} catch (Throwable $e) {
    fclose($fh);
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
