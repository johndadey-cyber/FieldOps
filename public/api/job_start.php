<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Job.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    JsonResponse::json(['ok' => false, 'error' => 'Method not allowed', 'code' => 405], 405);
    return;
}

$raw  = file_get_contents('php://input');
$data = $_POST;
if (!verify_csrf_token($data['csrf_token'] ?? null)) {
    csrf_log_failure_payload($raw, $data);
    JsonResponse::json(['ok' => false, 'error' => 'Invalid CSRF token', 'code' => \ErrorCodes::CSRF_INVALID], 400);
    return;
}
require_auth();
require_role('tech');

$jobId = isset($data['job_id']) ? (int)$data['job_id'] : 0;
$lat   = isset($data['location_lat']) ? (float)$data['location_lat'] : (isset($data['lat']) ? (float)$data['lat'] : null);
$lng   = isset($data['location_lng']) ? (float)$data['location_lng'] : (isset($data['lng']) ? (float)$data['lng'] : null);

if ($jobId <= 0 || $lat === null || $lng === null) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo    = getPDO();
    require_job_owner($pdo, $jobId);
    $ok = Job::start($pdo, $jobId, $lat, $lng);
    if ($ok) {
        JsonResponse::json(['ok' => true, 'status' => 'in_progress']);
    } else {
        JsonResponse::json(['ok' => false, 'error' => 'Invalid status', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    }
} catch (Throwable $e) {

    error_log('job_start: ' . $e->getMessage());

    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}

