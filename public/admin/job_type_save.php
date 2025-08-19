<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_csrf();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobType.php';

$pdo = getPDO();
$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$name = trim((string)($_POST['name'] ?? ''));

switch ($action) {
    case 'create':
        if ($name !== '') { JobType::create($pdo, $name); }
        break;
    case 'update':
        if ($id > 0 && $name !== '') { JobType::update($pdo, $id, $name); }
        break;
    case 'delete':
        if ($id > 0) { JobType::delete($pdo, $id); }
        break;
}
header('Location: /admin/job_type_list.php');
exit;
