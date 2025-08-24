<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobNote.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    JsonResponse::json(['ok' => false, 'error' => 'Method not allowed', 'code' => 405], 405);
    return;
}

$raw  = file_get_contents('php://input');
$data = array_merge($_GET, $_POST);
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    JsonResponse::json(['ok' => false, 'error' => 'Invalid CSRF token', 'code' => \ErrorCodes::CSRF_INVALID], 400);
    return;
}
require_auth();
require_role('tech');

$jobId        = isset($data['job_id']) ? (int)$data['job_id'] : 0;
$technicianId = isset($data['technician_id']) ? (int)$data['technician_id'] : 0;
$note         = trim((string)($data['note'] ?? ''));

if ($jobId <= 0 || $technicianId <= 0 || $note === '') {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

$sessionId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
if ($sessionId !== $technicianId) {
    JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
    return;
}

try {
    $pdo = getPDO();
    require_job_owner($pdo, $jobId);
    $id  = JobNote::add($pdo, $jobId, $technicianId, $note);
    $status = $pdo->query('SELECT status FROM jobs WHERE id=' . $jobId)->fetchColumn();
    JsonResponse::json(['ok' => true, 'id' => $id, 'status' => $status]);
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
