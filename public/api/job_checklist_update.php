<?php
declare(strict_types=1);

require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../_auth.php';
require __DIR__ . '/../_csrf.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobChecklistItem.php';

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

$jobId = isset($data['job_id']) ? (int)$data['job_id'] : 0;

// New bulk update path
$itemsRaw = $data['items'] ?? null;
if ($itemsRaw !== null) {
    if ($jobId <= 0) {
        JsonResponse::json(['ok' => false, 'error' => 'Missing job_id', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
        return;
    }
    $items = is_string($itemsRaw) ? json_decode($itemsRaw, true) : null;
    if (!is_array($items)) {
        JsonResponse::json(['ok' => false, 'error' => 'Invalid items', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
        return;
    }
    try {
        $pdo = getPDO();
        require_job_owner($pdo, $jobId);
        $pdo->beginTransaction();
        $st = $pdo->prepare('UPDATE job_checklist_items SET is_completed = :c, completed_at = :ts WHERE id = :id');
        foreach ($items as $it) {
            $itemId = isset($it['id']) ? (int)$it['id'] : 0;
            $completed = (bool)($it['completed'] ?? false);
            if ($itemId <= 0) {
                throw new RuntimeException('Invalid id');
            }
            $st->execute([
                ':c' => $completed ? 1 : 0,
                ':ts' => $completed ? date('Y-m-d H:i:s') : null,
                ':id' => $itemId,
            ]);
        }
        $pdo->commit();
        $status = $pdo->query('SELECT status FROM jobs WHERE id=' . $jobId)->fetchColumn();
        JsonResponse::json(['ok' => true, 'status' => $status]);
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
    }
    return;
}

// Legacy single-id toggle for backward compatibility
$id = isset($data['id']) ? (int)$data['id'] : 0;
if ($id <= 0) {
    JsonResponse::json(['ok' => false, 'error' => 'Missing id', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo = getPDO();
    $stJob = $pdo->prepare('SELECT job_id FROM job_checklist_items WHERE id = :id');
    $jobRow = null;
    if ($stJob !== false) {
        $stJob->execute([':id' => $id]);
        $jobRow = $stJob->fetchColumn();
    }
    if ($jobRow === false || $jobRow === null) {
        JsonResponse::json(['ok' => false, 'error' => 'Not found', 'code' => \ErrorCodes::NOT_FOUND], 404);
        return;
    }
    require_job_owner($pdo, (int)$jobRow);
    $newState = JobChecklistItem::toggle($pdo, $id);
    if ($newState === null) {
        JsonResponse::json(['ok' => false, 'error' => 'Not found', 'code' => \ErrorCodes::NOT_FOUND], 404);
        return;
    }
    $st = $pdo->prepare('SELECT j.status FROM jobs j JOIN job_checklist_items i ON i.job_id = j.id WHERE i.id = :id');
    $status = null;
    if ($st !== false) {
        $st->execute([':id' => $id]);
        $status = $st->fetchColumn();
    }
    JsonResponse::json(['ok' => true, 'is_completed' => $newState, 'status' => $status]);
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
