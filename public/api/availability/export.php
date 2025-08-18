<?php
declare(strict_types=1);

/**
 * GET /api/availability/export.php
 * Output CSV of recurring availability and overrides for an employee and week.
 * Params: employee_id (int), week_start (Y-m-d)
 */

require_once __DIR__ . '/../../../config/database.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/**
 * Determine if the employee_availability_overrides table has a `type` column.
 */
function overrides_have_type(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) {
        return $has;
    }
    try {
        $row = $pdo->query("SHOW COLUMNS FROM employee_availability_overrides LIKE 'type'")
            ->fetch(PDO::FETCH_ASSOC);
        $has = $row !== false;
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
}

$eid = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;
$weekStart = isset($_GET['week_start']) ? (string)$_GET['week_start'] : '';

if ($eid <= 0 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $weekStart)) {
    http_response_code(400);
    echo 'invalid_params';
    exit;
}

$ws = new DateTimeImmutable($weekStart);
$we = $ws->modify('+6 days')->format('Y-m-d');

// Employee name
$stEmp = $pdo->prepare("SELECT CONCAT(first_name,' ',last_name) AS name FROM employees WHERE id = :id");
$stEmp->execute([':id' => $eid]);
$empName = (string)$stEmp->fetchColumn();
if ($empName === '') {
    http_response_code(404);
    echo 'not_found';
    exit;
}

// Recurring availability
$dayOrderSql = "FIELD(day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')";
$st = $pdo->prepare("SELECT day_of_week, DATE_FORMAT(start_time,'%H:%i') AS start_time, DATE_FORMAT(end_time,'%H:%i') AS end_time FROM employee_availability WHERE employee_id=:eid ORDER BY {$dayOrderSql}, start_time");
$st->execute([':eid' => $eid]);
$avail = $st->fetchAll(PDO::FETCH_ASSOC);

// Overrides within week range
$typeSql = overrides_have_type($pdo) ? 'type' : "'CUSTOM' AS type";
$st2 = $pdo->prepare(
    "SELECT date, DATE_FORMAT(start_time,'%H:%i') AS start_time, " .
    "DATE_FORMAT(end_time,'%H:%i') AS end_time, status, {$typeSql}, reason " .
    "FROM employee_availability_overrides " .
    "WHERE employee_id=:eid AND date BETWEEN :ws AND :we ORDER BY date, start_time"
);
$st2->execute([':eid' => $eid, ':ws' => $ws->format('Y-m-d'), ':we' => $we]);
$overrides = $st2->fetchAll(PDO::FETCH_ASSOC);

// CSV helper
$esc = static function (?string $v): string {
    $v = (string)$v;
    $v = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], $v);
    return '"' . $v . '"';
};

  $lines = ['employee,day,start,end,status,type,reason'];
foreach ($avail as $a) {
    $lines[] = implode(',', [
        $esc($empName),
        $esc($a['day_of_week'] ?? ''),
        $esc($a['start_time'] ?? ''),
          $esc($a['end_time'] ?? ''),
          $esc(''),
          $esc(''),
          $esc(''),
    ]);
}

foreach ($overrides as $ov) {
    $lines[] = implode(',', [
        $esc($empName),
        $esc($ov['date'] ?? ''),
        $esc($ov['start_time'] ?? ''),
        $esc($ov['end_time'] ?? ''),
          $esc($ov['status'] ?? ''),
          $esc($ov['type'] ?? ''),
          $esc($ov['reason'] ?? ''),
    ]);
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="availability.csv"');
echo implode("\n", $lines);
