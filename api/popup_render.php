<?php
// api/popup_render.php
// Returns HTML for the quick-info popup using /config/popup_template.json
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$isAdmin = (($_SESSION['role'] ?? '') === 'admin');

header('Content-Type: application/json');

$job_day_uid = $_GET['job_day_uid'] ?? '';
if ($job_day_uid === '') {
    http_response_code(400);
    echo json_encode(['error' => 'missing job_day_uid']); exit;
}

// 1) Load template JSON
$templatePath = SCHEDULE_DIR . 'config/popup_template.json';
$template = json_decode(file_get_contents($templatePath), true);
if (!$isAdmin && isset($template['actions']) && is_array($template['actions'])) {
    $template['actions'] = array_values(array_filter($template['actions'], function($a) {
        return !in_array($a['id'] ?? '', ['copy','edit','delete'], true);
    }));
}

// 2) Fetch job_day + job + contractor rows
$sql = "SELECT jd.*, j.title, j.job_number, j.salesman, j.status AS job_status, j.notes, j.meta AS job_meta,
               c.name AS contractor_name
        FROM job_days jd
        JOIN jobs j ON j.uid = jd.job_uid
        LEFT JOIN contractors c ON c.id = jd.contractor_id
        WHERE jd.uid = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $job_day_uid);
$stmt->execute();
$res = $stmt->get_result();
if (!$row = $res->fetch_assoc()) {
    http_response_code(404);
    echo json_encode(['error' => 'not found']); exit;
}

$statusLabels = [
    'placeholder'      => 'Placeholder',
    'needs_paperwork'  => 'Scheduled - Needs Paperwork',
    'scheduled'        => 'Scheduled',
    'dispatched'       => 'Dispatched',
    'canceled'         => 'Canceled',
    'completed'        => 'Completed',
    'paid'             => 'Paid'
];
$row['status']      = $statusLabels[strtolower($row['status']      ?? '')] ?? ($row['status'] ?? '');
$row['job_status']  = $statusLabels[strtolower($row['job_status']  ?? '')] ?? ($row['job_status'] ?? '');

// 3) Tiny template expansion
function val($path, $row) {
    // supports {{jobs.x}}, {{job_days.y}}, {{contractors.name}}, and a || fallback
    $parts = explode('||', $path); // fallback chain
    foreach ($parts as $p) {
        $p = trim($p);
        if (!preg_match('/^\{\{(.+)\}\}$/', $p, $m)) continue;
        $key = $m[1]; // e.g. job_days.start_time
        if ($key === '') continue;
        $segments = explode('.', $key);
        $root = $segments[0];
        $sub  = $segments[1] ?? null;
        if ($root === 'jobs') {
            if ($sub === 'meta') {
                // JSON meta on jobs
                $meta = json_decode($row['job_meta'] ?? 'null', true) ?: [];
                $leaf = $segments[2] ?? null;
                if ($leaf && array_key_exists($leaf, $meta)) return htmlspecialchars((string)$meta[$leaf]);
            } else {
                $map = [
                  'title'=>'title','job_number'=>'job_number','salesman'=>'salesman','status'=>'job_status','notes'=>'notes'
                ];
                if (isset($map[$sub]) && isset($row[$map[$sub]])) return nl2br(htmlspecialchars((string)$row[$map[$sub]]));
            }
        } elseif ($root === 'job_days') {
            $subMap = ['start_time','end_time','location','status','tractors','bobtails','movers','drivers','installers','supervisors','pctechs','project_managers','crew_transport','electricians','day_notes'];
            if (in_array($sub, $subMap, true) && isset($row[$sub])) return htmlspecialchars((string)$row[$sub]);
        } elseif ($root === 'contractors') {
            if ($sub === 'name') return htmlspecialchars((string)($row['contractor_name'] ?? ''));
        }
    }
    return '';
}

// Build HTML
ob_start(); ?>
<div class="qi">
  <h5><?= htmlspecialchars($template['title'] ? str_replace('{{jobs.title}}', $row['title'], $template['title']) : $row['title']) ?></h5>
  <?php foreach ($template['sections'] as $sec): ?>
    <div class="sec">
      <div class="sec-label"><strong><?= htmlspecialchars($sec['label']) ?></strong></div>
      <div class="sec-body">
        <?php foreach ($sec['rows'] as $r): ?>
          <div class="row">
            <div class="col-left"><?= val($r[0], $row) ?></div>
            <div class="col-right"><?= $r[1] ? val($r[1], $row) : '' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
      <hr>
    </div>
  <?php endforeach; ?>
  <div class="actions">
    <?php foreach ($template['actions'] as $a): ?>
      <button data-action="<?= htmlspecialchars($a['id']) ?>" class="btn btn-<?= htmlspecialchars($a['kind']) ?>">
        <?= htmlspecialchars($a['label']) ?>
      </button>
    <?php endforeach; ?>
  </div>
</div>
<?php
$html = ob_get_clean();

echo json_encode(['html' => $html, 'job_day_uid' => $job_day_uid]);
