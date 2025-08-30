<?php
include '/home/freeman/job_scheduler.php';
require_once __DIR__ . '/../lib/magic_link.php';
require_once __DIR__ . '/../lib/email_helpers.php';

// Additional addresses that should always be CC'd.
// Populate this array with strings like 'person@example.com'.
$extraCc = [];

// Load active admin users to CC on every mail.
$adminCc = [];
$res = $mysqli->query("SELECT email FROM users WHERE role='admin' AND status='active'");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        if ($email !== '') {
            $adminCc[] = $email;
        }
    }
    $res->close();
}
$ccList = array_merge($adminCc, $extraCc);

// Load master schedule recipients
$masterRecipients = [];
$res = $mysqli->query("SELECT email FROM master_schedule_recipients WHERE active=1");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $email = trim($row['email'] ?? '');
        if ($email !== '') {
            $masterRecipients[] = $email;
        }
    }
    $res->close();
}

// Determine which date(s) to send based on the day of week.
// 1=Mon ... 7=Sun
$today   = new DateTimeImmutable('today');
$dow     = (int)$today->format('N');
$targets = [];

// Delay between processing days (seconds)
$delaySeconds = (int)getenv('SCHEDULE_EMAIL_DELAY') ?: 300;

if ($dow >= 1 && $dow <= 4) {
    // Monday–Thursday → next day
    $targets[] = $today->modify('+1 day')->format('Y-m-d');
} elseif ($dow === 5) {
    // Friday → Saturday, Sunday, Monday
    $targets[] = $today->modify('+1 day')->format('Y-m-d');
    $targets[] = $today->modify('+2 day')->format('Y-m-d');
    $targets[] = $today->modify('+3 day')->format('Y-m-d');
} else {
    // Weekend cron runs should do nothing (already handled Friday)
    exit;
}

$targetCount = count($targets);

$sql = "SELECT c.id AS contractor_id, c.email_notify AS email, c.name AS contractor,
               jd.work_date, jd.start_time, jd.end_time, j.title, j.job_number
        FROM job_days jd
        JOIN jobs j ON j.uid = jd.job_uid
        JOIN contractors c ON c.id = jd.contractor_id
        WHERE jd.work_date = ? AND c.active=1
        ORDER BY c.display_order, jd.start_time";

foreach ($targets as $i => $day) {
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param('s', $day);
    $stmt->execute();
    $res = $stmt->get_result();

    $by = [];
    while ($r = $res->fetch_assoc()) {
        $cid = (int)$r['contractor_id'];
        if (!isset($by[$cid])) {
            $by[$cid] = [
                'name'  => $r['contractor'],
                'email' => $r['email'],
                'rows'  => []
            ];
        }
        $by[$cid]['rows'][] = $r;
    }
    $stmt->close();

    // Compose and send mails
    foreach ($by as $cid => $info) {
        $emails = preg_split('/[\s,;]+/', $info['email'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        if (!$emails) {
            $emails = ['dispatch@example.com'];
        }
        $contractor = $info['name'];

        $dt = new DateTimeImmutable($day);
        $prettyDay = $dt->format('l m/d/Y');
        $subject   = sprintf('Job Schedule for %s on %s', $contractor, $prettyDay);
        $message   = "Dear {$contractor},\n\nPlease click the button below to view your job schedule for {$prettyDay}.";
        $token     = magic_link_token($cid, $day);
        $link      = sprintf('https://example.com/095/schedule-ng/view_contractor_schedule.php?contractor_id=%d&date=%s&tok=%s',
            $cid, $day, $token);
        $body      = generate_email_body($subject, $message, $link);

        send_email($emails, $subject, $body, $ccList);
    }

    // Send master schedule
    if ($masterRecipients) {
        $dt = new DateTimeImmutable($day);
        $prettyDay = $dt->format('l m/d/Y');
        $subject = sprintf('Master List of All Jobs on %s', $prettyDay);
        $message = "Please click the button below to view the master list of all jobs for {$prettyDay}.";
        $link = sprintf('https://example.com/095/schedule-ng/view_contractor_schedule.php?contractor_id=master&date=%s&tok=%s',
            $day, magic_link_token(0, $day));
        $body = generate_email_body($subject, $message, $link);
        send_email($masterRecipients, $subject, $body);
    }

    if ($delaySeconds > 0 && $i < $targetCount - 1) {
        sleep($delaySeconds);
    }
}

echo "Sent.";