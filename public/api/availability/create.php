<?php
declare(strict_types=1);

/**
 * POST /api/availability/create.php
 * Save or update recurring availability windows.
 * Accepts JSON body: {employee_id, day_of_week, blocks:[{start_time,end_time}], replace_ids?}
 * Legacy: start_time/end_time and id are still supported.
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
function has_overlap(PDO $pdo, int $eid, array $days, string $start, string $end, ?int $excludeId = null, ?string $startDate = null): bool {
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
    if ($startDate !== null && has_start_date($pdo)) {
        $sql .= " AND (start_date IS NULL OR start_date >= :sd)";
        $params[':sd'] = $startDate;
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
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(employee_availability)")
                ->fetchAll(PDO::FETCH_ASSOC);
            $isInt = false;
            foreach ($cols as $col) {
                if (strcasecmp($col['name'] ?? '', 'day_of_week') === 0) {
                    $type = strtolower((string)($col['type'] ?? ''));
                    $isInt = str_contains($type, 'int');
                    break;
                }
            }
        } else {
            $row = $pdo->query("SHOW COLUMNS FROM employee_availability LIKE 'day_of_week'")
                ->fetch(PDO::FETCH_ASSOC);
            $type = strtolower((string)($row['Type'] ?? ''));
            $isInt = str_contains($type, 'int');
        }
    } catch (Throwable $e) {
        $isInt = false;
    }
    return $isInt;
}

function has_start_date(PDO $pdo): bool {
    static $has = null;
    if ($has !== null) return $has;
    try {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $cols = $pdo->query("PRAGMA table_info(employee_availability)")
                ->fetchAll(PDO::FETCH_ASSOC);
            $has = false;
            foreach ($cols as $col) {
                if (strcasecmp($col['name'] ?? '', 'start_date') === 0) {
                    $has = true;
                    break;
                }
            }
        } else {
            $row = $pdo->query("SHOW COLUMNS FROM employee_availability LIKE 'start_date'")
                ->fetch(PDO::FETCH_ASSOC);
            $has = $row !== false;
        }
    } catch (Throwable $e) {
        $has = false;
    }
    return $has;
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
    csrf_log_failure_payload($raw, $data);
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'invalid_csrf']);
    exit;
}

$eid  = (int)($data['employee_id'] ?? 0);
$dayInput = $data['day_of_week'] ?? [];
$days = is_array($dayInput) ? $dayInput : [(string)$dayInput];
$blocks = [];
if (isset($data['blocks']) && is_array($data['blocks'])) {
    foreach ($data['blocks'] as $b) {
        if (is_array($b)) {
            $s = (string)($b['start_time'] ?? '');
            $e = (string)($b['end_time'] ?? '');
            $blocks[] = ['start'=>$s, 'end'=>$e];
        }
    }
} else {
    $start= (string)($data['start_time'] ?? '');
    $end  = (string)($data['end_time'] ?? '');
    $blocks[] = ['start'=>$start, 'end'=>$end];
}
$replaceIds = [];
if (!empty($data['replace_ids']) && is_array($data['replace_ids'])) {
    foreach ($data['replace_ids'] as $rid) {
        $rid = (int)$rid; if ($rid > 0) $replaceIds[] = $rid;
    }
}
$id   = isset($data['id']) ? (int)$data['id'] : 0; // backward compat
$startDate = (string)($data['start_date'] ?? '');

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday','0','1','2','3','4','5','6'];
$err = [];
if ($eid <= 0) $err[] = 'employee_id';
if ($days === []) $err[] = 'day_of_week';
foreach ($days as $d) {
    if (!in_array($d, $validDays, true)) { $err[] = 'day_of_week'; break; }
}
if ($blocks === []) $err[] = 'blocks';
foreach ($blocks as $b) {
    if (!preg_match('/^\d{2}:\d{2}$/', $b['start'])) $err[] = 'start_time';
    if (!preg_match('/^\d{2}:\d{2}$/', $b['end'])) $err[] = 'end_time';
}
usort($blocks, function($a,$b){ return strcmp($a['start'], $b['start']); });
for ($i=0; $i<count($blocks); $i++) {
    $s = $blocks[$i]['start'];
    $e = $blocks[$i]['end'];
    if ($s >= $e) { $err[] = 'range'; break; }
    if ($i > 0 && $blocks[$i-1]['end'] > $s) { $err[] = 'overlap'; break; }
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate)) $err[] = 'start_date';

if ($err) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>'validation','errors'=>$err,'message'=>'Invalid input.']);
    exit;
}
// Normalize day values depending on column type
$days = normalize_days($pdo, $days);
if ($id > 0 && $replaceIds === []) { $replaceIds = [$id]; }

$blocksUtc = array_map(fn($b) => ['start'=>$b['start'] . ':00','end'=>$b['end'] . ':00'], $blocks);

try {
    $pdo->beginTransaction();
    if ($replaceIds) {
        $in = implode(',', array_fill(0, count($replaceIds), '?'));
        $params = array_merge([$eid], $replaceIds);
        $pdo->prepare("DELETE FROM employee_availability WHERE employee_id=? AND id IN ($in)")->execute($params);
    }
    foreach ($blocksUtc as $b) {
        if (has_overlap($pdo, $eid, $days, $b['start'], $b['end'], null, $startDate)) {
            $pdo->rollBack();
            http_response_code(409);
            echo json_encode(['ok'=>false,'error'=>'overlap','message'=>'Window overlaps existing recurring availability for selected day(s). Overrides take precedence.','days'=>$days]);
            exit;
        }
    }
    $ids = [];
    $hasStart = has_start_date($pdo);
    if ($hasStart) {
        $stIns = $pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time, start_date) VALUES (:eid,:dow,:st,:et,:sd)");
    } else {
        $stIns = $pdo->prepare("INSERT INTO employee_availability (employee_id, day_of_week, start_time, end_time) VALUES (:eid,:dow,:st,:et)");
    }
    foreach ($days as $d) {
        foreach ($blocksUtc as $b) {
            $params = [':eid'=>$eid,':dow'=>$d,':st'=>$b['start'],':et'=>$b['end']];
            if ($hasStart) { $params[':sd'] = $startDate; }
            $stIns->execute($params);
            $ids[] = (int)$pdo->lastInsertId();
        }
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
    $det = json_encode(['ids'=>$ids,'days'=>$days,'blocks'=>$blocks,'start_date'=>$startDate], JSON_UNESCAPED_UNICODE);
    $pdo->prepare('INSERT INTO availability_audit (employee_id, user_id, action, details) VALUES (:eid,:uid,:act,:det)')
        ->execute([':eid'=>$eid, ':uid'=>$uid, ':act'=>'create', ':det'=>$det]);
} catch (Throwable $e) {
    // ignore audit errors
}
$idOut = $ids[0] ?? 0;
echo json_encode(['ok'=>true,'id'=>$idOut,'ids'=>$ids]);

