<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $jsonQ  = isset($_GET['json']) && $_GET['json'] === '1';
    return $jsonQ || stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest';
}
/** @param array<string,mixed> $payload */
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
function norm_time(string $t): string {
    $t = trim($t);
    if ($t === '') return '00:00:00';
    if (preg_match('/^\d{2}:\d{2}$/', $t)) return $t . ':00';
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $t)) return $t;
    $ts = strtotime($t);
    if ($ts === false) return '00:00:00';
    return date('H:i:s', $ts);
}
function has_overlap(PDO $pdo, int $employeeId, string $day, string $start, string $end, ?int $excludeId = null): bool {
    $sql = "
        SELECT COUNT(*) AS cnt
        FROM employee_availability
        WHERE employee_id = :eid
          AND day_of_week = :dow
          AND NOT (end_time <= :st OR start_time >= :et)
    ";
    if ($excludeId !== null) {
        $sql .= " AND id <> :id";
    }
    $st = $pdo->prepare($sql);
    $st->bindValue(':eid', $employeeId, PDO::PARAM_INT);
    $st->bindValue(':dow', $day, PDO::PARAM_STR);
    $st->bindValue(':st', $start, PDO::PARAM_STR);
    $st->bindValue(':et', $end, PDO::PARAM_STR);
    if ($excludeId !== null) {
        $st->bindValue(':id', $excludeId, PDO::PARAM_INT);
    }
    $st->execute();
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return ((int)($row['cnt'] ?? 0)) > 0;
}

/** Determine if employee_availability.day_of_week is numeric. */
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

/** Normalize a day value based on column type. */
function normalize_day(PDO $pdo, string $day): string {
    if (!dow_is_int($pdo)) return $day;
    $map = [
        'Sunday'=>0,
        'Monday'=>1,
        'Tuesday'=>2,
        'Wednesday'=>3,
        'Thursday'=>4,
        'Friday'=>5,
        'Saturday'=>6,
    ];
    if (isset($map[$day])) return (string)$map[$day];
    if (is_numeric($day)) return (string)((int)$day);
    return $day;
}

$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['POST','PUT','PATCH'], true)) {
    json_out(['ok'=>false,'error'=>'Method not allowed'], 405);
}

$raw   = file_get_contents('php://input');
$data  = array_merge($_GET, $_POST);
$token = (string)($data['csrf_token'] ?? '');
if (!csrf_verify($token)) { csrf_log_failure_payload($raw, $data); json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }

$id         = isset($_POST['id']) ? (int)$_POST['id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : (isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0);
$day        = (string)($_POST['day_of_week'] ?? ($_GET['day_of_week'] ?? ''));
$start      = norm_time((string)($_POST['start_time'] ?? ($_GET['start_time'] ?? '')));
$end        = norm_time((string)($_POST['end_time'] ?? ($_GET['end_time'] ?? '')));

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$errors = [];
if ($id <= 0) $errors[] = 'id is required';
if ($employeeId <= 0) $errors[] = 'employee_id is required';
if (!in_array($day, $validDays, true)) $errors[] = 'day_of_week invalid';
if ($start >= $end) $errors[] = 'end_time must be after start_time';

// Normalize day according to column type
$dayNorm = normalize_day($pdo, $day);
if (!$errors && has_overlap($pdo, $employeeId, $dayNorm, $start, $end, $id)) {
    $errors[] = 'Window overlaps an existing window for this day.';
}

if ($errors) { json_out(['ok'=>false,'errors'=>$errors], 422); }

try {
    $up = $pdo->prepare("
        UPDATE employee_availability
           SET employee_id = :eid,
               day_of_week = :dow,
               start_time  = :st,
               end_time    = :et
         WHERE id = :id
    ");
    $ok = $up->execute([
        ':eid'=>$employeeId, ':dow'=>$dayNorm, ':st'=>$start, ':et'=>$end, ':id'=>$id
    ]);
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $det = json_encode(['id'=>$id,'day'=>$day,'start'=>$start,'end'=>$end], JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
            ->execute([':eid'=>$employeeId, ':uid'=>$uid, ':act'=>'update', ':det'=>$det]);
    } catch (Throwable $e) {
        // ignore audit errors
    }
    json_out(['ok'=> (bool)$ok, 'id'=>$id]);
} catch (Throwable $e) {
    error_log('[availability_update] ' . $e->getMessage());
    json_out(['ok'=>false,'error'=>'Update failed'], 500);
}
