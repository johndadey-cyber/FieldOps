<?php

declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../models/Job.php';
require __DIR__ . '/../../models/JobNote.php';
require __DIR__ . '/../../models/JobPhoto.php';
require __DIR__ . '/../../models/JobCompletion.php';

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

$jobId        = isset($data['job_id']) ? (int)$data['job_id'] : 0;
$technicianId = isset($data['technician_id']) ? (int)$data['technician_id'] : 0;
$lat          = isset($data['location_lat']) ? (float)$data['location_lat'] : (isset($data['lat']) ? (float)$data['lat'] : null);
$lng          = isset($data['location_lng']) ? (float)$data['location_lng'] : (isset($data['lng']) ? (float)$data['lng'] : null);
$finalNote    = trim((string)($data['final_note'] ?? ''));
$photos       = $data['final_photos'] ?? [];
$signature    = (string)($data['signature'] ?? '');

if ($jobId <= 0 || $technicianId <= 0 || $lat === null || $lng === null || $finalNote === '' || !is_array($photos) || count($photos) === 0 || $signature === '') {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo = getPDO();
    $pdo->beginTransaction();

    JobNote::add($pdo, $jobId, $technicianId, $finalNote, true);

    $uploadDir = __DIR__ . '/../uploads/jobs/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    foreach ($photos as $p) {
        if (!is_string($p) || $p === '') {
            throw new RuntimeException('Invalid photo');
        }
        $ext = 'png';
        if (preg_match('/^data:image\/(\w+);base64,/', $p, $m)) {
            $p   = substr($p, strpos($p, ',') + 1);
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        }
        $img = base64_decode($p, true);
        if ($img === false) {
            throw new RuntimeException('Invalid photo');
        }
        $filename     = 'job_' . $jobId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destPath     = $uploadDir . $filename;
        $relativePath = 'uploads/jobs/' . $filename;
        if (!is_string($destPath) || !is_string($relativePath)) {
            throw new RuntimeException('Invalid photo path');
        }
        file_put_contents($destPath, $img);
        JobPhoto::add($pdo, $jobId, $technicianId, $relativePath, 'final');
    }

    $sigDir = __DIR__ . '/../uploads/signatures/';
    if (!is_dir($sigDir)) {
        mkdir($sigDir, 0777, true);
    }
    $sigExt = 'png';
    if (preg_match('/^data:image\/(\w+);base64,/', $signature, $sm)) {
        $signature = substr($signature, strpos($signature, ',') + 1);
        $sigExt    = strtolower($sm[1]);
        if ($sigExt === 'jpeg') {
            $sigExt = 'jpg';
        }
    }
    $sigBin = base64_decode($signature, true);
    if ($sigBin === false) {
        throw new RuntimeException('Invalid signature');
    }
    $sigFile     = 'job_' . $jobId . '_signature_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $sigExt;
    $sigDest     = $sigDir . $sigFile;
    $sigRelPath  = 'uploads/signatures/' . $sigFile;
    if (!is_string($sigDest) || !is_string($sigRelPath)) {
        throw new RuntimeException('Invalid signature path');
    }
    file_put_contents($sigDest, $sigBin);
    JobCompletion::save($pdo, $jobId, $sigRelPath);

    $ok = Job::complete($pdo, $jobId, $lat, $lng);
    if ($ok) {
        $pdo->commit();
        // Log completion event for auditing
        $msg = sprintf(
            "[%s] job_id=%d technician_id=%d status=completed\n",
            date('c'),
            $jobId,
            $technicianId
        );
        error_log($msg, 3, __DIR__ . '/../../logs/job_events.log');

        // Notify internal stakeholders about the completed job
        $subject = 'Job Completed: #' . $jobId;
        $body    = 'Job #' . $jobId . ' was completed by technician #' . $technicianId . ' at ' . date('c');
        @mail('alerts@example.com', $subject, $body);

        JsonResponse::json(['ok' => true, 'status' => 'completed']);
    } else {
        $pdo->rollBack();
        JsonResponse::json(['ok' => false, 'error' => 'Invalid status', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    }
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
