<?php
/**
 * GET /schedule-ng/api/contractors_list.php
 * -----------------------------------------
 * Returns all contractors with ordering info.
 * Used by the admin page and (optionally) other UIs.
 *
 * Output:
 *  { contractors: [ {id, uid, name, active, display_order, color_hex} ... ] }
 */
include '/home/freeman/job_scheduler.php';
header('Content-Type: application/json');

$res = $mysqli->query("SELECT id, uid, name, active, display_order, color_hex
                       FROM contractors
                       ORDER BY active DESC, display_order ASC, name ASC");

$out = [];
while ($row = $res->fetch_assoc()) {
  $row['id'] = (int)$row['id'];
  $row['active'] = (int)$row['active'];
  $row['display_order'] = (int)$row['display_order'];
  $out[] = $row;
}
echo json_encode(['contractors' => $out], JSON_UNESCAPED_UNICODE);
