<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'admin') {
    return;
}
?>
<nav class="mb-3">
  <a href="/admin/job_type_list.php" class="me-3">Job Types</a>
  <a href="/admin/checklist_template_list.php" class="me-3">Checklists</a>
  <a href="/admin/skill_list.php" class="me-3">Skills</a>
  <a href="/admin/role_list.php" class="me-3">Roles</a>
</nav>
