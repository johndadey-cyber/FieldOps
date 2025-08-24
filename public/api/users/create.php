<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/session.php';
require_once __DIR__ . '/../../_auth.php';
require_once __DIR__ . '/../../../helpers/auth_helpers.php';
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../models/User.php';
require_once __DIR__ . '/../../../models/AuditLog.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::json(['ok' => false, 'error' => 'Method not allowed', 'code' => 405], 405);
    return;
}

require_role('admin');
require_csrf();

$username = trim((string)($_POST['username'] ?? ''));
$email    = trim((string)($_POST['email'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$role     = (string)($_POST['role'] ?? '');

$allowedRoles = ['dispatcher', 'tech', 'admin'];
if ($username === '' || $email === '' || $password === '' || !in_array($role, $allowedRoles, true)) {
    JsonResponse::json(['ok' => false, 'error' => 'Invalid parameters', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
    return;
}

try {
    $pdo = getPDO();
    $res = User::create($pdo, $username, $email, $password, $role);
    if (!$res['ok']) {
        JsonResponse::json(['ok' => false, 'error' => $res['error'] ?? 'Server error', 'code' => \ErrorCodes::VALIDATION_ERROR], 422);
        return;
    }
    $newId = (int)($res['id'] ?? 0);
    $creatorId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : null;
    AuditLog::insert($pdo, $creatorId, 'user_create', ['new_user_id' => $newId]);
    JsonResponse::json(['ok' => true, 'id' => $newId]);
} catch (Throwable $e) {
    JsonResponse::json(['ok' => false, 'error' => 'Server error', 'code' => \ErrorCodes::SERVER_ERROR], 500);
}
