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

$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { json_out(['ok'=>false,'error'=>'Method not allowed'], 405); }

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) { json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }

$employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
$day        = (string)($_POST['day_of_week'] ?? '');
$start      = norm_time((string)($_POST['start_time'] ?? ''));
$end        = norm_time((string)($_POST['end_time'] ?? ''));

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
$errors = [];
if ($employeeId <= 0) $errors[] = 'employee_id is required';
if (!in_array($day, $validDays, true)) $errors[] = 'day_of_week invalid';
if ($start >= $end) $errors[] = 'end_time must be after start_time';

if (!$errors && has_overlap($pdo, $employeeId, $day, $start, $end, null)) {
    $errors[] = 'Window overlaps an existing window for this day.';
}

if ($errors) { json_out(['ok'=>false,'errors'=>$errors], 422); }

try {
    $ins = $pdo->prepare("
        INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time)
        VALUES (:eid, :dow, :st, :et)
    ");
    $ins->execute([':eid'=>$employeeId, ':dow'=>$day, ':st'=>$start, ':et'=>$end]);
    $newId = (int)$pdo->lastInsertId();
    json_out(['ok'=>true,'id'=>$newId]);
} catch (Throwable $e) {
    error_log('[availability_save] ' . $e->getMessage());
    json_out(['ok'=>false,'error'=>'Save failed'], 500);
}
