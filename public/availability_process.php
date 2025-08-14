<?php
// /public/availability_process.php
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

if (!isset($_SESSION['csrf_token'], $_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    redirectWithFlash('employees.php', 'danger', 'Security token invalid. Please try again.');
}

$employeeId  = (int)($_POST['employee_id'] ?? 0);
$personId    = (int)($_POST['person_id'] ?? 0);
$availability= $_POST['availability'] ?? [];

if ($employeeId <= 0 || $personId <= 0) {
    redirectWithFlash('employees.php', 'danger', 'Missing employee/person reference.');
}

$validDays = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
try {
    $pdo->beginTransaction();

    // Remove existing
    $pdo->prepare("DELETE FROM availability WHERE person_id = :pid")->execute([':pid' => $personId]);

    // Insert new rows
    $ins = $pdo->prepare("
        INSERT INTO availability (person_id, day_of_week, start_time, end_time)
        VALUES (:pid, :dow, :st, :et)
    ");

    foreach ($availability as $dow => $times) {
        if (!in_array($dow, $validDays, true)) continue;
        $st = trim((string)($times['start'] ?? ''));
        $et = trim((string)($times['end'] ?? ''));
        if ($st === '' && $et === '') continue; // skip unavailable days

        // Basic sanity: HH:MM or HH:MM:SS
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $st) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $et)) {
            throw new RuntimeException("Invalid time format for {$dow}.");
        }
        $ins->execute([
            ':pid' => $personId,
            ':dow' => $dow,
            ':st'  => strlen($st) === 5 ? $st.':00' : $st,
            ':et'  => strlen($et) === 5 ? $et.':00' : $et,
        ]);
    }

    $pdo->commit();
    redirectWithFlash("availability_form.php?employee_id={$employeeId}", 'success', 'Availability saved.');
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[availability_process] '.$e->getMessage());
    redirectWithFlash("availability_form.php?employee_id={$employeeId}", 'danger', 'Failed to save availability. Please check times and try again.');
}
