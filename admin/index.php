<?php
include '/home/freeman/job_scheduler.php';
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
  <title>Admin Dashboard — Schedule NG</title>
  <style>
    body { font-family: sans-serif; margin:0; background:#f5f5f5; }
    .container { max-width: 900px; margin:0 auto; padding:20px; }
    h1 { text-align:center; margin-bottom:1.5rem; }
    nav ul { list-style:none; padding:0; margin:0; display:flex; flex-wrap:wrap; justify-content:center; gap:1rem; }
    nav a { display:block; padding:1rem 1.5rem; background:#0069d9; color:#fff; border-radius:4px; text-decoration:none; }
    nav a:hover { background:#0053ba; }
    dialog { border:none; border-radius:8px; padding:1rem; max-width:400px; }
    dialog::backdrop { background:rgba(0,0,0,0.3); }
    dialog label { display:block; margin-top:1rem; }
    dialog input, dialog select { width:100%; padding:0.5rem; margin-top:0.25rem; }
    dialog .actions { margin-top:1rem; display:flex; gap:0.5rem; }
    dialog button { flex:1; padding:0.5rem 1rem; }
    .links { text-align:center; margin-top:2rem; }
    .links a { color:#0069d9; text-decoration:none; margin:0 0.5rem; }
    <?php if ($isMobile): ?>
    nav ul { flex-direction:column; }
    nav a { font-size:1.1rem; }
    <?php else: ?>
    nav a { font-size:1.2rem; }
    <?php endif; ?>
    @media (max-width:600px) {
      nav ul { flex-direction:column; }
      nav a { font-size:1.1rem; }
      .container { padding:15px; }
      dialog { width:100%; }
    }
  </style>
</head>
<body class="<?= $isMobile ? 'mobile' : 'desktop' ?>">
  <div class="container">
    <h1>Admin Dashboard</h1>
    <nav>
      <ul>
        <li><a href="add_job.php">Add Job</a></li>
        <li><a href="contractors.php">Contractors</a></li>
        <li><a href="job_fields.php">Job Fields</a></li>
        <li><a href="manual_email.php">Send Emails</a></li>
        <li><a href="master_recipients.php">Admin Email Recipients</a></li>
        <li><a href="#" id="openDaily">View Daily Schedule</a></li>
        <li><a href="#" id="openRange">View Schedule Range</a></li>
      </ul>
    </nav>

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
          <input type="date" name="start_date" value="<?= date('Y-m-d') ?>" required />
        </label>
        <label>End Date
          <input type="date" name="end_date" value="<?= date('Y-m-d') ?>" required />
        </label>
        <div class="actions">
          <button type="submit">View</button>
          <button type="button" onclick="this.closest('dialog').close()">Cancel</button>
        </div>
      </form>
    </dialog>

    <div class="links">
      <a href="../">Back to Schedule</a> | <a href="../logout.php">Logout</a>
    </div>
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