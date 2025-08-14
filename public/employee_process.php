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
$hire_date   = trim((string)($_POST['hire_date'] ?? ''));
$is_active   = isset($_POST['is_active']) ? 1 : 0;
$skills      = $_POST['skills'] ?? [];           // array of job_type_id

$errors = [];
if ($first_name === '') $errors[] = 'First name is required.';
if ($last_name === '')  $errors[] = 'Last name is required.';
if ($hire_date === '')  $errors[] = 'Hire date is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is invalid.';

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
        $e = $pdo->prepare("INSERT INTO employees (person_id, hire_date, is_active) VALUES (:pid, :hd, :act)");
        $e->execute([':pid' => $personId, ':hd' => $hire_date, ':act' => $is_active]);
        $employeeId = (int)$pdo->lastInsertId();

        // Skills
        if (is_array($skills) && !empty($skills)) {
            $ins = $pdo->prepare("INSERT INTO employee_skills (employee_id, job_type_id) VALUES (:eid, :jt)");
            foreach ($skills as $jt) {
                $jt = (int)$jt;
                if ($jt > 0) $ins->execute([':eid' => $employeeId, ':jt' => $jt]);
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
        $eu = $pdo->prepare("UPDATE employees SET hire_date = :hd, is_active = :act WHERE id = :id");
        $eu->execute([':hd' => $hire_date, ':act' => $is_active, ':id' => $id]);

        // Replace skills
        $pdo->prepare("DELETE FROM employee_skills WHERE employee_id = :eid")->execute([':eid' => $id]);
        if (is_array($skills) && !empty($skills)) {
            $ins = $pdo->prepare("INSERT INTO employee_skills (employee_id, job_type_id) VALUES (:eid, :jt)");
            foreach ($skills as $jt) {
                $jt = (int)$jt;
                if ($jt > 0) $ins->execute([':eid' => $id, ':jt' => $jt]);
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
