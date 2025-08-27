<?php
include '/home/freeman/job_scheduler.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

$contractors = [];
$res = $mysqli->query("SELECT id, name FROM contractors ORDER BY name");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $contractors[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <title>Admin Dashboard — Schedule NG</title>
</head>
<body>
  <h1>Admin Dashboard</h1>
  <ul>
    <li><a href="add_job.php">Add Job</a></li>
    <li><a href="contractors.php">Contractors</a></li>
    <li><a href="#" id="openDaily">View Daily Schedule</a></li>
    <li><a href="#" id="openRange">View Schedule Range</a></li>
  </ul>

  <dialog id="dailyModal">
    <form method="get" action="../view_contractor_schedule.php">
      <label>Contractor
        <select name="contractor_id" required>
          <option value="master">Master List</option>
<?php foreach ($contractors as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <label>Date
        <input type="date" name="date" value="<?= date('Y-m-d') ?>" required>
      </label>
      <div style="margin-top:1em;">
        <button type="submit">View</button>
        <button type="button" onclick="this.closest('dialog').close()">Cancel</button>
      </div>
    </form>
  </dialog>

  <dialog id="rangeModal">
    <form method="get" action="../view_contractor_range.php">
      <label>Contractor
        <select name="contractor_id" required>
          <option value="master">Master List</option>
<?php foreach ($contractors as $c): ?>
          <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
<?php endforeach; ?>
        </select>
      </label>
      <label>Start Date
        <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required>
      </label>
      <label>End Date
        <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" required>
      </label>
      <div style="margin-top:1em;">
        <button type="submit">View</button>
        <button type="button" onclick="this.closest('dialog').close()">Cancel</button>
      </div>
    </form>
  </dialog>

  <p><a href="../">Back to Schedule</a> | <a href="../logout.php">Logout</a></p>

  <script>
    document.getElementById('openDaily').addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('dailyModal').showModal();
    });
    document.getElementById('openRange').addEventListener('click', function(e) {
      e.preventDefault();
      document.getElementById('rangeModal').showModal();
    });
  </script>
</body>
</html>