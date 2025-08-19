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
$label        = trim((string)($data['label'] ?? ''));
$file         = $_FILES['photo'] ?? null;

if ($jobId <= 0 || $technicianId <= 0 || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
$allowed = ['jpg','jpeg','png','gif'];
if ($ext === '' || !in_array($ext, $allowed, true)) {
    JsonResponse::json(['ok' => false, 'error' => 'Unsupported file type', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

$uploadDir = __DIR__ . '/../uploads/jobs/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
$filename     = 'job_' . $jobId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$destPath     = $uploadDir . $filename;
$relativePath = 'uploads/jobs/' . $filename;

if (!move_uploaded_file((string)$file['tmp_name'], $destPath)) {
    if (PHP_SAPI === 'cli' && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']) &&
        @rename((string)$file['tmp_name'], $destPath)
    ) {
        // allow tests to bypass move_uploaded_file restrictions
    } else {
        JsonResponse::json(['ok' => false, 'error' => 'Upload failed', 'code' => \ErrorCodes::SERVER_ERROR], 500);
        return;
    }
}

try {
    $pdo = getPDO();
    $id  = JobPhoto::add($pdo, $jobId, $technicianId, $relativePath, $label);
    JsonResponse::json(['ok' => true, 'id' => $id, 'path' => $relativePath]);
} catch (Throwable $e) {
    @unlink($destPath);
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
