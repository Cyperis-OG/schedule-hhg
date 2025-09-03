<?php
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// Basic user-agent check to detect mobile devices
$isMobile = preg_match('/Mobi|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

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
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard — <?= SCHEDULE_NAME ?></title>
  <link rel="stylesheet" href="admin.css" />
  <style>
    .container { max-width:900px; margin:0 auto; padding:20px; }
    h1 { text-align:center; margin-bottom:1.5rem; }
  </style>
</head>
<body class="<?= $isMobile ? 'mobile' : 'desktop' ?>">
  <div class="admin-nav">
    <a class="btn" href="index.php">Back to Admin Panel</a>
    <a class="btn" href="../">Back to Schedule</a>
    <a class="btn" href="../logout.php">Logout</a>
  </div>
  <div class="container">
    <h1>Admin Dashboard</h1>
    <ul class="menu">
      <li><a class="btn" href="mobile_schedule.php">Mobile Schedule</a><span class="desc">View mobile schedule</span></li>
      <li><a class="btn" href="add_job.php">Add Job</a><span class="desc">Create a new job with one or more days</span></li>
      <li><a class="btn" href="contractors.php">Contractors</a><span class="desc">Manage contractor records and status</span></li>
      <li><a class="btn" href="customers.php">Customers</a><span class="desc">View and edit customer details</span></li>
      <li><a class="btn" href="salesmen.php">Salesmen</a><span class="desc">Maintain salesman contact info</span></li>
      <li><a class="btn" href="job_fields.php">Job Fields</a><span class="desc">Configure per-day job fields</span></li>
      <li><a class="btn" href="manual_email.php">Send Emails</a><span class="desc">Email schedules to contractors</span></li>
      <li><a class="btn" href="master_recipients.php">Admin Email Recipients</a><span class="desc">Manage admin recipient list</span></li>
      <li><a class="btn" href="#" id="openDaily">View Daily Schedule</a><span class="desc">Open a contractor's daily schedule</span></li>
      <li><a class="btn" href="#" id="openRange">View Schedule Range</a><span class="desc">View schedules across dates</span></li>
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
          <input type="date" name="date" value="<?= date('Y-m-d') ?>" required />
        </label>
        <div class="actions">
          <button type="submit" class="btn">View</button>
          <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancel</button>
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
          <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required />
        </label>
        <label>End Date
          <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" required />
        </label>
        <div class="actions">
          <button type="submit" class="btn">View</button>
          <button type="button" class="btn" onclick="this.closest('dialog').close()">Cancel</button>
        </div>
      </form>
    </dialog>
  </div>

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