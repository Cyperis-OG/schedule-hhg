<?php
// api/import_schedule.php
// Import jobs from an uploaded XLSX file using the column mapping provided by HHG.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

$file = null;
foreach (['xlsx', 'csv', 'file'] as $field) {
    if (isset($_FILES[$field])) {
        $file = $_FILES[$field];
        break;
    }
}

if (!$file) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing file upload']);
    exit;
}

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => upload_error_message($file['error'] ?? UPLOAD_ERR_NO_FILE)]);
    exit;
}

$originalName = (string)($file['name'] ?? '');
if ($originalName !== '' && !preg_match('/\.xlsx$/i', $originalName)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'please upload an .xlsx spreadsheet']);
    exit;
}

try {
    $rows = load_xlsx_rows($file['tmp_name']);
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid spreadsheet: ' . $e->getMessage()]);
    exit;
}

if (!$rows || count($rows) < 2) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'spreadsheet has no data']);
    exit;
}

// Remove header row
array_shift($rows);

$mysqli->begin_transaction();

try {
    $imported = 0;
    $duplicates = 0;

    $sqlJob = "INSERT INTO jobs (uid, title, job_number, salesman, service_type, status, notes, created_at)
               VALUES (?,?,?,?,?,?,?,NOW())";
    $sqlDay = "INSERT INTO job_days (
                  uid, job_uid, work_date, start_time, end_time,
                  contractor_id, location,
                  tractors, bobtails, movers, drivers, installers, pctechs, supervisors, project_managers, crew_transport, electricians,
                  equipment, weight,
                  day_notes, status, created_at
               ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())";

    $stJob = must_prepare($mysqli, $sqlJob);
    $stDay = must_prepare($mysqli, $sqlDay);

    $stFindDup = must_prepare($mysqli, "SELECT jd.job_uid AS job_uid"
        . " FROM job_days jd"
        . " INNER JOIN jobs j ON j.uid = jd.job_uid"
        . " WHERE j.job_number = ? AND jd.work_date = ?"
        . " LIMIT 1");
    $stMarkDayDup = must_prepare($mysqli, "UPDATE job_days jd"
        . " INNER JOIN jobs j ON j.uid = jd.job_uid"
        . " SET jd.status = 'duplicate'"
        . " WHERE j.job_number = ? AND jd.work_date = ?");
    $stMarkJobDup = must_prepare($mysqli, "UPDATE jobs SET status = 'duplicate' WHERE job_number = ? AND status <> 'duplicate'");

    $defaultCtr = lookup_default_contractor($mysqli);

    foreach ($rows as $row) {
        $statusRaw      = trim((string)($row['A'] ?? ''));
        $dateRaw        = trim((string)($row['C'] ?? ''));
        $jobNumberRaw   = trim((string)($row['D'] ?? ''));
        $customer       = trim((string)($row['E'] ?? ''));
        $serviceTypeRaw = trim((string)($row['F'] ?? ''));
        $weightRaw      = trim((string)($row['G'] ?? ''));
        $timeRaw        = trim((string)($row['I'] ?? ''));
        $locationRaw    = trim((string)($row['J'] ?? ''));
        $equipmentRaw   = trim((string)($row['K'] ?? ''));
        $contractorRaw  = trim((string)($row['L'] ?? ''));
        $dayNotesRaw    = trim((string)($row['M'] ?? ''));
        $requesterRaw   = trim((string)($row['N'] ?? ''));

        if ($customer === '' && $dateRaw === '' && $jobNumberRaw === '' && $contractorRaw === '') {
            // Completely empty row -> skip
            continue;
        }

        $workDate = parse_excel_date($dateRaw);
        if ($workDate === null) {
            // No usable date, skip row
            continue;
        }

        if ($customer === '') {
            continue; // Require a customer name to build the job title
        }

        [$startTime, $endTime] = parse_time_range($timeRaw);

        $contractorId = resolve_contractor($mysqli, normalize_contractor_name($contractorRaw));
        if ($contractorId === null && $defaultCtr !== null) {
            $contractorId = $defaultCtr;
        }

        $jobNumber = $jobNumberRaw !== '' ? $jobNumberRaw : null;
        $requester = $requesterRaw !== '' ? $requesterRaw : null;
        $serviceType = $serviceTypeRaw !== '' ? $serviceTypeRaw : null;

        $status = normalize_status($statusRaw);

        if ($jobNumber !== null) {
            if (!$stFindDup->bind_param('ss', $jobNumber, $workDate)) {
                throw new Exception('duplicate lookup bind failed: ' . $stFindDup->error);
            }
            if (!$stFindDup->execute()) {
                throw new Exception('duplicate lookup failed: ' . $stFindDup->error);
            }
            $dupRes = $stFindDup->get_result();
            $existing = $dupRes ? $dupRes->fetch_assoc() : null;
            if ($dupRes) {
                $dupRes->free();
            }
            $stFindDup->free_result();

            if ($existing) {
                if (!$stMarkDayDup->bind_param('ss', $jobNumber, $workDate)) {
                    throw new Exception('duplicate day bind failed: ' . $stMarkDayDup->error);
                }
                if (!$stMarkDayDup->execute()) {
                    throw new Exception('duplicate day update failed: ' . $stMarkDayDup->error);
                }
                if (!$stMarkJobDup->bind_param('s', $jobNumber)) {
                    throw new Exception('duplicate job bind failed: ' . $stMarkJobDup->error);
                }
                if (!$stMarkJobDup->execute()) {
                    throw new Exception('duplicate job update failed: ' . $stMarkJobDup->error);
                }
                $duplicates++;
                $stMarkDayDup->reset();
                $stMarkJobDup->reset();
                $stFindDup->reset();
                continue;
            }

            $stFindDup->reset();
        }

        $jobUid = uid26();
        $dayUid = uid26();

        $notes = null;
        if (!$stJob->bind_param('sssssss', $jobUid, $customer, $jobNumber, $requester, $serviceType, $status, $notes)) {
            throw new Exception('bind_param failed for job: ' . $stJob->error);
        }
        if (!$stJob->execute()) {
            throw new Exception('job insert failed: ' . $stJob->error);
        }

        $weight = parse_weight($weightRaw);
        $equipment = $equipmentRaw !== '' ? $equipmentRaw : null;
        $dayNotes = $dayNotesRaw !== '' ? $dayNotesRaw : null;
        $location = null;
        if ($locationRaw !== '') {
            $location = extract_city_state($locationRaw);
            if ($location === null) {
                $location = substr($locationRaw, 0, 191);
            }
        }

        $tractors = 0;
        $bobtails = 0;
        $movers = 0;
        $drivers = 0;
        $installers = 0;
        $pctechs = 0;
        $supervisors = 0;
        $project_managers = 0;
        $crew_transport = 0;
        $electricians = 0;

        $dayUidVar = $dayUid;
        $jobUidVar = $jobUid;
        $workDateVar = $workDate;
        $startVar = $startTime;
        $endVar = $endTime;
        $contractorVar = $contractorId;
        $locationVar = $location;
        $equipmentVar = $equipment;
        $weightVar = $weight;
        $dayNotesVar = $dayNotes;
        $statusVar = $status;

        $types = 'sssssis' . str_repeat('i', 10) . 'sdss';
        if (!$stDay->bind_param(
            $types,
            $dayUidVar,
            $jobUidVar,
            $workDateVar,
            $startVar,
            $endVar,
            $contractorVar,
            $locationVar,
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
            $equipmentVar,
            $weightVar,
            $dayNotesVar,
            $statusVar
        )) {
            throw new Exception('bind_param failed for day: ' . $stDay->error);
        }
        if (!$stDay->execute()) {
            throw new Exception('day insert failed: ' . $stDay->error);
        }

        $imported++;
    }

    $stJob->close();
    $stDay->close();
    $stFindDup->close();
    $stMarkDayDup->close();
    $stMarkJobDup->close();
    $mysqli->commit();
    echo json_encode(['ok' => true, 'imported' => $imported, 'duplicates' => $duplicates]);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function must_prepare(mysqli $db, string $sql): mysqli_stmt {
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new Exception('prepare failed: ' . $db->error . ' | SQL: ' . $sql);
    }
    return $stmt;
}

