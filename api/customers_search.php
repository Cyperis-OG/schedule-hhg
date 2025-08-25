<?php
/**
 * GET /095/schedule-ng/api/customers_search.php?q=term
 * ------------------------------------------------
 * Returns a small list of customers for autocomplete and their defaults:
 *  - default_location
 *  - default_salesman
 *  - preferred_contractor_id (+ its name)
 *  - last_job_number
 *
 * Output: { results: [ { id, name, default_location, default_salesman, preferred_contractor_id, preferred_contractor_name, last_job_number } ... ] }
 */
include '/home/freeman/job_scheduler.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 1) {
  echo json_encode(['results' => []]); exit;
}

// Prepare LIKE with wildcards safely
$like = '%' . $mysqli->real_escape_string($q) . '%';

$sql = "SELECT c.id, c.name, c.default_location, c.default_salesman, c.preferred_contractor_id, c.last_job_number,
               ct.name AS preferred_contractor_name
        FROM customers c
        LEFT JOIN contractors ct ON ct.id = c.preferred_contractor_id
        WHERE c.name LIKE '{$like}'
        ORDER BY c.name ASC
        LIMIT 12";
$res = $mysqli->query($sql);

$out = [];
while ($r = $res->fetch_assoc()) {
  $out[] = [
    'id' => (int)$r['id'],
    'name' => $r['name'],
    'default_location' => $r['default_location'],
    'default_salesman' => $r['default_salesman'],
    'preferred_contractor_id' => $r['preferred_contractor_id'] ? (int)$r['preferred_contractor_id'] : null,
    'preferred_contractor_name' => $r['preferred_contractor_name'],
    'last_job_number' => $r['last_job_number']
  ];
}
echo json_encode(['results' => $out], JSON_UNESCAPED_UNICODE);
