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
$lat   = isset($data['location_lat']) ? (float)$data['location_lat'] : (isset($data['lat']) ? (float)$data['lat'] : null);
$lng   = isset($data['location_lng']) ? (float)$data['location_lng'] : (isset($data['lng']) ? (float)$data['lng'] : null);

if ($jobId <= 0 || $lat === null || $lng === null) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo    = getPDO();
    $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;

    $st = $pdo->prepare('SELECT technician_id FROM jobs WHERE id = :id');
    if ($st === false) {
        throw new RuntimeException('Failed to prepare statement');
    }
    $st->execute([':id' => $jobId]);
    $techId = (int)$st->fetchColumn();
    if ($techId !== $userId) {
        JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
        return;
    }

    $ok = Job::start($pdo, $jobId, $lat, $lng);
    if ($ok) {
        JsonResponse::json(['ok' => true, 'status' => 'in_progress']);
    } else {
        JsonResponse::json(['ok' => false, 'error' => 'Invalid status', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    }
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}