function uid26(): string {
    return bin2hex(random_bytes(13));
}

function upload_error_message(int $code): string {
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'uploaded file is too large';
        case UPLOAD_ERR_PARTIAL:
            return 'upload did not complete';
        case UPLOAD_ERR_NO_FILE:
            return 'no file was uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'temporary upload directory is missing';
        case UPLOAD_ERR_CANT_WRITE:
            return 'failed to write uploaded file to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'upload blocked by PHP extension';
        default:
            return 'file upload failed';
    }
}

function load_xlsx_rows(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new Exception('unable to open XLSX archive');
    }

    $sheetPath = detect_first_sheet_path($zip);
    $sharedStrings = load_shared_strings($zip);

    $sheetData = $zip->getFromName($sheetPath);
    if ($sheetData === false) {
        throw new Exception('unable to read first worksheet');
    }

    $sheetXml = @simplexml_load_string($sheetData);
    if ($sheetXml === false) {
        throw new Exception('invalid worksheet XML');
    }

    $rows = [];
    foreach ($sheetXml->sheetData->row as $row) {
        $cells = [];
        foreach ($row->c as $cell) {
            $ref = strtoupper(preg_replace('/\d+/', '', (string)$cell['r']));
            $type = (string)$cell['t'];

            $value = '';
            if ($type === 's') {
                $idx = (int)$cell->v;
                $value = $sharedStrings[$idx] ?? '';
            } elseif ($type === 'inlineStr') {
                $value = read_inline_str($cell);
            } else {
                $value = isset($cell->v) ? (string)$cell->v : '';
            }

            $cells[$ref] = $value;
        }
        $rows[] = $cells;
    }

    $zip->close();

    return $rows;
}

