<?php
/**
 * GET /api/customers_search.php?q=term
 * -----------------------------------
 * Returns a small list of customers for autocomplete and their defaults:␊
 *  - default_location␊
 *  - default_salesman (with phone)
 *  - preferred_contractor_id (+ its name)␊
 *  - last_job_number␊
 *  - standard_notes␊
 *␊
 * Output: { results: [ { id, name, default_location, default_salesman, default_salesman_phone, preferred_contractor_id, preferred_contractor_name, last_job_number, standard_notes } ... ] }
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 1) {
  echo json_encode(['results' => []]); exit;
}

// Prepare LIKE with wildcards safely
$like = '%' . $mysqli->real_escape_string($q) . '%';

$sql = "SELECT c.id, c.name, c.default_location, c.default_salesman,
               s.phone AS default_salesman_phone,
               c.preferred_contractor_id, c.last_job_number,
               c.standard_notes,
               ct.name AS preferred_contractor_name
        FROM customers c
        LEFT JOIN contractors ct ON ct.id = c.preferred_contractor_id
        LEFT JOIN salesmen s ON s.name = c.default_salesman
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
    'default_salesman_phone' => $r['default_salesman_phone'],
    'preferred_contractor_id' => $r['preferred_contractor_id'] ? (int)$r['preferred_contractor_id'] : null,
    'preferred_contractor_name' => $r['preferred_contractor_name'],
    'last_job_number' => $r['last_job_number'],
    'standard_notes' => $r['standard_notes']
  ];
}
echo json_encode(['results' => $out], JSON_UNESCAPED_UNICODE);