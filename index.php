<?php
/**
 * /home/freeman/public_html/095/schedule-ng/index.php
 * Schedule NG — modular build (core + DnD + QuickAdd + QuickInfo)
 */
include '/home/freeman/job_scheduler.php';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Schedule NG</title>

  <?php include __DIR__ . '/includes/syncfusion_cdn.php'; ?>

  <!-- App styles -->
  <link rel="stylesheet" href="./assets/app.css" />
</head>
<body>
  <header class="topbar">
    <div class="brand">Schedule NG</div>
<label class="switch">
  <input type="checkbox" id="dragToggle" checked>
  <span class="slider round">Drag &amp; Drop</span>
</label>
  </header>

  <div id="Schedule"></div>

  <!-- App config (before scripts that read it) -->
  <script>
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
      MAX_DAYS: 5
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
  <script src="./assets/js/dnd.js"></script>            <!-- drag/resize toggle & guards -->
  <script src="./assets/js/persist-moves.js"></script>  <!-- dragStop/resizeStop -> POST to server -->
  <script src="./assets/js/quickinfo.js"></script>      <!-- centered quick info dialog -->
  <script src="./assets/js/quickadd.js"></script>       <!-- add job (multi-day) -->
  <script src="./assets/js/editjob.js"></script>        <!-- edit job (multi-day) -->

</body>
</html>
