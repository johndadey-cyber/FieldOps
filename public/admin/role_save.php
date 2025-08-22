<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Role.php';
require_once __DIR__ . '/../_csrf.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo 'Method Not Allowed';
    exit;
}

$raw   = file_get_contents('php://input');
$data  = array_merge($_GET, $_POST);
$token = (string)($data['csrf_token'] ?? '');
if (!csrf_verify($token)) {
    csrf_log_failure_payload($raw, $data);
    http_response_code(422);
    echo 'Invalid CSRF token';
    exit;
}

$pdo  = getPDO();
$id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));
$isDelete = isset($_POST['delete']);

if ($isDelete) {
    if ($id > 0) {
        Role::delete($pdo, $id);
    }
    header('Location: role_list.php');
    exit;
}

if ($name === '') {
    header('Location: ' . ($id > 0 ? 'role_form.php?id=' . $id : 'role_form.php'));
    exit;
}

if ($id > 0) {
    Role::update($pdo, $id, $name);
} else {
    Role::create($pdo, $name);
}

header('Location: role_list.php');
exit;
