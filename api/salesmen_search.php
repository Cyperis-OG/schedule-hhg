<?php
/**
 * GET /api/salesmen_search.php?q=term
 * -----------------------------------
 * Returns a list of salesmen for autocomplete.
 * Each item: { id, name, phone }
 */
require_once __DIR__ . '/../config.php';
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '' || mb_strlen($q) < 1) {
  echo json_encode(['results' => []]); exit;
}
$like = '%' . $mysqli->real_escape_string($q) . '%';
$sql = "SELECT id, name, phone FROM salesmen WHERE name LIKE '{$like}' ORDER BY name ASC LIMIT 12";
$res = $mysqli->query($sql);
$out = [];
while ($r = $res->fetch_assoc()) {
  $out[] = [
    'id' => (int)$r['id'],
    'name' => $r['name'],
    'phone' => $r['phone']
  ];
}
echo json_encode(['results' => $out], JSON_UNESCAPED_UNICODE);