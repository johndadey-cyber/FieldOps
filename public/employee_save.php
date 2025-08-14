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
function redirect_to(string $path): void {
    header('Location: ' . $path);
    exit;
}

$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { json_out(['ok'=>false,'error'=>'Method not allowed'], 405); }

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) { json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }

$id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$first          = trim((string)($_POST['first_name']        ?? ''));
$last           = trim((string)($_POST['last_name']         ?? ''));
$email          = trim((string)($_POST['email']             ?? ''));
$phone          = trim((string)($_POST['phone']             ?? ''));
$addr1          = trim((string)($_POST['address_line1']     ?? ''));
$addr2          = trim((string)($_POST['address_line2']     ?? ''));
$city           = trim((string)($_POST['city']              ?? ''));
$state          = trim((string)($_POST['state']             ?? ''));
$postal         = trim((string)($_POST['postal_code']       ?? ''));
$placeId        = trim((string)($_POST['google_place_id']   ?? ''));
$lat            = (string)($_POST['home_address_lat'] ?? '') !== '' ? (float)$_POST['home_address_lat'] : null;
$lon            = (string)($_POST['home_address_lon'] ?? '') !== '' ? (float)$_POST['home_address_lon'] : null;
$employmentType = trim((string)($_POST['employment_type']   ?? ''));
$hireDate       = trim((string)($_POST['hire_date']         ?? ''));
$status         = trim((string)($_POST['status']            ?? ''));
$notes          = trim((string)($_POST['notes']             ?? ''));
$skills         = $_POST['skills'] ?? [];

$errors = [];
if ($first === '' || !preg_match('/^[A-Za-z\s\'-]{1,50}$/', $first)) {
    $errors[] = 'First name is required.';
}
if ($last === '' || !preg_match('/^[A-Za-z\s\'-]{1,50}$/', $last)) {
    $errors[] = 'Last name is required.';
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
} else {
    $st = $pdo->prepare('SELECT 1 FROM people WHERE email = :em LIMIT 1');
    $st->execute([':em'=>$email]);
    if ($st->fetchColumn()) $errors[] = 'An employee with this email already exists.';
}
if ($phone === '' || !preg_match('/^\(\d{3}\) \d{3}-\d{4}$/', $phone)) {
    $errors[] = 'Valid phone is required.';
} else {
    $st = $pdo->prepare('SELECT 1 FROM people WHERE phone = :ph LIMIT 1');
    $st->execute([':ph'=>$phone]);
    if ($st->fetchColumn()) $errors[] = 'An employee with this phone already exists.';
}
if ($addr1 === '' || $city === '' || $state === '' || $postal === '') {
    $errors[] = 'Address is required.';
}
if ($lat === null || $lon === null) {
    $errors[] = 'Lat/Lon required.';
}
$validEmpTypes = ['Full-Time','Part-Time','Contractor'];
if (!in_array($employmentType, $validEmpTypes, true)) {
    $errors[] = 'Employment type invalid.';
}
if ($hireDate === '' || $hireDate > date('Y-m-d')) {
    $errors[] = 'Hire date cannot be in the future.';
}
$validStatus = ['Active','Inactive'];
if (!in_array($status, $validStatus, true)) {
    $errors[] = 'Status invalid.';
}
// Validate skills ids
$skillIds = [];
if (is_array($skills) && count($skills) > 0) {
    $skills = array_map('intval', $skills);
    $placeholders = implode(',', array_fill(0, count($skills), '?'));
    try {
        $st = $pdo->prepare('SELECT id FROM skills WHERE id IN (' . $placeholders . ')');
        $st->execute($skills);
        $skillIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $skillIds = [];
    }
    foreach ($skills as $sid) {
        if (!in_array($sid, $skillIds, true)) {
            $errors[] = 'Invalid skill selected.';
            break;
        }
    }
} else {
    $skills = [];
}

if ($errors) {
    wants_json() ? json_out(['ok'=>false,'errors'=>$errors], 422) : redirect_to('employee_form.php');
}

try {
    $pdo->beginTransaction();

    if ($id > 0) {
        $st = $pdo->prepare('SELECT person_id FROM employees WHERE id = :id');
        $st->execute([':id'=>$id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Employee not found');
        $personId = (int)$row['person_id'];

        $up = $pdo->prepare('UPDATE people SET first_name=:fn,last_name=:ln,email=:em,phone=:ph,address_line1=:a1,address_line2=:a2,city=:city,state=:st,postal_code=:pc,google_place_id=:pid,latitude=:lat,longitude=:lon WHERE id=:pidKey');
        $up->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId !== '' ? $placeId : null, ':lat'=>$lat, ':lon'=>$lon,
            ':pidKey'=>$personId,
        ]);

        $ue = $pdo->prepare('UPDATE employees SET employment_type=:et, hire_date=:hd, status=:st, notes=:nt, is_active=:ia WHERE id=:id');
        $ue->execute([
            ':et'=>$employmentType,
            ':hd'=>$hireDate,
            ':st'=>$status,
            ':nt'=>$notes !== '' ? $notes : null,
            ':ia'=>$status === 'Active' ? 1 : 0,
            ':id'=>$id,
        ]);

        $pdo->prepare('DELETE FROM employee_skills WHERE employee_id = :eid')->execute([':eid'=>$id]);
        if (!empty($skills)) {
            $ins = $pdo->prepare('INSERT INTO employee_skills (employee_id, skill_id) VALUES (:eid,:sid)');
            foreach ($skills as $sid) {
                $ins->execute([':eid'=>$id, ':sid'=>$sid]);
            }
        }

        $pdo->commit();
        wants_json() ? json_out(['ok'=>true,'id'=>$id]) : redirect_to('employees.php');
    } else {
        $ins = $pdo->prepare('INSERT INTO people (first_name,last_name,email,phone,address_line1,address_line2,city,state,postal_code,google_place_id,latitude,longitude) VALUES (:fn,:ln,:em,:ph,:a1,:a2,:city,:st,:pc,:pid,:lat,:lon)');
        $ins->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId !== '' ? $placeId : null, ':lat'=>$lat, ':lon'=>$lon,
        ]);
        $personId = (int)$pdo->lastInsertId();

        $ie = $pdo->prepare('INSERT INTO employees (person_id, employment_type, hire_date, status, notes, is_active) VALUES (:pid,:et,:hd,:st,:nt,:ia)');
        $ie->execute([
            ':pid'=>$personId,
            ':et'=>$employmentType,
            ':hd'=>$hireDate,
            ':st'=>$status,
            ':nt'=>$notes !== '' ? $notes : null,
            ':ia'=>$status === 'Active' ? 1 : 0,
        ]);
        $newId = (int)$pdo->lastInsertId();

        if (!empty($skills)) {
            $ins = $pdo->prepare('INSERT INTO employee_skills (employee_id, skill_id) VALUES (:eid,:sid)');
            foreach ($skills as $sid) {
                $ins->execute([':eid'=>$newId, ':sid'=>$sid]);
            }
        }

        $pdo->commit();
        wants_json() ? json_out(['ok'=>true,'id'=>$newId]) : redirect_to('employees.php');
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[employee_save] ' . $e->getMessage());
    wants_json() ? json_out(['ok'=>false,'error'=>'Save failed'], 500) : redirect_to('employee_form.php');
}
