<?php
include '/home/freeman/job_scheduler.php';
$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');

// Build per-contractor summaries & a global link
// For “magic links”, create rows in invite_tokens with role='contractor' and short expiry,
// or maintain a separate magic_links table. Then email each contractor their link.

$sql = "SELECT c.email_notify AS email, c.name AS contractor, jd.work_date, jd.start_time, jd.end_time, j.title, j.job_number
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
while ($r = $res->fetch_assoc()) { $by[$r['contractor']][] = $r; }

// … Compose and send mails (PHPMailer preferred). Left minimal here on purpose.
foreach ($by as $contractor => $rows) {
    $to = 'dispatch@example.com'; // or $rows[0]['email']
    $body = "Schedule for {$tomorrow} — {$contractor}\n\n";
    foreach ($rows as $x) {
        $body .= sprintf("%s (%s)  %s-%s\n", $x['title'], $x['job_number'], substr($x['start_time'],0,5), substr($x['end_time'],0,5));
    }
    @mail($to, "Tomorrow's Schedule: {$contractor}", $body); // swap with PHPMailer in production
}
echo "Sent.";
