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
require_once __DIR__ . '/../../models/ChecklistTemplate.php';

$pdo = getPDO();
$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$jobTypeId = isset($_POST['job_type_id']) ? (int)$_POST['job_type_id'] : 0;
$items = [];
if (isset($_POST['items']) && is_array($_POST['items'])) {
    $items = $_POST['items'];
}
$description = trim((string)($_POST['description'] ?? ''));
$position = isset($_POST['position']) && $_POST['position'] !== '' ? (int)$_POST['position'] : null;

switch ($action) {
    case 'create':
        if ($jobTypeId > 0 && !empty($items)) {
            foreach ($items as $it) {
                $desc = trim((string)($it['description'] ?? ''));
                $pos  = isset($it['position']) && $it['position'] !== '' ? (int)$it['position'] : null;
                if ($desc !== '') {
                    ChecklistTemplate::create($pdo, $jobTypeId, $desc, $pos);
                }
            }
        } elseif ($jobTypeId > 0 && $description !== '') {
            ChecklistTemplate::create($pdo, $jobTypeId, $description, $position);
        }
        break;
    case 'update':
        $first = $items[0] ?? ['description' => $description, 'position' => $position];
        $desc = trim((string)($first['description'] ?? ''));
        $pos  = isset($first['position']) && $first['position'] !== '' ? (int)$first['position'] : null;
        if ($id > 0 && $jobTypeId > 0 && $desc !== '') { ChecklistTemplate::update($pdo, $id, $jobTypeId, $desc, $pos); }
        break;
    case 'delete':
        if ($id > 0) { ChecklistTemplate::delete($pdo, $id); }
        break;
}
header('Location: /admin/checklist_template_list.php');
exit;
