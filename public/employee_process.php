<?php
// /public/employee_process.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';



if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../config/database.php';
$pdo = getPDO();

function redirectWithFlash(string $to, string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header("Location: {$to}");
    exit;
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['create','update'], true)) {
    redirectWithFlash('employees.php', 'danger', 'Invalid action.');
}

if (!isset($_SESSION['csrf_token'], $_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    redirectWithFlash('employees.php', 'danger', 'Security token invalid. Please try again.');
}

$id          = (int)($_POST['id'] ?? 0);           // employee.id for update
$first_name  = trim((string)($_POST['first_name'] ?? ''));
$last_name   = trim((string)($_POST['last_name'] ?? ''));
$email       = trim((string)($_POST['email'] ?? ''));
$phone       = trim((string)($_POST['phone'] ?? ''));
$addr        = trim((string)($_POST['address_line1'] ?? ''));
$city        = trim((string)($_POST['city'] ?? ''));
$state       = trim((string)($_POST['state'] ?? ''));
$postal      = trim((string)($_POST['postal_code'] ?? ''));
$lat         = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
$lon         = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;
$placeId     = trim((string)($_POST['google_place_id'] ?? ''));
$employment_type = trim((string)($_POST['employment_type'] ?? ''));
$hire_date       = trim((string)($_POST['hire_date'] ?? ''));
$status          = trim((string)($_POST['status'] ?? 'Active'));
$role_id         = (string)($_POST['role_id'] ?? '') !== '' ? (int)$_POST['role_id'] : null;
$is_active       = $status === 'Active' ? 1 : 0;
$skills          = $_POST['skills'] ?? [];           // array of skill_id

$errors = [];
if ($first_name === '') $errors[] = 'First name is required.';
if ($last_name === '')  $errors[] = 'Last name is required.';
if ($hire_date === '')  $errors[] = 'Hire date is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is invalid.';
$validEmpTypes = ['Full-Time','Part-Time','Contractor'];
if (!in_array($employment_type, $validEmpTypes, true)) {
    $errors[] = 'Employment type invalid.';
}
$validStatus = ['Active','Inactive'];
if (!in_array($status, $validStatus, true)) {
    $errors[] = 'Status invalid.';
}
if ($role_id !== null) {
    $st = $pdo->prepare('SELECT 1 FROM roles WHERE id = :id');
    $st->execute([':id'=>$role_id]);
    if (!$st->fetchColumn()) {
        $errors[] = 'Role invalid.';
    }
}

// Validate skill ids
$validSkillIds = [];
if (is_array($skills) && count($skills) > 0) {
    $skills = array_map('intval', $skills);
    $placeholders = implode(',', array_fill(0, count($skills), '?'));
    try {
        $st = $pdo->prepare('SELECT id FROM skills WHERE id IN (' . $placeholders . ')');
        $st->execute($skills);
        $validSkillIds = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (Throwable $e) {
        $validSkillIds = [];
    }
    foreach ($skills as $sid) {
        if (!in_array($sid, $validSkillIds, true)) {
            $errors[] = 'Invalid skill selected.';
            break;
        }
    }
} else {
    $skills = [];
}

if ($errors) {
    redirectWithFlash(($action === 'create' ? 'employee_form.php' : "edit_employee.php?id={$id}"), 'danger', implode(' ', $errors));
}

try {
    $pdo->beginTransaction();

    if ($action === 'create') {
        // Insert into people
        $p = $pdo->prepare("
            INSERT INTO people (first_name, last_name, email, phone, address, city, state, postal_code, latitude, longitude, google_place_id)
            VALUES (:fn, :ln, :email, :phone, :addr, :city, :state, :postal, :lat, :lon, :place)
        ");
        $p->execute([
            ':fn'    => $first_name, ':ln' => $last_name,
            ':email' => $email !== '' ? $email : null,
            ':phone' => $phone !== '' ? $phone : null,
            ':addr'  => $addr !== '' ? $addr : null,
            ':city'  => $city !== '' ? $city : null,
            ':state' => $state !== '' ? $state : null,
            ':postal'=> $postal !== '' ? $postal : null,
            ':lat'   => $lat, ':lon' => $lon, ':place' => $placeId !== '' ? $placeId : null,
        ]);
        $personId = (int)$pdo->lastInsertId();

        // Insert employee
        $e = $pdo->prepare("INSERT INTO employees (person_id, employment_type, hire_date, status, is_active, role_id) VALUES (:pid, :et, :hd, :st, :act, :rid)");
        $e->execute([':pid' => $personId, ':et'=>$employment_type, ':hd' => $hire_date, ':st'=>$status, ':act' => $is_active, ':rid'=>$role_id]);
        $employeeId = (int)$pdo->lastInsertId();

        // Skills
        if (is_array($skills) && !empty($skills)) {
            $ins = $pdo->prepare("INSERT INTO employee_skills (employee_id, skill_id) VALUES (:eid, :sid)");
            foreach ($skills as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) $ins->execute([':eid' => $employeeId, ':sid' => $sid]);
            }
        }

    } else {
        if ($id <= 0) throw new RuntimeException('Missing employee ID.');

        // Find person_id from employee
        $q = $pdo->prepare("SELECT person_id FROM employees WHERE id = :id");
        $q->execute([':id' => $id]);
        $row = $q->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('Employee not found.');
        $personId = (int)$row['person_id'];

        // Update people
        $u = $pdo->prepare("
            UPDATE people
            SET first_name = :fn, last_name = :ln, email = :email, phone = :phone,
                address = :addr, city = :city, state = :state, postal_code = :postal,
                latitude = :lat, longitude = :lon, google_place_id = :place
            WHERE id = :pid
        ");
        $u->execute([
            ':fn' => $first_name, ':ln' => $last_name, ':email' => $email !== '' ? $email : null, ':phone' => $phone !== '' ? $phone : null,
            ':addr'=> $addr !== '' ? $addr : null, ':city'=> $city !== '' ? $city : null, ':state'=> $state !== '' ? $state : null,
            ':postal'=> $postal !== '' ? $postal : null, ':lat'=> $lat, ':lon'=> $lon, ':place'=> $placeId !== '' ? $placeId : null,
            ':pid'=> $personId,
        ]);

        // Update employee row
        $eu = $pdo->prepare("UPDATE employees SET employment_type=:et, hire_date = :hd, status=:st, is_active = :act, role_id=:rid WHERE id = :id");
        $eu->execute([':et'=>$employment_type, ':hd' => $hire_date, ':st'=>$status, ':act' => $is_active, ':rid'=>$role_id, ':id' => $id]);

        // Replace skills
        $pdo->prepare("DELETE FROM employee_skills WHERE employee_id = :eid")->execute([':eid' => $id]);
        if (is_array($skills) && !empty($skills)) {
            $ins = $pdo->prepare("INSERT INTO employee_skills (employee_id, skill_id) VALUES (:eid, :sid)");
            foreach ($skills as $sid) {
                $sid = (int)$sid;
                if ($sid > 0) $ins->execute([':eid' => $id, ':sid' => $sid]);
            }
        }
    }

    $pdo->commit();
    redirectWithFlash('employees.php', 'success', 'Employee saved.');
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[employee_process] '.$e->getMessage());
    redirectWithFlash('employees.php', 'danger', 'Employee save failed. Please try again.');
}
