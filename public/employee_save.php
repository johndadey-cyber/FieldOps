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

/**
 * Strip non-digits and validate a 10-digit US phone number.
 * Returns the digits-only string or null if invalid.
 */
function normalize_phone(string $raw): ?string {
    $digits = preg_replace('/\D+/', '', $raw);
    return is_string($digits) && preg_match('/^\d{10}$/', $digits) ? $digits : null;
}

$__empLogFile = dirname(__DIR__) . '/logs/employee_save.log';
$log = static function (string $msg) use ($__empLogFile): void {
    $line = sprintf("[%s] %s\n", date('c'), $msg);
    error_log($line, 3, $__empLogFile);
};
register_shutdown_function(function () use ($log): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $log('FATAL: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    }
});

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$log('entry method=' . $method);
if ($method !== 'POST') {
    $log('Invalid method; redirecting to employee_form.php');
    redirect_to('employee_form.php');
}

$pdo = getPDO();

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) { $log('Invalid CSRF token'); json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }
$log('CSRF token verified');

$id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$first          = trim((string)($_POST['first_name']        ?? ''));
$last           = trim((string)($_POST['last_name']         ?? ''));
$email          = trim((string)($_POST['email']             ?? ''));
$phoneRaw       = trim((string)($_POST['phone']             ?? ''));

$digits         = preg_replace('/\D+/', '', $phoneRaw);
$phone          = is_string($digits) ? $digits : '';
$log(sprintf('Phone raw input: %s, digits: %s', $phoneRaw, $phone));

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
$roleId         = (string)($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
$skills         = $_POST['skills'] ?? [];

$log('Processing id=' . $id);

$errors = [];
$addError = static function (string $msg) use (&$errors, $log): void {
    $errors[] = $msg;
    $log('VALIDATION: ' . $msg);
};

// If updating, fetch associated person_id early for duplicate checks
$personId = null;
if ($id > 0) {
    $st = $pdo->prepare('SELECT person_id FROM employees WHERE id = :id');
    $st->execute([':id' => $id]);
    $personId = $st->fetchColumn();
    if ($personId === false) {
        $addError('Employee not found.');
    } else {
        $personId = (int)$personId;
    }
}
if ($first === '' || !preg_match('/^[A-Za-z\s\'-]{1,50}$/', $first)) {
    $addError('First name is required.');
}
if ($last === '' || !preg_match('/^[A-Za-z\s\'-]{1,50}$/', $last)) {
    $addError('Last name is required.');
}
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $addError('Valid email is required.');
} else {
    $sql = 'SELECT 1 FROM people WHERE email = :em';
    $params = [':em' => $email];
    if ($personId !== null) {
        $sql .= ' AND id <> :pid';
        $params[':pid'] = $personId;
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ($st->fetchColumn()) $addError('An employee with this email already exists.');
}
if ($phone === null) {
    $addError('Valid phone is required.');
} else {
    // $phone contains only digits for comparison
    $sql = 'SELECT 1 FROM people WHERE phone = :ph';
    $params = [':ph' => $phone];
    if ($personId !== null) {
        $sql .= ' AND id <> :pid';
        $params[':pid'] = $personId;
    }
    $sql .= ' LIMIT 1';
    $st = $pdo->prepare($sql);
    $st->execute($params);
    if ($st->fetchColumn()) $addError('An employee with this phone already exists.');
}
if ($addr1 === '' || $city === '' || $state === '' || $postal === '') {
    $addError('Address is required.');
}
if ($lat === null || $lon === null) {
    $addError('Lat/Lon required.');
} elseif ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
    $addError('Invalid Lat/Lon coordinates.');
}
$validEmpTypes = ['Full-Time','Part-Time','Contractor'];
if (!in_array($employmentType, $validEmpTypes, true)) {
    $addError('Employment type invalid.');
}
if ($hireDate === '' || $hireDate > date('Y-m-d')) {
    $addError('Hire date cannot be in the future.');
}
$validStatus = ['Active','Inactive'];
if (!in_array($status, $validStatus, true)) {
    $addError('Status invalid.');
}
if ($roleId !== null) {
    $st = $pdo->prepare('SELECT 1 FROM roles WHERE id = :id');
    $st->execute([':id'=>$roleId]);
    if (!$st->fetchColumn()) {
        $addError('Role invalid.');
    }
}
// Validate skills ids (job types)
$skillIds = [];
if (is_array($skills) && count($skills) > 0) {
    $skills = array_map('intval', $skills);
    $placeholders = implode(',', array_fill(0, count($skills), '?'));
    try {
        // job types are used as skills
        $st = $pdo->prepare('SELECT id FROM job_types WHERE id IN (' . $placeholders . ')');
        $st->execute($skills);
        $skillIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $skillIds = [];
    }
    foreach ($skills as $sid) {
        if (!in_array($sid, $skillIds, true)) {
            $addError('Invalid skill selected.');
            break;
        }
    }
} else {
    $skills = [];
}

