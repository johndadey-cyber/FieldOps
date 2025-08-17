<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

// Centralized logging so deploys can inspect failures.
$__availabilityLogFile = __DIR__ . '/../logs/availability_error.log';
$logException = static function (Throwable $e) use ($__availabilityLogFile): void {
    $msg = sprintf(
        "[%s] %s in %s:%d\n%s\n",
        date('c'),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($msg, 3, $__availabilityLogFile);
};
register_shutdown_function(function () use ($logException) {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $logException(new ErrorException($err['message'], 0, $err['type'], $err['file'], $err['line']));
    }
});

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
// Availability overrides supersede recurring windows.
// Check for overlap against all selected days.
/**
 * @param list<string> $days
 */
function has_overlap(PDO $pdo, int $employeeId, array $days, string $start, string $end, ?int $excludeId = null): bool {
    if ($days === []) return false;
    $placeholders = [];
    $params = [':eid'=>$employeeId, ':st'=>$start, ':et'=>$end];
    foreach ($days as $idx => $d) {
        $ph = ':d' . $idx;
        $placeholders[] = $ph;
        $params[$ph] = $d;
    }
    $sql = "SELECT COUNT(*) AS cnt FROM employee_availability WHERE employee_id = :eid AND day_of_week IN (" . implode(',', $placeholders) . ") AND NOT (end_time <= :st OR start_time >= :et)";
    if ($excludeId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
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

/**
 * @param list<string> $days
 * @return list<string>
 */
function normalize_days(PDO $pdo, array $days): array {
    if ($days === []) return $days;
    if (!dow_is_int($pdo)) return array_map('strval', $days);
    $map = [
        'Sunday'=>0,
        'Monday'=>1,
        'Tuesday'=>2,
        'Wednesday'=>3,
        'Thursday'=>4,
        'Friday'=>5,
        'Saturday'=>6,
    ];
    $out = [];
    foreach ($days as $d) {
        if (isset($map[$d])) {
            $out[] = (string)$map[$d];
        } elseif (is_numeric($d)) {
            $out[] = (string)((int)$d);
        }
    }
    return $out;
}

try {
    $pdo = getPDO();

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($method !== 'POST') { json_out(['ok'=>false,'error'=>'Method not allowed'], 405); }

    $token = (string)($_POST['csrf_token'] ?? '');
    if (!csrf_verify($token)) { json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }

    $employeeId = isset($_POST['employee_id']) ? (int)$_POST['employee_id'] : 0;
    $dayInput   = $_POST['day_of_week'] ?? [];
    $days       = is_array($dayInput) ? $dayInput : [(string)$dayInput];
    $start      = norm_time((string)($_POST['start_time'] ?? ''));
    $end        = norm_time((string)($_POST['end_time'] ?? ''));

    $validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
    $errors = [];
    if ($employeeId <= 0) $errors[] = 'employee_id is required';
    if ($days === []) $errors[] = 'day_of_week invalid';
    foreach ($days as $d) {
        if (!in_array($d, $validDays, true)) { $errors[] = 'day_of_week invalid'; break; }
    }
    if ($start >= $end) $errors[] = 'end_time must be after start_time';

    // Normalize days for DB column type
    $days = normalize_days($pdo, $days);

    if (!$errors && has_overlap($pdo, $employeeId, $days, $start, $end, null)) {
        $errors[] = 'Window overlaps an existing window for selected day(s). Overrides take precedence.';
    }

    if ($errors) { json_out(['ok'=>false,'errors'=>$errors], 422); }

    $ins = $pdo->prepare(
        "INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid, :dow, :st, :et)"
    );
    foreach ($days as $d) {
        $ins->execute([':eid'=>$employeeId, ':dow'=>$d, ':st'=>$start, ':et'=>$end]);
    }
    $newId = (int)$pdo->lastInsertId();
    try {
        $uid = $_SESSION['user']['id'] ?? null;
        $det = json_encode(['id'=>$newId,'days'=>$days,'start'=>$start,'end'=>$end], JSON_UNESCAPED_UNICODE);
        $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
            ->execute([':eid'=>$employeeId, ':uid'=>$uid, ':act'=>'create', ':det'=>$det]);
    } catch (Throwable $e) {
        // ignore audit errors
    }
    json_out(['ok'=>true,'id'=>$newId]);
} catch (Throwable $e) {
    $logException($e);
    json_out(['ok'=>false,'error'=>'Save failed'], 500);
}
