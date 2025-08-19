<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../models/JobPhoto.php';

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

$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing id', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo   = getPDO();
    $photo = JobPhoto::get($pdo, $id);
    if ($photo === null) {
        JsonResponse::json(['ok' => false, 'error' => 'Not found', 'code' => 404], 404);
        return;
    }
    $deleted = JobPhoto::delete($pdo, $id);
    if ($deleted) {
        $fullPath = __DIR__ . '/../' . $photo['path'];
        if (is_file($fullPath)) {
            @unlink($fullPath);
        }
    }
    JsonResponse::json(['ok' => true, 'deleted' => $deleted]);
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
