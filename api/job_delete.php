<?php
// /095/schedule-ng/api/job_delete.php
// Delete a single job day or an entire job (including all its days)
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (($_SESSION['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'forbidden']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
$job_uid = preg_replace('/[^a-fA-F0-9]/', '', $payload['job_uid'] ?? '');
$day_uid = preg_replace('/[^a-fA-F0-9]/', '', $payload['job_day_uid'] ?? '');

try {
    if ($job_uid && !$day_uid) {
        // Delete entire job and its days
        $mysqli->begin_transaction();
        $st = $mysqli->prepare('DELETE FROM job_days WHERE job_uid = ?');
        $st->bind_param('s', $job_uid);
        $st->execute();
        $st->close();
        $st = $mysqli->prepare('DELETE FROM jobs WHERE uid = ? LIMIT 1');
        $st->bind_param('s', $job_uid);
        $st->execute();
        $st->close();
        $mysqli->commit();
        echo json_encode(['ok' => true, 'mode' => 'job']);
        exit;
    }

    if ($day_uid) {
        // Delete a single day; remove job if it was the last day
        $mysqli->begin_transaction();
        $st = $mysqli->prepare('SELECT job_uid FROM job_days WHERE uid = ? LIMIT 1');
        $st->bind_param('s', $day_uid);
        $st->execute();
        $st->bind_result($juid);
        $st->fetch();
        $st->close();
        if (!$juid) {
            $mysqli->rollback();
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'not found']);
            exit;
        }
        $st = $mysqli->prepare('DELETE FROM job_days WHERE uid = ? LIMIT 1');
        $st->bind_param('s', $day_uid);
        $st->execute();
        $st->close();
        $st = $mysqli->prepare('SELECT COUNT(*) FROM job_days WHERE job_uid = ?');
        $st->bind_param('s', $juid);
        $st->execute();
        $st->bind_result($cnt);
        $st->fetch();
        $st->close();
        if ($cnt == 0) {
            $st = $mysqli->prepare('DELETE FROM jobs WHERE uid = ? LIMIT 1');
            $st->bind_param('s', $juid);
            $st->execute();
            $st->close();
        }
        $mysqli->commit();
        echo json_encode(['ok' => true, 'mode' => 'day']);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing uid']);
} catch (Throwable $e) {
    $mysqli->rollback();
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}