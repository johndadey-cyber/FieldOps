<?php
declare(strict_types=1);

/**
 * GET /api/availability/index.php
 * Return recurring availability and overrides for a given employee and week.
 * Params: employee_id (int), week_start (Y-m-d)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../config/database.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$weekStart = isset($_GET['week_start']) ? (string)$_GET['week_start'] : '';

if ($eid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_params']);
    exit;
}

$ws = new DateTimeImmutable($weekStart);
$we = $ws->modify('+6 days')->format('Y-m-d');

// Recurring availability ordered Monday→Sunday then by start time
$dayOrderSql = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$st = $pdo->prepare("SELECT id, day_of_week, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time FROM employee_availability WHERE employee_id = :eid ORDER BY {$dayOrderSql}, start_time");
$st->execute([':eid' => $eid]);
$avail = $st->fetchAll(PDO::FETCH_ASSOC);

// Overrides within week
$st2 = $pdo->prepare("SELECT id, date, status, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time, reason FROM employee_availability_overrides WHERE employee_id = :eid AND date BETWEEN :ws AND :we ORDER BY date, start_time");
$st2->execute([':eid' => $eid, ':ws' => $ws->format('Y-m-d'), ':we' => $we]);
$over = $st2->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['ok' => true, 'availability' => $avail, 'overrides' => $over], JSON_UNESCAPED_UNICODE);

