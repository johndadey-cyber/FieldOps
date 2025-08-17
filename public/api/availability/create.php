<?php
declare(strict_types=1);

/**
 * POST /api/availability/create.php
 * Save or update recurring availability windows.
 * Accepts JSON body: {id?, employee_id, day_of_week, start_time, end_time}
 */

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../../config/database.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
require_once __DIR__ . '/../../_csrf.php';
require_once __DIR__ . '/../../../helpers/availability_error_logger.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Overrides supersede recurring availability entries.
/**
 * @param list<string> $days
 */
function has_overlap(PDO $pdo, int $eid, array $days, string $start, string $end, ?int $excludeId = null): bool {
    if ($days === []) return false;
    $placeholders = [];
    $params = [':eid'=>$eid, ':st'=>$start, ':et'=>$end];
    foreach ($days as $i => $d) {
        $ph = ':d' . $i;
        $placeholders[] = $ph;
        $params[$ph] = $d;
    }
    $sql = "SELECT COUNT(*) AS cnt FROM employee_availability WHERE employee_id=:eid AND day_of_week IN (" . implode(',', $placeholders) . ") AND NOT (end_time <= :st OR start_time >= :et)";
    if ($excludeId !== null) {
        $sql .= " AND id <> :id";
        $params[':id'] = $excludeId;
    }
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    return ((int)($row['cnt'] ?? 0)) > 0;
}

/**
 * Determine if employee_availability.day_of_week column is numeric.
 */
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

$raw = file_get_contents('php://input');
$data = json_decode($raw ?: '[]', true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>'invalid_json']);
    exit;
}

if (!csrf_verify($data['csrf_token'] ?? null)) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
}

$eid  = (int)($data['employee_id'] ?? 0);
$dayInput = $data['day_of_week'] ?? [];
$days = is_array($dayInput) ? $dayInput : [(string)$dayInput];
$start= (string)($data['start_time'] ?? '');
$end  = (string)($data['end_time'] ?? '');
$id   = isset($data['id']) ? (int)$data['id'] : 0;

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday','0','1','2','3','4','5','6'];
$err = [];
if ($eid <= 0) $err[] = 'employee_id';
if ($days === []) $err[] = 'day_of_week';
foreach ($days as $d) {
    if (!in_array($d, $validDays, true)) { $err[] = 'day_of_week'; break; }
}
if (!preg_match('/^\d{2}:\d{2}$/', $start)) $err[] = 'start_time';
if (!preg_match('/^\d{2}:\d{2}$/', $end)) $err[] = 'end_time';
if ($start >= $end) $err[] = 'range';

if ($err) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'validation','errors'=>$err,'message'=>'Invalid input.']);
    exit;
}

$startUtc = $start . ':00';
$endUtc   = $end . ':00';

// Normalize day values depending on column type
$days = normalize_days($pdo, $days);

if (has_overlap($pdo, $eid, $days, $startUtc, $endUtc, $id > 0 ? $id : null)) {
    http_response_code(409);
    echo json_encode(['ok'=>false,'error'=>'overlap','message'=>'Window overlaps existing recurring availability for selected day(s). Overrides take precedence.','days'=>$days]);
    exit;
}


$day = $days[0] ?? null;
$ids = [];
$isUpdate = $id > 0;

try {
    $pdo->beginTransaction();
    if ($isUpdate) {
        $stUpd = $pdo->prepare("UPDATE employee_availability SET day_of_week=:dow, start_time=:st, end_time=:et WHERE id=:id AND employee_id=:eid");
        $stUpd->execute([':dow'=>$day,':st'=>$startUtc,':et'=>$endUtc,':id'=>$id,':eid'=>$eid]);
        $ids[] = $id;
        $remaining = array_slice($days, 1);
        if ($remaining) {
            $stIns = $pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid,:dow,:st,:et)");
            foreach ($remaining as $d) {
                $stIns->execute([':eid'=>$eid,':dow'=>$d,':st'=>$startUtc,':et'=>$endUtc]);
                $ids[] = (int)$pdo->lastInsertId();
            }
        }
    } else {
        $stIns = $pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid,:dow,:st,:et)");
        foreach ($days as $d) {
            $stIns->execute([':eid'=>$eid,':dow'=>$d,':st'=>$startUtc,':et'=>$endUtc]);
            $ids[] = (int)$pdo->lastInsertId();
        }
        $id = $ids[0] ?? 0;
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    availability_log_error($pdo, $eid, $data, $e);
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>'db_error','message'=>'Failed to save availability.']);
    exit;
}


try {
    $uid = $_SESSION['user']['id'] ?? null;
    $det = json_encode(['ids'=>$ids,'days'=>$days,'start'=>$start,'end'=>$end], JSON_UNESCAPED_UNICODE);
    $act = $isUpdate ? 'update' : 'create';
    $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
        ->execute([':eid'=>$eid, ':uid'=>$uid, ':act'=>$act, ':det'=>$det]);
} catch (Throwable $e) {
    // ignore audit errors
}
echo json_encode(['ok'=>true,'id'=>$id,'ids'=>$ids]);

