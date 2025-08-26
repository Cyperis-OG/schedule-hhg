<?php
/**
 * jobs_fetch.php — returns resources and events for a given YYYY-MM-DD
 * Emits ISO 8601 date-times and numeric resource ids.
 *
 * GET ?date=YYYY-MM-DD
 */
header('Content-Type: application/json');
require_once '/home/freeman/job_scheduler.php';

$day = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
  http_response_code(400);
  echo json_encode(['resources'=>[], 'events'=>[]]);
  exit;
}

function isoDT($d, $t) { return $d . 'T' . $t; }

// Check if a column exists in a table (cached per request)
function column_exists(mysqli $db, string $table, string $col): bool {
  static $cache = [];
  $key = $table . '.' . $col;
  if (!array_key_exists($key, $cache)) {
    $stmt = $db->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
    if ($stmt) {
      $stmt->bind_param('s', $col);
      $stmt->execute();
      $cache[$key] = (bool)$stmt->get_result()->fetch_assoc();
      $stmt->close();
    } else {
      $cache[$key] = false;
    }
  }
  return $cache[$key];
}

/* ----- RESOURCES FIRST ----- */
$resources = [];
$sqlR = "SELECT id, name, COALESCE(color_hex,'') AS color_hex
         FROM contractors
         WHERE (status IS NULL OR LOWER(status)='active' OR status='1')
         ORDER BY COALESCE(display_order, 9999) ASC, name ASC";
if ($rs = $mysqli->query($sqlR)) {
  while ($c = $rs->fetch_assoc()) {
    $resources[] = [
      'id'        => (int)$c['id'],
      'name'      => $c['name'],
      'color_hex' => $c['color_hex']
    ];
  }
  $rs->close();
}
if (empty($resources)) {
  // fallback: include everyone
  if ($rs2 = $mysqli->query(
        "SELECT id, name, COALESCE(color_hex,'') AS color_hex
         FROM contractors
         ORDER BY COALESCE(display_order, 9999) ASC, name ASC")) {
    while ($c = $rs2->fetch_assoc()) {
      $resources[] = [
        'id'        => (int)$c['id'],
        'name'      => $c['name'],
        'color_hex' => $c['color_hex']
      ];
    }
    $rs2->close();
  }
}

/* ----- EVENTS SECOND ----- */
$events = [];
$crewSel = column_exists($mysqli, 'job_days', 'crew_transport') ? 'jd.crew_transport' : '0 AS crew_transport';
$sqlE = "SELECT
           jd.uid        AS id,
           j.title       AS title,
           j.job_number  AS job_number,
           jd.work_date,
           jd.start_time,
           jd.end_time,
           jd.contractor_id,
           jd.location,
           jd.status,
           jd.tractors,
           jd.bobtails,
           jd.drivers,
           jd.movers,
           jd.installers,
           jd.pctechs,
           jd.supervisors,
           jd.project_managers,
           $crewSel,
           jd.electricians,
           jd.day_notes
         FROM job_days jd
         LEFT JOIN jobs j ON j.uid = jd.job_uid
         WHERE jd.work_date = ?
         ORDER BY jd.start_time ASC";

if ($stmt = $mysqli->prepare($sqlE)) {
  $stmt->bind_param('s', $day);
  $stmt->execute();
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $files = ['bol' => [], 'extra' => []];
    foreach (['bol', 'extra'] as $bucket) {
      $dir = __DIR__ . '/../uploads/' . $row['id'] . '/' . $bucket . '/';
      if (is_dir($dir)) {
        foreach (scandir($dir) as $fn) {
          if ($fn === '.' || $fn === '..') continue;
          $files[$bucket][] = '/095/schedule-ng/uploads/' . $row['id'] . '/' . $bucket . '/' . $fn;
        }
      }
    }

    $event = [
      'Id'           => $row['id'],
      'Subject'      => trim($row['title'] . ($row['job_number'] ? " ({$row['job_number']})" : '')),
      'Customer'     => $row['title'],
      'JobNumber'    => $row['job_number'],
      'StartTime'    => isoDT($row['work_date'], $row['start_time']),
      'EndTime'      => isoDT($row['work_date'], $row['end_time']),
      'ContractorId'   => is_null($row['contractor_id']) ? null : (int)$row['contractor_id'],
      'Location'       => $row['location'],
      'Status'         => $row['status'],
      'tractors'       => (int)$row['tractors'],
      'bobtails'       => (int)$row['bobtails'],
      'drivers'        => (int)$row['drivers'],
      'movers'         => (int)$row['movers'],
      'installers'     => (int)$row['installers'],
      'pctechs'        => (int)$row['pctechs'],
      'supervisors'    => (int)$row['supervisors'],
      'project_managers'=> (int)$row['project_managers'],
      'crew_transport'  => (int)$row['crew_transport'],
      'electricians'    => (int)$row['electricians'],
      'day_notes'       => $row['day_notes']
    ];
    if ($files['bol'] || $files['extra']) {
      $event['files'] = $files;
    }
    $events[] = $event;
  }
  $stmt->close();
}

echo json_encode(['resources' => $resources, 'events' => $events]);