function detect_first_sheet_path(ZipArchive $zip): string {
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    if ($workbookXml === false) {
        return 'xl/worksheets/sheet1.xml';
    }
    $workbook = @simplexml_load_string($workbookXml);
    if ($workbook === false) {
        return 'xl/worksheets/sheet1.xml';
    }
    $sheets = $workbook->sheets->sheet ?? [];
    if (!count($sheets)) {
        return 'xl/worksheets/sheet1.xml';
    }
    $first = $sheets[0];
    $rid = (string)$first['r:id'];
    if ($rid === '') {
        return 'xl/worksheets/sheet1.xml';
    }
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($relsXml === false) {
        return 'xl/worksheets/sheet1.xml';
    }
    $rels = @simplexml_load_string($relsXml);
    if ($rels === false) {
        return 'xl/worksheets/sheet1.xml';
    }
    foreach ($rels->Relationship as $rel) {
        if ((string)$rel['Id'] === $rid) {
            $target = (string)$rel['Target'];
            if ($target !== '') {
                if (strpos($target, 'worksheets/') === 0) {
                    return 'xl/' . $target;
                }
                return 'xl/' . ltrim($target, '/');
            }
        }
    }
    return 'xl/worksheets/sheet1.xml';
}

function load_shared_strings(ZipArchive $zip): array {
    $shared = [];
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return $shared;
    }
    $doc = @simplexml_load_string($xml);
    if ($doc === false) {
        return $shared;
    }
    foreach ($doc->si as $si) {
        $text = '';
        if (isset($si->t)) {
            $text .= (string)$si->t;
        }
        if (isset($si->r)) {
            foreach ($si->r as $r) {
                $text .= (string)$r->t;
            }
        }
        $shared[] = $text;
    }
    return $shared;
}

function read_inline_str(\SimpleXMLElement $cell): string {
    $text = '';
    if (isset($cell->is->t)) {
        $text .= (string)$cell->is->t;
    }
    if (isset($cell->is->r)) {
        foreach ($cell->is->r as $run) {
            $text .= (string)$run->t;
        }
    }
    return $text;
}

function parse_excel_date($value): ?string {
    if ($value === '' || $value === null) {
        return null;
    }
    if (is_numeric($value)) {
        $base = DateTime::createFromFormat('Y-m-d H:i:s', '1899-12-30 00:00:00', new DateTimeZone('UTC'));
        if ($base) {
            $base->modify('+' . (int)$value . ' days');
            return $base->format('Y-m-d');
        }
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return null;
    }
    return date('Y-m-d', $ts);
}

function parse_time_range($value): array {
    if ($value === '' || $value === null) {
        return ['08:00:00', '12:00:00'];
    }

    if (is_numeric($value)) {
        $start = excel_time_to_hms((float)$value);
        $end = add_hours($start, 4);
        return [$start, $end];
    }

    $normalized = str_replace(['–', '—', ' to '], '-', (string)$value);
    $parts = array_map('trim', explode('-', $normalized));
    if (count($parts) >= 2) {
        $start = parse_single_time($parts[0]) ?? '08:00:00';
        $end = parse_single_time($parts[1]);
        if ($end === null) {
            $end = add_hours($start, 4);
        }
        return [$start, ensure_after($start, $end)];
    }

    $start = parse_single_time($normalized) ?? '08:00:00';
    $end = add_hours($start, 4);
    return [$start, $end];
}

function parse_single_time($value): ?string {
    if ($value === '' || $value === null) {
        return null;
    }
    if (is_numeric($value)) {
        return excel_time_to_hms((float)$value);
    }
    $ts = strtotime((string)$value);
    if ($ts === false) {
        return null;
    }
    return date('H:i:00', $ts);
}

