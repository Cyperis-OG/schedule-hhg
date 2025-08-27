<?php
include '/home/freeman/job_scheduler.php';
require_once __DIR__ . '/../lib/magic_link.php';

$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

// Build per-contractor summaries & a magic link for that day
$sql = "SELECT c.id AS contractor_id, c.email_notify AS email, c.name AS contractor,
               jd.work_date, jd.start_time, jd.end_time, j.title, j.job_number
        FROM job_days jd
        JOIN jobs j ON j.uid = jd.job_uid
        JOIN contractors c ON c.id = jd.contractor_id
        WHERE jd.work_date = ? AND c.active=1
        ORDER BY c.display_order, jd.start_time";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('s', $tomorrow);
$stmt->execute();
$res = $stmt->get_result();

$by = [];
while ($r = $res->fetch_assoc()) {
    $cid = (int)$r['contractor_id'];
    if (!isset($by[$cid])) {
        $by[$cid] = [
            'name' => $r['contractor'],
            'email' => $r['email'],
            'rows' => []
        ];
    }
    $by[$cid]['rows'][] = $r;
}

// … Compose and send mails (PHPMailer preferred). Left minimal here on purpose.
foreach ($by as $cid => $info) {
    $to = $info['email'] ?: 'dispatch@example.com';
    $contractor = $info['name'];

    $body = "Schedule for {$tomorrow} — {$contractor}\n\n";
    foreach ($info['rows'] as $x) {
        $body .= sprintf("%s (%s)  %s-%s\n",
            $x['title'], $x['job_number'], substr($x['start_time'],0,5), substr($x['end_time'],0,5));
    }

    $token = magic_link_token($cid, $tomorrow);
    $link = sprintf('https://example.com/095/schedule-ng/?date=%s&ctr=%d&tok=%s',
        $tomorrow, $cid, $token);
    $body .= "\nView schedule: {$link}\n";

    @mail($to, "Tomorrow's Schedule: {$contractor}", $body); // swap with PHPMailer in production
}

echo "Sent.";