<?php
declare(strict_types=1);
/**
 * /public/api/assignments/eligible.php
 * Version: 2025-08-13.4
 * Purpose: Return candidate employees for assignment with availability, conflicts, skills, dayLoad, distance, qualified flag.
 * Notes:
 *  - Schema-adaptive (tolerates optional/mixed columns/tables)
 *  - Safe JSON errors
 *  - RELAXED role gating: if role is missing/unknown, or contains "tech", treat as technician; only deny known non-tech roles
 */

header('Content-Type: application/json; charset=utf-8');
@ini_set('display_errors', '0');
@ini_set('html_errors', '0');

set_error_handler(function(int $severity, string $message, string $file = '', int $line = 0) {
  if (!(error_reporting() & $severity)) return false;
  throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(function(Throwable $e) {
  http_response_code(500);
  error_log('[eligible.php] ' . $e->getMessage() . "\n" . $e->getTraceAsString());
  echo json_encode(['ok'=>false,'code'=>500,'error'=>'INTERNAL','detail'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  exit;
});
function fail(int $code, string $msg, array $extra = []): never {
  http_response_code($code);
  echo json_encode(['ok'=>false,'code'=>$code,'error'=>$msg] + $extra, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ---------- DB include (fixed path + fallbacks) ---------- */
$DB_PATHS = [
  __DIR__ . '/../../../config/database.php',
  __DIR__ . '/../../config/database.php',
  dirname(__DIR__, 3) . '/config/database.php',
];
$loadedDb = false;
foreach ($DB_PATHS as $p) { if (is_file($p)) { require $p; $loadedDb = true; break; } }
if (!$loadedDb) fail(500, 'DB_CONFIG_NOT_FOUND', ['searched'=>$DB_PATHS]);
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ---------------- Inputs ---------------- */
$jobId = isset($_GET['jobId']) && is_numeric($_GET['jobId']) ? (int)$_GET['jobId'] : 0;
if ($jobId <= 0) fail(400, 'Missing or invalid jobId');
$sortBy = $_GET['sort'] ?? 'distance';
$sortBy = in_array($sortBy, ['distance','dayload','name'], true) ? $sortBy : 'distance';
$DEBUG  = isset($_GET['debug']);

$ORG_TZ = 'America/Chicago';

/* -------------- Schema helpers -------------- */
function tableExists(PDO $pdo, string $name): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :n");
  $st->execute([':n'=>$name]);
  return (int)$st->fetchColumn() > 0;
}
function colExists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (int)$st->fetchColumn() > 0;
}

$schema = [
  'jobs'                     => tableExists($pdo,'jobs'),
  'customers'               => tableExists($pdo,'customers'),
  'employees'               => tableExists($pdo,'employees'),
  'people'                  => tableExists($pdo,'people'),
  'roles'                   => tableExists($pdo,'roles'),
  'employee_availability'   => tableExists($pdo,'employee_availability'),
  'employee_availability_overrides' => tableExists($pdo, 'employee_availability_overrides'),
  'employee_skills'         => tableExists($pdo,'employee_skills'),
  'job_types'               => tableExists($pdo,'job_types'),
  'skills'                  => tableExists($pdo,'skills'),
  'job_jobtype'             => tableExists($pdo,'job_jobtype'),
  'jobtype_skills'          => tableExists($pdo,'jobtype_skills'),
  'job_employee_assignment' => tableExists($pdo,'job_employee_assignment'),
  'job_employee'            => tableExists($pdo,'job_employee'),
];

$custLatCol = colExists($pdo,'customers','latitude') ? 'latitude' : (colExists($pdo,'customers','lat') ? 'lat' : null);
$custLonCol = colExists($pdo,'customers','longitude') ? 'longitude' : (colExists($pdo,'customers','lng') ? 'lng' : null);

$empActiveCol = colExists($pdo,'employees','is_active') ? 'is_active' : (colExists($pdo,'employees','active') ? 'active' : null);
$empRoleText  = colExists($pdo,'employees','role') ? 'role' : null;

$peopleLatCol = colExists($pdo,'people','latitude') ? 'latitude' : (colExists($pdo,'people','lat') ? 'lat' : null);
$peopleLonCol = colExists($pdo,'people','longitude') ? 'longitude' : (colExists($pdo,'people','lng') ? 'lng' : null);
$peopleEmailCol = colExists($pdo,'people','email') ? 'email' : null;
$peoplePhoneCol = colExists($pdo,'people','phone') ? 'phone' : (colExists($pdo,'people','phone_number') ? 'phone_number' : null);

/* -------------- Job + customer coords -------------- */
if (!$schema['jobs']) fail(500, 'Schema error: jobs table not found');

$jobSelect = "SELECT j.id, j.customer_id, j.description, j.scheduled_date, j.scheduled_time, j.duration_minutes";
$custSelectLat = $custLatCol ? "c.`{$custLatCol}` AS cust_lat" : "NULL AS cust_lat";
$custSelectLon = $custLonCol ? "c.`{$custLonCol}` AS cust_lon" : "NULL AS cust_lon";
$custJoin = $schema['customers'] ? "LEFT JOIN customers c ON c.id = j.customer_id" : "";

$sqlJob = "
  $jobSelect, $custSelectLat, $custSelectLon
  FROM jobs j
  $custJoin
  WHERE j.id = :jobId
  LIMIT 1";
$st = $pdo->prepare($sqlJob);
$st->execute([':jobId'=>$jobId]);
$jobRow = $st->fetch(PDO::FETCH_ASSOC);
if (!$jobRow) fail(404, 'Job not found');

$jobDate     = (string)($jobRow['scheduled_date'] ?? '');
$jobTime     = (string)($jobRow['scheduled_time'] ?? '00:00:00');
$jobDuration = (int)($jobRow['duration_minutes'] ?? 0);
$custLat     = isset($jobRow['cust_lat']) ? (float)$jobRow['cust_lat'] : null;
$custLon     = isset($jobRow['cust_lon']) ? (float)$jobRow['cust_lon'] : null;

$tzLocal     = new DateTimeZone($ORG_TZ);
$dtLocal     = new DateTime("{$jobDate} {$jobTime}", $tzLocal);
$dtLocalEnd  = (clone $dtLocal)->modify("+{$jobDuration} minutes");
$dtUtc       = (clone $dtLocal)->setTimezone(new DateTimeZone('UTC'));
$dtUtcEnd    = (clone $dtLocalEnd)->setTimezone(new DateTimeZone('UTC'));
$jobWindowLabel = sprintf('%s, %s—%s', $dtLocal->format('Y-m-d'), $dtLocal->format('H:i'), $dtLocalEnd->format('H:i'));

/* -------------- Required skills for job -------------- */
$reqIds = []; $reqNamesById = [];
if ($schema['job_jobtype'] && $schema['jobtype_skills'] && $schema['skills']) {
  $stReq = $pdo->prepare(
    "SELECT DISTINCT s.id, s.name
       FROM job_jobtype jj
       JOIN jobtype_skills jts ON jts.job_type_id = jj.job_type_id
       JOIN skills s ON s.id = jts.skill_id
       WHERE jj.job_id = :jid
       ORDER BY s.name"
  );
  $stReq->execute([':jid' => $jobId]);
  $reqRows = $stReq->fetchAll(PDO::FETCH_ASSOC);
  foreach ($reqRows as $r) {
    $sid = (int)$r['id'];
    $reqIds[] = $sid;
    $reqNamesById[$sid] = (string)$r['name'];
  }
}

/* -------------- Helpers -------------- */
function haversineKm(?float $lat1, ?float $lon1, ?float $lat2, ?float $lon2): ?float {
  if ($lat1 === null || $lon1 === null || $lat2 === null || $lon2 === null) return null;
  $R=6371.0088; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1);
  $a=sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
  return round($R * 2 * atan2(sqrt($a), sqrt(1-$a)), 1);
}
function toMinutes(string $hhmmss): int {
  [$h,$m] = array_map('intval', explode(':', $hhmmss) + [0,0,0]);
  return $h*60 + $m;
}
/** RELAXED role test: default-allow unless clearly non-tech */
function isTechnicianRole(?string $role): bool {
  $r = strtolower(trim((string)$role));
  if ($r === '') return true;                   // no role info → don't block
  if (preg_match('/tech/', $r)) return true;   // "tech", "technician", "field tech", etc.
  $nonTech = ['admin','administrator','manager','sales','dispatcher','owner','office','customer','billing','hr','qa','supervisor'];
  return !in_array($r, $nonTech, true);
}

/* -------------- Date math -------------- */
$weekdayMon1 = (int)$dtLocal->format('N');  // 1..7 (Mon=1)
$weekdayName = $dtLocal->format('l');       // Monday..Sunday
$jobStartMin = toMinutes($jobTime);
$jobEndMin   = $jobStartMin + $jobDuration;

/* -------------- Employees base + role + people -------------- */
if (!$schema['employees']) fail(500, 'Schema error: employees table not found');

$select = [
  "e.id AS emp_id",
  $empActiveCol ? "e.`{$empActiveCol}` AS is_active" : "1 AS is_active",
  "e.role_id",
  $schema['people'] ? "p.first_name" : "NULL AS first_name",
  $schema['people'] ? "p.last_name"  : "NULL AS last_name",
  $schema['people'] && $peopleEmailCol ? "p.`{$peopleEmailCol}` AS email" : "NULL AS email",
  $schema['people'] && $peoplePhoneCol ? "p.`{$peoplePhoneCol}` AS phone" : "NULL AS phone",
  $schema['people'] && $peopleLatCol ? "p.`{$peopleLatCol}` AS emp_lat" : "NULL AS emp_lat",
  $schema['people'] && $peopleLonCol ? "p.`{$peopleLonCol}` AS emp_lon" : "NULL AS emp_lon",
];
$joins = [];
if ($schema['people']) $joins[] = "LEFT JOIN people p ON p.id = e.person_id";
if ($schema['roles'] && colExists($pdo,'roles','name')) {
  $select[] = "r.name AS role_name";
  $joins[]  = "LEFT JOIN roles r ON r.id = e.role_id";
} elseif ($empRoleText) {
  $select[] = "e.`{$empRoleText}` AS role_name";
} else {
  $select[] = "NULL AS role_name";
}
$sqlEmps = "SELECT " . implode(",\n         ", $select) . "\nFROM employees e\n" . implode("\n", $joins);
$emps = $pdo->query($sqlEmps)->fetchAll(PDO::FETCH_ASSOC);

/* -------------- Skills -------------- */
$skillsByEmp = [];
if ($schema['employee_skills'] && $schema['skills']) {
  $skillRows = $pdo->query(
    "SELECT es.employee_id, s.id AS skill_id, s.name
       FROM employee_skills es
       JOIN skills s ON s.id = es.skill_id"
  )->fetchAll(PDO::FETCH_ASSOC);
  foreach ($skillRows as $s) {
    $skillsByEmp[(int)$s['employee_id']][(int)$s['skill_id']] = (string)$s['name'];
  }
}

/* -------------- Availability -------------- */
$availByEmp = [];
if ($schema['employee_availability']) {
  // accept either numeric DOW or name
  $stAvail = $pdo->prepare("
    SELECT employee_id, day_of_week, start_time, end_time
    FROM employee_availability
    WHERE day_of_week IN (:dowNum, :dowName)
  ");
  $stAvail->execute([':dowNum'=>(string)$weekdayMon1, ':dowName'=>$weekdayName]);
  $availRows = $stAvail->fetchAll(PDO::FETCH_ASSOC);
  foreach ($availRows as $a) {
    $eid = (int)$a['employee_id'];
    $availByEmp[$eid][] = ['start'=>(string)$a['start_time'],'end'=>(string)$a['end_time']];
  }
}

$overrideByEmp = [];
if (!empty($schema['employee_availability_overrides'])) {
  $stOv = $pdo->prepare("SELECT employee_id, status, start_time, end_time FROM employee_availability_overrides WHERE date = :d");
  $stOv->execute([':d'=>$jobDate]);
  $ovRows = $stOv->fetchAll(PDO::FETCH_ASSOC);
  foreach ($ovRows as $o) {
    $overrideByEmp[(int)$o['employee_id']][] = [
      'status' => (string)$o['status'],
      'start'  => (string)($o['start_time'] ?? '00:00:00'),
      'end'    => (string)($o['end_time'] ?? '23:59:59'),
    ];
  }
}

/* -------------- Assignments that day -------------- */
$assignByEmp = [];
if ($schema['job_employee_assignment'] || $schema['job_employee']) {
  $assignRows = [];
  if ($schema['job_employee_assignment']) {
    $stA = $pdo->prepare("
      SELECT jea.employee_id, jea.job_id, j.scheduled_time, j.duration_minutes
      FROM job_employee_assignment jea
      JOIN jobs j ON j.id = jea.job_id
      WHERE j.scheduled_date = :d
    ");
    $stA->execute([':d'=>$jobDate]);
    $assignRows = array_merge($assignRows, $stA->fetchAll(PDO::FETCH_ASSOC));
  }
  if ($schema['job_employee']) {
    $stB = $pdo->prepare("
      SELECT je.employee_id, je.job_id, j.scheduled_time, j.duration_minutes
      FROM job_employee je
      JOIN jobs j ON j.id = je.job_id
      WHERE j.scheduled_date = :d
    ");
    $stB->execute([':d'=>$jobDate]);
    $assignRows = array_merge($assignRows, $stB->fetchAll(PDO::FETCH_ASSOC));
  }
  foreach ($assignRows as $ar) {
    $assignByEmp[(int)$ar['employee_id']][] = [
      'jobId'=>(int)$ar['job_id'],
      't'=>(string)($ar['scheduled_time'] ?? '00:00:00'),
      'dur'=>(int)($ar['duration_minutes'] ?? 0),
    ];
  }
}

/* -------------- Build payload -------------- */
$employeesOut = [];
foreach ($emps as $eRow) {
  $empId   = (int)$eRow['emp_id'];
  $isActive= (int)($eRow['is_active'] ?? 1) === 1; // default active
  $role    = (string)($eRow['role_name'] ?? '');
  $first   = (string)($eRow['first_name'] ?? '');
  $last    = (string)($eRow['last_name'] ?? '');
  $email   = (string)($eRow['email'] ?? '');
  $phone   = (string)($eRow['phone'] ?? '');
  $empLat  = isset($eRow['emp_lat']) ? (float)$eRow['emp_lat'] : null;
  $empLon  = isset($eRow['emp_lon']) ? (float)$eRow['emp_lon'] : null;

  $empSkillsMap = $skillsByEmp[$empId] ?? [];
  ksort($empSkillsMap);
  $skillsList = array_values($empSkillsMap);

  // Required → missing
  $missing = [];
  if (!empty($reqIds)) {
    foreach ($reqIds as $rid) {
      if (!isset($empSkillsMap[$rid])) {
        $missing[] = $reqNamesById[$rid] ?? ('Skill '.$rid);
      }
    }
  }
  $flags = [];
  if (!empty($missing)) $flags[] = 'missing_required_skills';

  // Availability vs job window
  $status = 'none'; $coverStart = null; $coverEnd = null;
  $empAvailWindows = $availByEmp[$empId] ?? [];
  $ovr = $overrideByEmp[$empId] ?? [];
  if (!empty($ovr)) {
    $empAvailWindows = [];
    foreach ($ovr as $o) {
      $oStatus = strtoupper($o['status']);
      if ($oStatus === 'UNAVAILABLE') { $empAvailWindows = []; break; }
      if ($oStatus === 'AVAILABLE' || $oStatus === 'PARTIAL') {
        $empAvailWindows[] = ['start'=>$o['start'], 'end'=>$o['end']];
      }
    }
  }
  if (!empty($empAvailWindows)) {
    $jobStart = $jobStartMin; $jobEnd = $jobEndMin; $hasOverlap = false;
    foreach ($empAvailWindows as $w) {
      $aStart = toMinutes($w['start']); $aEnd = toMinutes($w['end']);
      $overlap = ($aStart < $jobEnd) && ($jobStart < $aEnd);
      $covers  = ($aStart <= $jobStart) && ($aEnd >= $jobEnd);
      if ($overlap) { $hasOverlap = true; $coverStart = $coverStart ?? max($jobStart,$aStart); $coverEnd = $coverEnd ?? min($jobEnd,$aEnd); }
      if ($covers) { $status='full'; $coverStart=$jobStart; $coverEnd=$jobEnd; break; }
    }
    if ($status !== 'full') $status = $hasOverlap ? 'partial' : 'none';
  }
  $windowOut = [
    'start' => $coverStart !== null ? sprintf('%02d:%02d', intdiv($coverStart,60), $coverStart%60) : null,
    'end'   => $coverEnd   !== null ? sprintf('%02d:%02d', intdiv($coverEnd,60),   $coverEnd%60)   : null,
  ];

  // Conflicts & dayLoad
  $conflicts = []; $dayAssgns = $assignByEmp[$empId] ?? [];
  foreach ($dayAssgns as $as) {
    $otherId = (int)$as['jobId']; if ($otherId === $jobId) continue;
    $oStart = toMinutes((string)$as['t']); $oEnd = $oStart + (int)$as['dur'];
    $overlap = ($oStart < $jobEndMin) && ($jobStartMin < $oEnd);
    if ($overlap) $conflicts[] = [
      'jobId'=>$otherId,
      'start'=>sprintf('%02d:%02d', intdiv($oStart,60), $oStart%60),
      'end'  =>sprintf('%02d:%02d', intdiv($oEnd,60),   $oEnd%60),
    ];
  }
  $dayLoad = 0; foreach ($dayAssgns as $as) if ((int)$as['jobId'] !== $jobId) $dayLoad++;

  // Distance
  $distanceKm = haversineKm($empLat, $empLon, $custLat, $custLon);

  // Qualified (relaxed role test + Alex’s other rules)
  $isTech     = isTechnicianRole($role);
  $skillsOK   = empty($reqIds) || empty($missing);
  $hasConflict= !empty($conflicts);
  $isQualified= $isActive && $isTech && $skillsOK && ($status === 'full') && !$hasConflict;

  $employeesOut[] = [
    'id'            => $empId,
    'first_name'    => $first,
    'last_name'     => $last,
    'qualified'     => $isQualified,
    'skills'        => $skillsList,
    'missing_required_skills' => $missing,
    'flags'        => $flags,
    'availability'  => [ 'status'=>$status, 'window'=>$windowOut ],
    'conflicts'     => $conflicts,
    'dayLoad'       => $dayLoad,
    'distanceKm'    => $distanceKm,
    'meta'          => [ 'email'=>$email, 'phone'=>$phone ],
  ];
}

/* -------------- Sorting -------------- */
usort($employeesOut, function($a, $b) use ($sortBy) {
  $nameA = strtolower(trim(($a['last_name'] ?? '').' '.($a['first_name'] ?? '')));
  $nameB = strtolower(trim(($b['last_name'] ?? '').' '.($b['first_name'] ?? '')));
  if ($sortBy === 'name') return $nameA <=> $nameB;
  if ($sortBy === 'dayload') {
    $dl = ($a['dayLoad'] ?? 0) <=> ($b['dayLoad'] ?? 0);
    if ($dl !== 0) return $dl;
    $da = $a['distanceKm']; $db = $b['distanceKm'];
    if ($da === null && $db !== null) return 1;
    if ($db === null && $da !== null) return -1;
    if ($da !== null && $db !== null && $da != $db) return $da <=> $db;
    return $nameA <=> $nameB;
  }
  // distance default
  $da = $a['distanceKm']; $db = $b['distanceKm'];
  if ($da === null && $db !== null) return 1;
  if ($db === null && $da !== null) return -1;
  if ($da !== null && $db !== null && $da != $db) return $da <=> $db;
  $dl = ($a['dayLoad'] ?? 0) <=> ($b['dayLoad'] ?? 0);
  if ($dl !== 0) return $dl;
  return $nameA <=> $nameB;
});

/* -------------- Output -------------- */
$jobOut = [
  'id'                   => (int)$jobRow['id'],
  'description'          => (string)($jobRow['description'] ?? ''),
  'scheduledDate'        => $dtLocal->format('Y-m-d'),
  'timezone'             => $ORG_TZ,
  'start'                => $dtUtc->format(DateTimeInterface::ATOM),
  'end'                  => $dtUtcEnd->format(DateTimeInterface::ATOM),
  'start_local'          => $dtLocal->format(DateTimeInterface::ATOM),
  'end_local'            => $dtLocalEnd->format(DateTimeInterface::ATOM),
  'windowLabel'          => $jobWindowLabel,
  'requiredSkillIds'   => array_values($reqIds),
  'requiredSkillNames' => array_values(array_map(fn($id)=>$reqNamesById[$id] ?? ('Skill '.$id), $reqIds)),
];

$out = [
  'ok'         => true,
  'job'        => $jobOut,
  'employees'  => $employeesOut,
  'sort'       => ['by'=>$sortBy,'order'=>'asc'],
  'paramsEcho' => ['serverFiltering'=>'none'],
];
if ($DEBUG) {
  $out['debug'] = [
    'schema' => $schema,
    'columns' => [
      'customers' => ['lat'=>$custLatCol,'lon'=>$custLonCol],
      'employees' => ['is_active'=>$empActiveCol,'role_text'=>$empRoleText],
      'people'    => ['lat'=>$peopleLatCol,'lon'=>$peopleLonCol,'email'=>$peopleEmailCol,'phone'=>$peoplePhoneCol],
    ],
    'jobId' => $jobId,
  ];
}
echo json_encode($out, JSON_UNESCAPED_UNICODE);
