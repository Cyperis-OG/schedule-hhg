<?php
/**
 * index.php — Schedule NG (modular build: core + DnD + QuickAdd + QuickInfo)
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/magic_link.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$initDate = $_GET['date'] ?? date('Y-m-d');
if (isset($_GET['ctr'], $_GET['tok'])) {
  $cid = (int)$_GET['ctr'];
  if (magic_link_verify($cid, $initDate, $_GET['tok'])) {
    $_SESSION['role'] = 'contractor';
    $_SESSION['contractor_id'] = $cid;
  }
}

$isAdmin = (($_SESSION['role'] ?? '') === 'admin');
$isMobile = preg_match('/Mobi|Android|iPhone/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
if ($isAdmin && $isMobile) {
  header('Location: ./admin/mobile_schedule.php?date=' . urlencode($initDate));
  exit;
}
$dayFieldsJson = file_get_contents(SCHEDULE_DIR . 'config/day_fields.json');
if ($dayFieldsJson === false) $dayFieldsJson = '[]';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title><?= SCHEDULE_NAME ?></title>

  <?php include __DIR__ . '/includes/syncfusion_cdn.php'; ?>

  <!-- App styles -->
  <link rel="stylesheet" href="./assets/app.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand"><?= SCHEDULE_NAME ?></div>
    <div class="actions">
      <?php if ($isAdmin): ?>
        <label class="switch dnd-toggle">
          <input type="checkbox" id="dragToggle" checked>
          <span class="slider round">Drag &amp; Drop</span>
        </label>
        <a class="btn sm" href="./admin/">Admin</a>
        <button class="btn sm" type="button" onclick="window.open('./view_contractor_schedule.php?contractor_id=master&date=' + encodeURIComponent(window.getViewYMD ? window.getViewYMD() : '<?= $initDate ?>'), '_blank')">Master List</button>
        <button class="btn sm" type="button" onclick="window.open('./map.php?date=' + encodeURIComponent(window.getViewYMD ? window.getViewYMD() : '<?= $initDate ?>'), '_blank')">Map</button>
        <button class="btn sm" type="button" id="importBtn">Import Schedule</button>
        <a class="btn sm" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="btn sm" href="login.php">Admin Login</a>
      <?php endif; ?>
    </div>
  </header>

  <div id="Schedule"></div>

  <!-- App config (before scripts that read it) -->
  <script>
    window.IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    window.SCH_CFG = {
      API: {
        fetchDay:        './api/jobs_fetch.php',
        popup:           './api/popup_render.php',
        persistTimeslot: './api/job_update_timeslot.php',
        saveJob:         './api/job_save.php',

        // NEW:
        editRead:        './api/job_full_get.php',   // returns { ok:true, job:{...}, days:[...] }
        editSave:        './api/job_full_save.php',  // accepts payload { job, days } for updates
        deleteJob:       './api/job_delete.php'      // delete a single day or whole job
      },
      DEFAULT_TZ: 'America/Chicago',
      MAX_DAYS: 5,
      INIT_DATE: '<?= $initDate ?>',
      DAY_FIELDS: <?= $dayFieldsJson ?>,
      BASE_PATH: '<?= BASE_PATH ?>'
    };

    if (window.ej?.schedule?.Schedule?.Inject) {
      ej.schedule.Schedule.Inject(
        ej.schedule.TimelineViews,
        ej.schedule.DragAndDrop,
        ej.schedule.Resize
      );
    }
  </script>

  <!-- Modules (order matters) -->
  <script src="./assets/js/core.js"></script>           <!-- builds scheduler, loadDay, exposes window.sch -->
  <script src="./assets/js/apptemplate.js"></script>    <!-- 2-line appointment template -->
  <?php if ($isAdmin): ?>
  <script src="./assets/js/dnd.js"></script>            <!-- drag/resize toggle & guards -->
  <script src="./assets/js/persist-moves.js"></script>  <!-- dragStop/resizeStop -> POST to server -->
  <?php endif; ?>
  <script src="./assets/js/quickinfo.js"></script>      <!-- centered quick info dialog -->
  <?php if ($isAdmin): ?>
  <?php $importJsVersion = @filemtime(__DIR__ . '/assets/js/import.js') ?: time(); ?>
  <script src="./assets/js/quickadd.js"></script>       <!-- add job (multi-day) -->
  <script src="./assets/js/editjob.js"></script>        <!-- edit job (multi-day) -->
  <script src="./assets/js/import.js?v=<?= $importJsVersion ?>"></script>         <!-- import jobs from XLSX -->
  <?php endif; ?>

</body>
</html>