function excel_time_to_hms(float $value): string {
    $seconds = (int)round($value * 24 * 60 * 60);
    $hours = floor($seconds / 3600) % 24;
    $minutes = floor(($seconds % 3600) / 60);
    return sprintf('%02d:%02d:00', $hours, $minutes);
}

function add_hours(string $time, int $hours): string {
    $dt = DateTime::createFromFormat('H:i:s', $time, new DateTimeZone('UTC'));
    if (!$dt) {
        return $time;
    }
    $dt->modify('+' . $hours . ' hours');
    return $dt->format('H:i:00');
}

function ensure_after(string $start, string $end): string {
    if ($end > $start) {
        return $end;
    }
    return add_hours($start, 4);
}

function normalize_status(string $label): string {
    $raw = trim($label);
    if ($raw === '') {
        return 'scheduled';
    }

    $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '_', $raw), '_'));
    $map = [
        'placeholder'                 => 'placeholder',
        'needs_paperwork'             => 'needs_paperwork',
        'scheduled_needs_paperwork'   => 'needs_paperwork',
        'scheduled'                   => 'scheduled',
        'assigned'                    => 'assigned',
        'preplanned'                  => 'preplanned',
        'pre_planned'                 => 'preplanned',
        'pre_plan'                    => 'preplanned',
        'dispatched'                  => 'dispatched',
        'canceled'                    => 'canceled',
        'cancelled'                   => 'canceled',
        'completed'                   => 'completed',
        'paid'                        => 'paid',
        'duplicate'                   => 'duplicate'
    ];
    if ($slug !== '' && isset($map[$slug])) {
        return $map[$slug];
    }

    if (preg_match('/pre\s*plan/i', $raw)) {
        return 'preplanned';
    }
    if (preg_match('/assign/i', $raw)) {
        return 'assigned';
    }
    if (preg_match('/dispatch/i', $raw)) {
        return 'dispatched';
    }
    if (preg_match('/paper/i', $raw)) {
        return 'needs_paperwork';
    }
    if (preg_match('/cancel/i', $raw)) {
        return 'canceled';
    }
    if (preg_match('/complete/i', $raw)) {
        return 'completed';
    }
    if (preg_match('/paid/i', $raw)) {
        return 'paid';
    }

    return 'scheduled';
}

function parse_weight(string $raw): ?float {
    if ($raw === '') {
        return null;
    }
    $clean = str_replace([',', 'lbs', 'lb'], '', strtolower($raw));
    $clean = trim($clean);
    if ($clean === '') {
        return null;
    }
    if (!is_numeric($clean)) {
        $clean = preg_replace('/[^0-9.\-]/', '', $clean);
    }
    return $clean === '' ? null : (float)$clean;
}

function resolve_contractor(mysqli $db, string $name): ?int {
    if ($name === '') {
        return null;
    }
    $nameEsc = $db->real_escape_string($name);
    $sql = "SELECT id FROM contractors WHERE LOWER(name) = LOWER('" . $nameEsc . "') LIMIT 1";
    if (!$res = $db->query($sql)) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return $row ? (int)$row['id'] : null;
}

function normalize_contractor_name(string $raw): string {
    if ($raw === '') return '';
    $trimmed = preg_replace('/[\d#\/].*/', '', $raw);
    $trimmed = trim($trimmed);
    $parts = preg_split('/\s+/', $trimmed);
    if (!$parts) {
        return '';
    }
    if (count($parts) >= 2) {
        return $parts[0] . ' ' . $parts[1];
    }
    return $parts[0];
}

function lookup_default_contractor(mysqli $db): ?int {
    $res = $db->query("SELECT id FROM contractors WHERE LOWER(name) = 'will advise' LIMIT 1");
    if (!$res) {
        return null;
    }
    $row = $res->fetch_assoc();
    $res->free();
    return $row ? (int)$row['id'] : null;
}

function extract_city_state(string $raw): ?string {
    $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $normalized);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/([A-Za-z][A-Za-z .\'\-]*)\s*,\s*([A-Z]{2})(?:\s+\d{5}(?:-\d{4})?)?$/', $line, $m)) {
            $city = preg_replace('/\s+/', ' ', trim($m[1]));
            $state = strtoupper($m[2]);
            if ($city !== '' && $state !== '') {
                return $city . ', ' . $state;
            }
        }
    }
    if (preg_match('/([A-Za-z][A-Za-z .\'\-]*)\s*,\s*([A-Z]{2})(?:\s+\d{5}(?:-\d{4})?)?/', $raw, $m)) {
        $city = preg_replace('/\s+/', ' ', trim($m[1]));
        $state = strtoupper($m[2]);
        if ($city !== '' && $state !== '') {
            return $city . ', ' . $state;
        }
    }
    return null;
}
