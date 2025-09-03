<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/magic_link.php';

$cid  = $_GET['contractor_id'] ?? '';
$date = $_GET['date'] ?? '';
$tok  = $_GET['tok'] ?? '';

if (!$cid || !$date) {
    die('Invalid request. Contractor and date are required.');
}

// Allow if admin session or valid magic link
$allow = false;
if (($_SESSION['role'] ?? '') === 'admin') {
    $allow = true;
} elseif ($tok) {
    if ($cid === 'master' && magic_link_verify(0, $date, $tok)) {
        $allow = true;
    } elseif (ctype_digit((string)$cid) && magic_link_verify((int)$cid, $date, $tok)) {
        $allow = true;
    }
}
if (!$allow) {
    die('Access denied.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    die('Invalid date format.');
}

$contractorName = '';
if ($cid === 'master') {
    $contractorName = 'Master List';
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
}

if ($cid === 'master') {
    $stmt = $mysqli->prepare("SELECT jd.uid, jd.start_time, jd.end_time, jd.location,
                                       jd.tractors, jd.bobtails, jd.drivers, jd.movers, jd.installers, jd.pctechs,
                                       jd.supervisors, jd.project_managers, jd.crew_transport, jd.electricians,
                                       jd.day_notes, j.title AS customer_name, j.job_number, j.salesman,
                                       c.name AS contractor_name
                                FROM job_days jd
                                JOIN jobs j ON j.uid = jd.job_uid
                                JOIN contractors c ON c.id = jd.contractor_id
                                WHERE jd.work_date = ?
                                ORDER BY c.display_order, jd.start_time");
    $stmt->bind_param('s', $date);
} else {
    $stmt = $mysqli->prepare("SELECT jd.uid, jd.start_time, jd.end_time, jd.location,
                                       jd.tractors, jd.bobtails, jd.drivers, jd.movers, jd.installers, jd.pctechs,
                                       jd.supervisors, jd.project_managers, jd.crew_transport, jd.electricians,
                                       jd.day_notes, j.title AS customer_name, j.job_number, j.salesman
                                FROM job_days jd
                                JOIN jobs j ON j.uid = jd.job_uid
                                WHERE jd.contractor_id = ? AND jd.work_date = ?
                                ORDER BY jd.start_time");
    $stmt->bind_param('is', $cid, $date);
}
$stmt->execute();
$res  = $stmt->get_result();
$jobs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$formattedDate = date('l m/d/Y', strtotime($date));
if ($cid === 'master') {
    $pageTitle  = "Master List of All Jobs for $formattedDate";
    $titleLine1 = 'Master Schedule';
    $titleLine2 = $formattedDate;
} else {
    $safeName   = htmlspecialchars($contractorName);
    $pageTitle  = $safeName . "'s Schedule for " . $formattedDate;
    $titleLine1 = $safeName . "'s Schedule";
    $titleLine2 = $formattedDate;
}

function formatVehicles(array $row): string {
    $items = [
        'TTrailers'      => $row['tractors']      ?? 0,
        'Bobtails'       => $row['bobtails']      ?? 0,
        'Crew Transport' => $row['crew_transport'] ?? 0,
    ];
    $lines = [];
    foreach ($items as $label => $count) {
        if ((int)$count > 0) {
            $lines[] = "$count $label";
        }
    }
    return $lines ? implode('<br>', $lines) : 'None';
}

function formatLabor(array $row): string {
    $items = [
        'Super'        => $row['supervisors']     ?? 0,
        'Drivers'      => $row['drivers']         ?? 0,
        'Movers'       => $row['movers']          ?? 0,
        'Installers'   => $row['installers']      ?? 0,
        'PC Techs'     => $row['pctechs']         ?? 0,
        'Proj Mgrs'    => $row['project_managers'] ?? 0,
        'Electricians' => $row['electricians']    ?? 0,
    ];
    $lines = [];
    foreach ($items as $label => $count) {
        if ((int)$count > 0) {
            $lines[] = "$count $label";
        }
    }
    return $lines ? implode('<br>', $lines) : 'None';
}

function listAttachments(string $uid): string {
    $links = [];

    $bolDir = __DIR__ . "/uploads/$uid/bol/";
    if (is_dir($bolDir)) {
        foreach (scandir($bolDir) as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $links[] = "<a href='./uploads/$uid/bol/" . rawurlencode($fn) . "' target='_blank'>View BOL/CSO</a>";
            break; // Only one BOL/CSO link
        }
    }

    $extraDir = __DIR__ . "/uploads/$uid/extra/";
    if (is_dir($extraDir)) {
        $i = 1;
        foreach (scandir($extraDir) as $fn) {
            if ($fn === '.' || $fn === '..') continue;
            $links[] = "<a href='./uploads/$uid/extra/" . rawurlencode($fn) . "' target='_blank'>Additional File $i</a>";
            $i++;
        }
    }

    return $links ? implode('<br>', $links) : 'No Files Uploaded';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?></title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        table { font-size: 0.9rem; }
        .job-table {
            border-collapse: separate;
            border-spacing: 0;
        }
        .job-block td {
            border-left: 1px solid #dee2e6;
            border-right: 1px solid #dee2e6;
            border-top: none;
            border-bottom: none;
        }
        .job-title td {
            border-top: 1px solid #dee2e6;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            font-weight: bold;
            text-align: center;
            font-size: 1.1rem;
        }
        .job-notes td {
            border-bottom: 1px solid #dee2e6;
            border-bottom-left-radius: 0.25rem;
            border-bottom-right-radius: 0.25rem;
        }
        .job-spacer td {
            border: none;
            padding: 0;
            height: 10px;
        }
    </style>
</head>
<body class="container mt-5">
    <div class="d-flex align-items-start justify-content-between mb-3">
        <div>
            <h1 class="mb-0"><?= $titleLine1 ?></h1>
            <h4 class="mb-0"><?= $titleLine2 ?></h4>
        </div>
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
    <p>No jobs found <?= ($cid === 'master') ? 'for this date.' : 'for this contractor on this date.'; ?></p>
<?php else: ?>
    <table class="table job-table">
        <thead>
            <tr>
                <?php if ($cid === 'master'): ?>
                    <th>Contractor</th>
                <?php endif; ?>
                <th>Time</th>
                <th>Job Number</th>
                <th>Customer</th>
                <th>Salesman</th>
                <th>Location</th>
                <th>Vehicles</th>
                <th>Labor</th>
                <th>Attachment</th>
            </tr>
        </thead>
        <tbody>
            <tr class="job-spacer">
                <?php if ($cid === 'master'): ?>
                    <td colspan="9"></td>
                <?php else: ?>
                    <td colspan="8"></td>
                <?php endif; ?>
            </tr>
        <?php $total = count($jobs); $i = 0; foreach ($jobs as $job):
            $start = date('g:i A', strtotime($job['start_time']));
            $end   = date('g:i A', strtotime($job['end_time']));
            $vehicles = formatVehicles($job);
            $labor    = formatLabor($job);
            $notes = nl2br(htmlspecialchars($job['day_notes'] ?? ''));
            $attach = listAttachments($job['uid']);
            $rowClass = ($i % 2 === 0) ? 'bg-light' : '';
            $i++;
        ?>
            <tr class="job-title job-block <?= $rowClass ?>">
                <?php if ($cid === 'master'): ?>
                    <td colspan="9"><?= htmlspecialchars($job['customer_name'] ?? 'N/A') ?></td>
                <?php else: ?>
                    <td colspan="8"><?= htmlspecialchars($job['customer_name'] ?? 'N/A') ?></td>
                <?php endif; ?>
            </tr>
            <tr class="job-block <?= $rowClass ?>">
                <?php if ($cid === 'master'): ?>
                    <td><?= htmlspecialchars($job['contractor_name']) ?></td>
                <?php endif; ?>
                <td><?= $start . ' - ' . $end ?></td>
                <td><?= htmlspecialchars($job['job_number'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['customer_name'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['salesman'] ?? 'N/A') ?></td>
                <td><?= htmlspecialchars($job['location'] ?? '') ?></td>
                <td><?= $vehicles ?></td>
                <td><?= $labor ?></td>
                <td><?= $attach ?></td>
            </tr>
            <tr class="job-notes job-block <?= $rowClass ?>">
                <?php if ($cid === 'master'): ?>
                    <td colspan="9"><strong>Notes:</strong> <?= $notes ?: 'None' ?></td>
                <?php else: ?>
                    <td colspan="8"><strong>Notes:</strong> <?= $notes ?: 'None' ?></td>
                <?php endif; ?>
            </tr>
            <?php if ($i < $total): ?>
            <tr class="job-spacer">
                <?php if ($cid === 'master'): ?>
                    <td colspan="9"></td>
                <?php else: ?>
                    <td colspan="8"></td>
                <?php endif; ?>
            </tr>
            <?php endif; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
</body>
</html>