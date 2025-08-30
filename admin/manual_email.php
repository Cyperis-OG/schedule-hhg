<?php
include '/home/freeman/job_scheduler.php';
require_once __DIR__ . '/../lib/magic_link.php';
require_once __DIR__ . '/../lib/email_helpers.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') { header('Location: ../login.php'); exit; }

// ----------------------------------------------------------------------
// Simple file logger for troubleshooting email sending
// ----------------------------------------------------------------------
$logFile = __DIR__ . '/manual_email.log';
function manual_email_log(string $msg) {
    global $logFile;
    $timestamp = date('c');
    file_put_contents($logFile, "[{$timestamp}] {$msg}\n", FILE_APPEND);
}
manual_email_log('Page hit. method=' . ($_SERVER['REQUEST_METHOD'] ?? '')); // initial load

// Load master recipients
$masterRecipients = [];
$res = $mysqli->query("SELECT email FROM master_schedule_recipients WHERE active=1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        if ($email !== '') { $masterRecipients[] = $email; }
    }
    $res->close();
}
manual_email_log('Master recipients: ' . implode(',', $masterRecipients));

$date = $_GET['date'] ?? '';

// Sending emails
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $ids  = array_map('intval', $_POST['contractors'] ?? []);
    $sendMaster = isset($_POST['send_master']);
    manual_email_log('POST request date=' . $date . '; ids=' . implode(',', $ids) . '; sendMaster=' . ($sendMaster ? 'yes' : 'no'));
    $statuses = [];
    if ($date && $ids) {
        // Fetch contractor info
        $stmt = $mysqli->prepare("SELECT id,name,email_notify FROM contractors WHERE id IN (" . implode(',', array_fill(0,count($ids),'?')) . ")");
        $types = str_repeat('i', count($ids));
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $res = $stmt->get_result();
        $info = [];
        while ($row = $res->fetch_assoc()) { $info[$row['id']] = $row; }
        $stmt->close();
        manual_email_log('Fetched contractor info: ' . json_encode($info));

        foreach ($ids as $cid) {
            if (!isset($info[$cid])) { manual_email_log("Contractor ID {$cid} missing from info"); continue; }
            $emails = preg_split('/[\s,;]+/', $info[$cid]['email_notify'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
            if (!$emails) { manual_email_log("Contractor ID {$cid} has no email addresses"); continue; }
            $contractor = $info[$cid]['name'];
            $dt = new DateTimeImmutable($date);
            $pretty = $dt->format('l m/d/Y');
            $subject = "Job Schedule for {$contractor} on {$pretty}";
            $message = "Dear {$contractor},\n\nPlease click the button below to view your job schedule for {$pretty}.";
            $link = sprintf('https://example.com/095/schedule-ng/view_contractor_schedule.php?contractor_id=%d&date=%s&tok=%s',
                $cid, $date, magic_link_token($cid, $date));
            $body = generate_email_body($subject, $message, $link);
            manual_email_log('Sending to contractor id=' . $cid . ' emails=' . implode(',', $emails));
            $result = send_email($emails, $subject, $body, [], 'manual_email_log');
            manual_email_log('Result for contractor id=' . $cid . ': ' . ($result ? 'success' : 'failure'));
            $statuses[] = "Sent to {$contractor}";
        }
        if ($sendMaster && $masterRecipients) {
            $dt = new DateTimeImmutable($date);
            $pretty = $dt->format('l m/d/Y');
            $subject = "Master List of All Jobs on {$pretty}";
            $message = "Please click the button below to view the master list of all jobs for {$pretty}.";
            $link = sprintf('https://example.com/095/schedule-ng/view_contractor_schedule.php?contractor_id=master&date=%s&tok=%s',
                $date, magic_link_token(0, $date));
            $body = generate_email_body($subject, $message, $link);
            manual_email_log('Sending master list to: ' . implode(',', $masterRecipients));
            $result = send_email($masterRecipients, $subject, $body, [], 'manual_email_log');
            manual_email_log('Master list result: ' . ($result ? 'success' : 'failure'));
            $statuses[] = "Master list sent";
        }
    }
}

// Load contractors for given date
$contractors = [];
if ($date) {
    $stmt = $mysqli->prepare("SELECT DISTINCT c.id, c.name FROM job_days jd JOIN contractors c ON c.id=jd.contractor_id WHERE jd.work_date=? ORDER BY c.display_order");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $contractors[] = $row; }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Send Schedule Emails</title>
<style>
 body { font-family:sans-serif; margin:0; background:#f5f5f5; }
 .container { max-width:800px; margin:0 auto; padding:20px; }
 fieldset { border:1px solid #ccc; background:#fff; padding:10px; }
 legend { font-weight:bold; }
 label { display:block; margin:0.25rem 0; }
 button { margin-top:1rem; padding:0.5rem 1rem; }
</style>
</head>
<body>
<div class="container">
<h1>Send Schedule Emails</h1>
<form method="get">
  <label>Select Date:
    <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" required>
  </label>
  <button type="submit">Load</button>
</form>

<?php if (!empty($contractors)): ?>
<form method="post">
  <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>" />
  <fieldset>
    <legend>Contractors</legend>
    <?php foreach ($contractors as $c): ?>
      <label><input type="checkbox" name="contractors[]" value="<?= $c['id'] ?>" checked> <?= htmlspecialchars($c['name']) ?></label>
    <?php endforeach; ?>
  </fieldset>
  <label><input type="checkbox" name="send_master" checked> Send master schedule</label>
  <button type="submit">Send Emails</button>
</form>
<?php endif; ?>

<?php if (!empty($statuses)): ?>
  <h2>Statuses</h2>
  <ul>
    <?php foreach ($statuses as $s): ?><li><?= htmlspecialchars($s) ?></li><?php endforeach; ?>
  </ul>
<?php endif; ?>
</div>
</body>
</html>