<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

$raw  = file_get_contents('php://input');
$data = array_merge($_GET, $_POST);
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    JsonResponse::json(['ok' => false, 'error' => 'Invalid CSRF token', 'code' => \ErrorCodes::CSRF_INVALID], 400);
    return;
}

if (current_role() === 'guest') {
    JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
    return;
}

$jobId = isset($data['job_id']) ? (int)$data['job_id'] : 0;
if ($jobId <= 0) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing job_id', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo = getPDO();
    $items = JobChecklistItem::listForJob($pdo, $jobId);
    JsonResponse::json(['ok' => true, 'items' => $items]);
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
