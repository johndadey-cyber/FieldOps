<?php
// /public/job_process.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';;



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
    redirectWithFlash('jobs.php', 'danger', 'Invalid action.');
}

if (!isset($_SESSION['csrf_token'], $_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    redirectWithFlash('jobs.php', 'danger', 'Security token invalid. Please try again.');
}

$id            = (int)($_POST['id'] ?? 0);
$customer_id   = (int)($_POST['customer_id'] ?? 0);
$description   = trim((string)($_POST['description'] ?? ''));
$scheduled_date= trim((string)($_POST['scheduled_date'] ?? ''));
$scheduled_time= trim((string)($_POST['scheduled_time'] ?? ''));
$duration_min  = (int)($_POST['duration_minutes'] ?? 0);
$status        = trim((string)($_POST['status'] ?? ''));

$errors = [];
if ($customer_id <= 0)      $errors[] = 'Customer is required.';
if ($description === '')    $errors[] = 'Description is required.';
if ($scheduled_date === '') $errors[] = 'Scheduled date is required.';
if ($scheduled_time === '') $errors[] = 'Scheduled time is required.';
if ($duration_min < 0)      $errors[] = 'Duration must be zero or more.';
if ($status === '')         $errors[] = 'Status is required.';

if ($errors) {
    redirectWithFlash(($action === 'create' ? 'add_job.php' : "edit_job.php?id={$id}"), 'danger', implode(' ', $errors));
}

try {
    $pdo->beginTransaction();

    if ($action === 'create') {
        $ins = $pdo->prepare("
            INSERT INTO jobs (customer_id, description, scheduled_date, scheduled_time, duration_minutes, status)
            VALUES (:cid, :d, :sd, :st, :dur, :stt)
        ");
        $ins->execute([
            ':cid' => $customer_id, ':d' => $description, ':sd' => $scheduled_date,
            ':st'  => $scheduled_time, ':dur' => $duration_min, ':stt' => $status,
        ]);
        $jobId = (int)$pdo->lastInsertId();

        // job types removed
    } else {
        if ($id <= 0) throw new RuntimeException('Missing job ID.');
        $upd = $pdo->prepare("
            UPDATE jobs
            SET customer_id = :cid, description = :d, scheduled_date = :sd,
                scheduled_time = :st, duration_minutes = :dur, status = :stt
            WHERE id = :id
        ");
        $upd->execute([
            ':cid'=>$customer_id, ':d'=>$description, ':sd'=>$scheduled_date, ':st'=>$scheduled_time,
            ':dur'=>$duration_min, ':stt'=>$status, ':id'=>$id,
        ]);

        // job types removed
    }

    $pdo->commit();
    redirectWithFlash('jobs.php', 'success', 'Job saved.');
} catch (Throwable $e) {
    $pdo->rollBack();
    error_log('[job_process] '.$e->getMessage());
    // TEMP dev-friendly error; remove message detail for prod if desired
    redirectWithFlash('jobs.php', 'danger', 'Job save failed: '.$e->getMessage());
}
