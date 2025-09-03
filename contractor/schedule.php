<?php
require_once __DIR__ . '/../config.php';
date_default_timezone_set('America/Chicago');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'contractor') { header('Location: ../contractor_login.php'); exit; }
$cid = (int)($_SESSION['contractor_id'] ?? 0);
$date = $_GET['date'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');
$prev = date('Y-m-d', strtotime($date.' -1 day'));
$next = date('Y-m-d', strtotime($date.' +1 day'));

$stmt = $mysqli->prepare("SELECT jd.uid, jd.start_time, jd.end_time, jd.location, jd.tractors, jd.bobtails, jd.drivers, jd.movers, jd.installers, jd.pctechs, jd.supervisors, jd.project_managers, jd.crew_transport, jd.electricians, jd.day_notes, jd.status, j.title AS customer_name, j.job_number, j.salesman FROM job_days jd JOIN jobs j ON j.uid = jd.job_uid WHERE jd.contractor_id=? AND jd.work_date=? ORDER BY jd.start_time");
$stmt->bind_param('is', $cid, $date);
$stmt->execute();
$res = $stmt->get_result();
$jobs = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$today = date('Y-m-d');
$tomorrow = date('Y-m-d', strtotime($today.' +1 day'));
$now = new DateTime('now', new DateTimeZone('America/Chicago'));
$showPreviewNotice = ($date > $tomorrow) || ($date === $tomorrow && ((int)$now->format('Hi') < 1600));

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }
function fmtTime($t){ return $t ? substr($t,0,5) : ''; }
function fmtVehicles($r){
  $items = ['TTrailers'=>$r['tractors']??0,'Bobtails'=>$r['bobtails']??0,'Crew Transport'=>$r['crew_transport']??0];
  $out=[]; foreach($items as $k=>$v){ if((int)$v>0)$out[]="$v $k"; }
  return $out?implode(', ',$out):'None';
}
function fmtLabor($r){
  $items=['Super'=>$r['supervisors']??0,'Drivers'=>$r['drivers']??0,'Movers'=>$r['movers']??0,'Installers'=>$r['installers']??0,'PC Techs'=>$r['pctechs']??0,'Proj Mgrs'=>$r['project_managers']??0,'Electricians'=>$r['electricians']??0];
  $out=[]; foreach($items as $k=>$v){ if((int)$v>0)$out[]="$v $k"; }
  return $out?implode(', ',$out):'None';
}
function listAttachments($uid){
  $links=[];
  $bolDir=__DIR__.'/../uploads/'.$uid.'/bol/';
  if(is_dir($bolDir)){
    foreach(scandir($bolDir) as $fn){ if($fn==='.'||$fn==='..') continue; $links[]="<a href='../uploads/$uid/bol/".rawurlencode($fn)."' target='_blank'>BOL/CSO</a>"; break; }
  }
  $extraDir=__DIR__.'/../uploads/'.$uid.'/extra/';
  if(is_dir($extraDir)){
    $i=1; foreach(scandir($extraDir) as $fn){ if($fn==='.'||$fn==='..') continue; $links[]="<a href='../uploads/$uid/extra/".rawurlencode($fn)."' target='_blank'>File $i</a>"; $i++; }
  }
  return $links?implode(' | ',$links):'No Files Uploaded';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?= SCHEDULE_NAME ?> Schedule</title>
  <style>
    body { font-family:sans-serif; padding:10px; }
    .nav { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
    .nav a { text-decoration:none; font-size:1.2rem; }
    .date { font-weight:bold; }
    .pending-banner { background:#ffdddd; color:#a00; padding:10px; font-weight:bold; text-align:center; margin-bottom:10px; }
    .job-btn { width:100%; padding:15px; margin:8px 0; font-size:1.1rem; border:none; border-radius:4px; cursor:pointer; }
    .job-btn.status-placeholder { background:#b0b0b0; color:#fff; }
    .job-btn.status-needs_paperwork { background:#4b9dd3; color:#fff; }
    .job-btn.status-scheduled { background:#003366; color:#fff; }
    .job-btn.status-dispatched { background:#e68a00; color:#fff; }
    .job-btn.status-canceled { background:#800020; color:#fff; }
    .job-btn.status-completed { background:#10b981; color:#fff; }
    .job-btn.status-paid { background:#10b981; color:#fff; border:2px solid #d4af37; }
    .job-details { padding:10px; background:#f0f0f0; border-radius:8px; }
  </style>
</head>
<body>
  <div class="nav">
    <a href="schedule.php?date=<?= $prev ?>">&lt; Prev Day</a>
    <span class="date"><?= date('m/d/Y', strtotime($date)) ?></span>
    <a href="schedule.php?date=<?= $next ?>">Next Day &gt;</a>
  </div>
  <?php if ($showPreviewNotice): ?>
    <div class="pending-banner">Pending - not confirmed</div>
  <?php endif; ?>

  <?php if (!$jobs): ?>
    <p>No jobs for this day.</p>
  <?php endif; ?>

  <?php foreach ($jobs as $j): $uid=h($j['uid']); $status=strtolower($j['status'] ?? ''); ?>
    <button class="job-btn status-<?= h($status) ?>" onclick="toggleJob('<?= $uid ?>')">
      <?= h($j['customer_name']) ?> (<?= fmtTime($j['start_time']) ?>-<?= fmtTime($j['end_time']) ?>)
    </button>
    <div id="d<?= $uid ?>" class="job-details" style="display:none;">
      <div><strong>Location:</strong> <?= h($j['location']) ?></div>
      <div><strong>Vehicles:</strong> <?= fmtVehicles($j) ?></div>
      <div><strong>Labor:</strong> <?= fmtLabor($j) ?></div>
      <div><strong>Notes:</strong> <?= nl2br(h($j['day_notes'])) ?></div>
      <div><strong>Attachments:</strong> <?= listAttachments($j['uid']) ?></div>
    </div>
  <?php endforeach; ?>
  <p style="text-align:center;margin-top:20px;"><a href="index.php">Back</a></p>
  <script>
    const SHOW_PREVIEW = <?= $showPreviewNotice ? 'true' : 'false' ?>;
    function toggleJob(uid){
      const d=document.getElementById('d'+uid);
      if(d.style.display==='none'){
        if(SHOW_PREVIEW){
          alert('This job is unconfirmed and available for preview. It is not yours or confirmed until the schedule is sent out and finalized.');
        }
        d.style.display='block';
      }else{
        d.style.display='none';
      }
    }
    if(SHOW_PREVIEW){
      window.addEventListener('load', function(){
        alert('The schedule is not final yet and is only a preview. Please check back at 4 PM for the final schedule.');
      });
    }
  </script>
</body>
</html>