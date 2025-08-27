<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once '/home/freeman/job_scheduler.php';
require_once __DIR__ . '/lib/magic_link.php';

$cid        = $_GET['contractor_id'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';

if (!$cid || !$start_date || !$end_date) {
    die('Invalid request. Contractor, start date, and end date are required.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
    die('Invalid date format.');
}

// Only admins can view ranges
if (($_SESSION['role'] ?? '') !== 'admin') {
    die('Access denied.');
}

$contractorName = '';
if ($cid === 'master') {
    $pageTitle = "Master List of All Jobs from $start_date to $end_date";
} else {
    $stmt = $mysqli->prepare('SELECT name FROM contractors WHERE id = ?');
    $stmt->bind_param('i', $cid);
    $stmt->execute();
    $stmt->bind_result($contractorName);
    $stmt->fetch();
    $stmt->close();
    if (!$contractorName) {
        die('Contractor not found.');
    }
    $pageTitle = htmlspecialchars($contractorName) . "'s Schedule from " . htmlspecialchars($start_date) . " to " . htmlspecialchars($end_date);
}

if ($cid === 'master') {
    $stmt = $mysqli->prepare("SELECT jd.uid, jd.work_date, jd.start_time, jd.end_time, jd.location, jd.status AS day_status,
                                       jd.tractors, jd.bobtails, jd.drivers, jd.movers, jd.installers, jd.pctechs,
                                       jd.supervisors, jd.project_managers, jd.crew_transport, jd.electricians,
                                       jd.day_notes, j.title AS customer_name, j.job_number, j.salesman,
                                       c.name AS contractor_name
                                FROM job_days jd
                                JOIN jobs j ON j.uid = jd.job_uid
                                JOIN contractors c ON c.id = jd.contractor_id
                                WHERE jd.work_date BETWEEN ? AND ?
                                ORDER BY jd.work_date, jd.start_time");
    $stmt->bind_param('ss', $start_date, $end_date);
} else {
    $stmt = $mysqli->prepare("SELECT jd.uid, jd.work_date, jd.start_time, jd.end_time, jd.location, jd.status AS day_status,
                                       jd.tractors, jd.bobtails, jd.drivers, jd.movers, jd.installers, jd.pctechs,
                                       jd.supervisors, jd.project_managers, jd.crew_transport, jd.electricians,
                                       jd.day_notes, j.title AS customer_name, j.job_number, j.salesman
                                FROM job_days jd
                                JOIN jobs j ON j.uid = jd.job_uid
                                WHERE jd.contractor_id = ? AND jd.work_date BETWEEN ? AND ?
                                ORDER BY jd.work_date, jd.start_time");
    $stmt->bind_param('iss', $cid, $start_date, $end_date);
}
$stmt->execute();
$res  = $stmt->get_result();
$jobs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function formatVehicles(array $row): string {
    $items = [
        'TTrailers'       => $row['tractors']       ?? 0,
        'Bobtails'        => $row['bobtails']       ?? 0,
        'Super'           => $row['supervisors']    ?? 0,
        'Drivers'         => $row['drivers']        ?? 0,
        'Movers'          => $row['movers']         ?? 0,
        'Installers'      => $row['installers']     ?? 0,
        'PC Techs'        => $row['pctechs']        ?? 0,
        'Proj Mgrs'       => $row['project_managers'] ?? 0,
        'Crew Transport'  => $row['crew_transport'] ?? 0,
        'Electricians'    => $row['electricians']   ?? 0,
    ];
    $lines = [];
    foreach ($items as $label => $count) {
        if ((int)$count > 0) {
            $lines[] = "$count $label";
        }
    }
    return $lines ? implode('<br>', $lines) : 'None';
}

function firstAttachment(string $uid): ?string {
    foreach (['bol', 'extra'] as $bucket) {
        $dir = __DIR__ . "/uploads/$uid/$bucket/";
        if (is_dir($dir)) {
            foreach (scandir($dir) as $fn) {
                if ($fn === '.' || $fn === '..') continue;
                return "./uploads/$uid/$bucket/" . rawurlencode($fn);
            }
        }
    }
    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style> table { font-size: 0.9rem; } </style>
</head>
<body class="container mt-5">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h1 class="mb-0"><?= $pageTitle ?></h1>
        <div class="d-flex align-items-center">
            <span class="mr-2">Print Jobs</span>
            <button onclick="window.print()" class="btn btn-light" title="Print this page">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16">
                  <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
                  <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>
                </svg>
            </button>
        </div>
    </div>

<?php if (empty($jobs)): ?>
    <p>No jobs found <?= ($cid === 'master') ? 'for this date range.' : 'for this contractor in this date range.'; ?></p>
<?php else: ?>
    <table class="table table-striped">
        <thead>
            <tr>
                <?php if ($cid === 'master'): ?>
                    <th>Contractor</th>
                <?php endif; ?>
                <th>Salesman</th>
                <th>Job Number</th>
                <th>Customer</th>
                <th>Location</th>
                <th>Date</th>
                <th>Time</th>
                <th>Vehicles/Labor</th>
                <th>Status</th>
                <th>Notes</th>
                <th>Attachment</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($jobs as $job):
            $start = date('g:i A', strtotime($job['start_time']));
            $end   = date('g:i A', strtotime($job['end_time']));
            $vehiclesLabor = formatVehicles($job);
            $notes = nl2br(htmlspecialchars($job['day_notes'] ?? ''));
            $attach = '';
            if ($file = firstAttachment($job['uid'])) {
                $attach = '<a href="' . $file . '" target="_blank">View File</a>';
            } else {
                $attach = 'None';
            }
        ?>
            <tr>
                <?php if ($cid === 'master'): ?>
                    <td><?= htmlspecialchars($job['contractor_name']) ?></td>
                <?php endif; ?>
                <td><?= htmlspecialchars($job['salesman'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['job_number'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['customer_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['location'] ?? '') ?></td>
                <td><?= htmlspecialchars($job['work_date']) ?></td>
                <td><?= $start . ' - ' . $end ?></td>
                <td><?= $vehiclesLabor ?></td>
                <td><?= htmlspecialchars($job['day_status'] ?? '') ?></td>
                <td><?= $notes ?></td>
                <td><?= $attach ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>