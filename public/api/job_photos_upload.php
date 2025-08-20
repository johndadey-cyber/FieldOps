<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobPhoto.php';

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
$tags         = (array)($data['tags'] ?? []);
$files        = $_FILES['photos'] ?? null;

if ($jobId <= 0 || $technicianId <= 0 || !is_array($files) || !isset($files['name'])) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

$count = count($files['name']);
$allowed = ['jpg','jpeg','png','gif'];
$uploadDir = __DIR__ . '/../uploads/jobs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

$pdo = getPDO();
$uploaded = [];

for ($i = 0; $i < $count; $i++) {
    if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        continue;
    }
    $ext = strtolower(pathinfo((string)$files['name'][$i], PATHINFO_EXTENSION));
    if ($ext === '' || !in_array($ext, $allowed, true)) {
        continue;
    }

    $filename     = 'job_' . $jobId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath     = $uploadDir . $filename;
    $relativePath = 'uploads/jobs/' . $filename;

    if (!move_uploaded_file((string)$files['tmp_name'][$i], $destPath)) {
        if (PHP_SAPI === 'cli' && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']) &&
            @rename((string)$files['tmp_name'][$i], $destPath)
        ) {
            // allow tests to bypass move_uploaded_file restrictions
        } else {
            continue;
        }
    }

    try {
        $tag = trim((string)($tags[$i] ?? ''));
        $id  = JobPhoto::add($pdo, $jobId, $technicianId, $relativePath, $tag);
        $uploaded[] = ['id' => $id, 'path' => $relativePath, 'tag' => $tag];
    } catch (Throwable $e) {
        @unlink($destPath);
        continue;
    }
}

if (empty($uploaded)) {
    JsonResponse::json(['ok' => false, 'error' => 'Upload failed', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

JsonResponse::json(['ok' => true, 'photos' => $uploaded]);
