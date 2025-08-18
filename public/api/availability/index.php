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

error_log(sprintf('availability index request: employee_id=%d, week_start=%s', $eid, $weekStart));

if ($eid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_params']);
    exit;
}

$ws = new DateTimeImmutable($weekStart);
$we = $ws->modify('+6 days')->format('Y-m-d');

// Helper to detect numeric day column
function dow_is_int(PDO $pdo): bool {
    static $isInt = null;
    if ($isInt !== null) return $isInt;
    try {
        $row = $pdo->query("SHOW COLUMNS FROM employee_availability LIKE 'day_of_week'")
            ->fetch(PDO::FETCH_ASSOC);
        $type = strtolower((string)($row['Type'] ?? ''));
        $isInt = str_contains($type, 'int');
    } catch (Throwable $e) {
        $isInt = false;
    }
    return $isInt;
}

$st = $pdo->prepare("SELECT id, day_of_week, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time FROM employee_availability WHERE employee_id = :eid ORDER BY day_of_week, start_time");
$st->execute([':eid' => $eid]);
$avail = $st->fetchAll(PDO::FETCH_ASSOC);

$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
if (dow_is_int($pdo)) {
    foreach ($avail as &$a) {
        $v = $a['day_of_week'] ?? '';
        if (is_numeric($v)) {
            $a['day_of_week'] = $dayNames[((int)$v)%7];
        }
    }
    unset($a);
}

// Ensure Monday→Sunday order
$order = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
usort($avail, static function($a, $b) use ($order) {
    $ad = array_search($a['day_of_week'], $order, true);
    $bd = array_search($b['day_of_week'], $order, true);
    $ad = $ad === false ? 99 : $ad;
    $bd = $bd === false ? 99 : $bd;
    if ($ad === $bd) {
        return strcmp($a['start_time'] ?? '', $b['start_time'] ?? '');
    }
    return $ad <=> $bd;
});

// Overrides within week.  Include day_of_week so the UI can highlight affected days
$st2 = $pdo->prepare(
    "SELECT id, date, DATE_FORMAT(date,'%W') AS day_of_week, status, " .
      "DATE_FORMAT(start_time,'%H:%i') AS start_time, " .
      "DATE_FORMAT(end_time,'%H:%i') AS end_time, type, reason " .
    "FROM employee_availability_overrides " .
    "WHERE employee_id = :eid AND date BETWEEN :ws AND :we " .
    "ORDER BY date, start_time"
);
$st2->execute([':eid' => $eid, ':ws' => $ws->format('Y-m-d'), ':we' => $we]);
$over = $st2->fetchAll(PDO::FETCH_ASSOC);

$response = ['ok' => true, 'availability' => $avail, 'overrides' => $over];
if (empty($avail)) {
    $response['message'] = 'no_records';
}
echo json_encode($response, JSON_UNESCAPED_UNICODE);