if ($errors) {
    $log('Validation failed: ' . implode('; ', $errors));
    wants_json() ? json_out(['ok'=>false,'errors'=>$errors], 422) : redirect_to('employee_form.php');
}

try {
    $log('Starting transaction');
    if (!$pdo->beginTransaction()) {
        throw new RuntimeException('Failed to start transaction');
    }

    if ($id > 0) {
        if ($personId === null) {
            $log('Person ID missing for update');
            throw new RuntimeException('Employee not found');
        }
        $log('Updating employee id=' . $id);

        $up = $pdo->prepare('UPDATE people SET first_name=:fn,last_name=:ln,email=:em,phone=:ph,address_line1=:a1,address_line2=:a2,city=:city,state=:st,postal_code=:pc,google_place_id=:pid,latitude=:lat,longitude=:lon WHERE id=:pidKey');
        $up->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId !== '' ? $placeId : null, ':lat'=>$lat, ':lon'=>$lon,
            ':pidKey'=>$personId,
        ]);

        $ue = $pdo->prepare('UPDATE employees SET employment_type=:et, hire_date=:hd, status=:st, is_active=:ia, role_id=:rid WHERE id=:id');
        $ue->execute([
            ':et'=>$employmentType,
            ':hd'=>$hireDate,
            ':st'=>$status,
            ':ia'=>$status === 'Active' ? 1 : 0,
            ':rid'=>$roleId,
            ':id'=>$id,
        ]);

        $pdo->prepare('DELETE FROM employee_skills WHERE employee_id = :eid')->execute([':eid'=>$id]);
        if (!empty($skills)) {
            // employee_skills references job_type_id
            $ins = $pdo->prepare('INSERT INTO employee_skills (employee_id, job_type_id) VALUES (:eid,:sid)');
            foreach ($skills as $sid) {
                $ins->execute([':eid'=>$id, ':sid'=>$sid]);
            }
        }

        if (!$pdo->commit()) {
            throw new RuntimeException('Commit failed');
        }
        $log('Update committed for id=' . $id);
        wants_json() ? json_out(['ok'=>true,'id'=>$id]) : redirect_to('employees.php');
    } else {
        $log('Inserting new employee');
        $ins = $pdo->prepare('INSERT INTO people (first_name,last_name,email,phone,address_line1,address_line2,city,state,postal_code,google_place_id,latitude,longitude) VALUES (:fn,:ln,:em,:ph,:a1,:a2,:city,:st,:pc,:pid,:lat,:lon)');
        $ins->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId !== '' ? $placeId : null, ':lat'=>$lat, ':lon'=>$lon,
        ]);
        $personId = (int)$pdo->lastInsertId();

        $ie = $pdo->prepare('INSERT INTO employees (person_id, employment_type, hire_date, status, is_active, role_id) VALUES (:pid,:et,:hd,:st,:ia,:rid)');
        $ie->execute([
            ':pid'=>$personId,
            ':et'=>$employmentType,
            ':hd'=>$hireDate,
            ':st'=>$status,
            ':ia'=>$status === 'Active' ? 1 : 0,
            ':rid'=>$roleId,
        ]);
        $newId = (int)$pdo->lastInsertId();

        if (!empty($skills)) {
            // employee_skills references job_type_id
            $ins = $pdo->prepare('INSERT INTO employee_skills (employee_id, job_type_id) VALUES (:eid,:sid)');
            foreach ($skills as $sid) {
                $ins->execute([':eid'=>$newId, ':sid'=>$sid]);
            }
        }

        if (!$pdo->commit()) {
            throw new RuntimeException('Commit failed');
        }
        $log('Insert committed for id=' . $newId);
        wants_json() ? json_out(['ok'=>true,'id'=>$newId]) : redirect_to('employees.php');
    }
} catch (Throwable $e) {
    $pdo->rollBack();
    $log('Exception: ' . $e->getMessage());
    error_log('[employee_save] ' . $e->getMessage());
    wants_json() ? json_out(['ok'=>false,'error'=>'Save failed'], 500) : redirect_to('employee_form.php');
}
