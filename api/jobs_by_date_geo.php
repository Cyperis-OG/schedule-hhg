<?php
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? date('Y-m-d');

$sql = "SELECT jd.uid, jd.location, j.title, j.job_number, jd.start_time, jd.end_time, c.name AS contractor,
               JSON_EXTRACT(j.meta,'$.lat') AS lat,
               JSON_EXTRACT(j.meta,'$.lng') AS lng
        FROM job_days jd
        JOIN jobs j ON j.uid = jd.job_uid
        LEFT JOIN contractors c ON c.id = jd.contractor_id
        WHERE jd.work_date=?";
$stmt=$mysqli->prepare($sql);
$stmt->bind_param('s',$date); $stmt->execute();
$res=$stmt->get_result();

$out=[];
while($r=$res->fetch_assoc()){
  $out[]=[
    'job_day_uid'=>$r['uid'],
    'title'=>$r['title'] . ($r['job_number'] ? " ({$r['job_number']})":''),
    'location'=>$r['location'],
    'contractor'=>$r['contractor'],
    'time'=>substr($r['start_time'],0,5) . ' - ' . substr($r['end_time'],0,5),
    'lat'=> $r['lat'] ? (float)$r['lat'] : null,
    'lng'=> $r['lng'] ? (float)$r['lng'] : null
  ];
}
echo json_encode($out);
