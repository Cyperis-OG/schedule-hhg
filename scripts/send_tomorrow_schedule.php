<?php
include '/home/freeman/job_scheduler.php';
require_once __DIR__ . '/../lib/magic_link.php';

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

// Determine which date(s) to send based on the day of week.
// 1=Mon ... 7=Sun
$today   = new DateTimeImmutable('today');
$dow     = (int)$today->format('N');
$targets = [];

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

$sql = "SELECT c.id AS contractor_id, c.email_notify AS email, c.name AS contractor,
               jd.work_date, jd.start_time, jd.end_time, j.title, j.job_number
        FROM job_days jd
        JOIN jobs j ON j.uid = jd.job_uid
        JOIN contractors c ON c.id = jd.contractor_id
        WHERE jd.work_date = ? AND c.active=1
        ORDER BY c.display_order, jd.start_time";

foreach ($targets as $day) {
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

    // Compose and send mails (PHPMailer preferred).
    foreach ($by as $cid => $info) {
        $emails = preg_split('/[\s,;]+/', $info['email'] ?? '', -1, PREG_SPLIT_NO_EMPTY);
        if (!$emails) {
            $emails = ['dispatch@example.com'];
        }
        $to = implode(',', $emails);
        $contractor = $info['name'];

        $dt = new DateTimeImmutable($day);
        $prettyDay = $dt->format('l m/d/Y');
        $subject   = sprintf('Job Schedule for %s on %s', $contractor, $prettyDay);

        $body = "Schedule for {$prettyDay} — {$contractor}\n\n";
        foreach ($info['rows'] as $x) {
            $body .= sprintf("%s (%s)  %s-%s\n",
                $x['title'], $x['job_number'], substr($x['start_time'],0,5), substr($x['end_time'],0,5));
        }

        $token = magic_link_token($cid, $day);
        $link  = sprintf('https://example.com/095/schedule-ng/view_contractor_schedule.php?contractor_id=%d&date=%s&tok=%s',
            $cid, $day, $token);
        $body .= "\nView schedule: {$link}\n";

        $headers = [];
        if ($ccList) {
            $headers[] = 'Cc: ' . implode(',', $ccList);
        }
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headersStr = implode("\r\n", $headers);

        @mail($to, $subject, $body, $headersStr); // swap with PHPMailer in production
    }
}

echo "Sent.";