<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';



require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/AssignmentEngine.php';

header('Content-Type: application/json; charset=utf-8');

$pdo = getPDO();

$jobId     = (int)($_GET['job_id'] ?? 0);
$availOnly = (int)($_GET['available_only'] ?? 0) === 1;
$skill     = trim((string)($_GET['skill'] ?? ''));
$sort      = trim((string)($_GET['sort'] ?? 'name'));

if ($jobId <= 0) {
    echo json_encode(['ok'=>false,'error'=>'Missing job_id']);
    exit;
}

$j = $pdo->prepare("
  SELECT j.id, j.customer_id, j.description, j.scheduled_date, j.scheduled_time,
         COALESCE(j.duration_minutes,0) AS duration_minutes,
         c.first_name, c.last_name, c.latitude AS cust_lat, c.longitude AS cust_lon
  FROM jobs j
  JOIN customers c ON c.id = j.customer_id
  WHERE j.id = :id
");
$j->execute([':id' => $jobId]);
$job = $j->fetch(PDO::FETCH_ASSOC);
if (!$job) {
    echo json_encode(['ok'=>false,'error'=>'Job not found']);
    exit;
}

// Currently assigned employees (singular table)
$assignedMap = [];
$qa = $pdo->prepare("SELECT employee_id FROM job_employee_assignment WHERE job_id = :jid");
$qa->execute([':jid' => $jobId]);
foreach ($qa->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $assignedMap[(int)$r['employee_id']] = true;
}

// Employee list (+optional skill filter)
$whereSkill = '';
$params = [];
if ($skill !== '') {
    $whereSkill = "AND EXISTS (
        SELECT 1 FROM employee_skills es
        JOIN skills s ON s.id = es.skill_id
        WHERE es.employee_id = e.id AND LOWER(s.name) = LOWER(:skill)
    )";
    $params[':skill'] = $skill;
}

$e = $pdo->prepare("
  SELECT e.id AS employee_id,
         p.first_name, p.last_name,
         p.latitude AS emp_lat, p.longitude AS emp_lon,
         COALESCE(GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR '||'), '') AS skills
  FROM employees e
  JOIN people p ON p.id = e.person_id
  LEFT JOIN employee_skills es ON es.employee_id = e.id
  LEFT JOIN skills s ON s.id = es.skill_id
  WHERE e.is_active = 1
  $whereSkill
  GROUP BY e.id, p.first_name, p.last_name, p.latitude, p.longitude
  ORDER BY p.last_name, p.first_name
");
$e->execute($params);
$list = $e->fetchAll(PDO::FETCH_ASSOC);

// Decorate with availability/conflict + distance
$candidates = [];
foreach ($list as $emp) {
    $eid = (int)$emp['employee_id'];
    $eval = AssignmentEngine::evaluateAvailability(
        $pdo,
        $eid,
        (string)$job['scheduled_date'],
        (string)$job['scheduled_time'],
        (int)$job['duration_minutes']
    );

    $distance = null;
    if (!empty($job['cust_lat']) && !empty($job['cust_lon']) && !empty($emp['emp_lat']) && !empty($emp['emp_lon'])) {
        $distance = AssignmentEngine::haversine(
            (float)$job['cust_lat'],
            (float)$job['cust_lon'],
            (float)$emp['emp_lat'],
            (float)$emp['emp_lon'],
            true
        );
    }

    if ($availOnly && $eval['status'] === 'unavailable') {
        continue;
    }

    $candidates[] = [
        'employee_id' => $eid,
        'name'        => trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? '')),
        'skills'      => $emp['skills'] !== '' ? explode('||', (string)$emp['skills']) : [],
        'status'      => $eval['status'],   // guaranteed key
        'window'      => $eval['window'],   // array|null
        'conflict'    => $eval['conflict'], // guaranteed key
        'distance'    => $distance,
        'checked'     => isset($assignedMap[$eid]),
    ];
}

// Sorting
if ($sort === 'proximity') {
    usort($candidates, fn($a, $b) => ($a['distance'] ?? INF) <=> ($b['distance'] ?? INF));
} elseif ($sort === 'availability') {
    $rank = ['available'=>0,'partial'=>1,'unavailable'=>2];
    usort(
        $candidates,
        fn($a,$b) => ($rank[$a['status']] <=> $rank[$b['status']]) ?: strcasecmp($a['name'],$b['name'])
    );
} else {
    usort($candidates, fn($a,$b) => strcasecmp($a['name'],$b['name']));
}

echo json_encode(['ok'=>true, 'job'=>$job['id'], 'candidates'=>$candidates], JSON_UNESCAPED_SLASHES);
