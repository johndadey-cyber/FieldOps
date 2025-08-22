<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../_auth.php';
require_role('admin');

$title = 'Admin Dashboard';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<h1 class="h4 mb-3">Admin Tools</h1>
<ul>
  <li><a href="/admin/job_type_list.php">Job Types</a></li>
  <li><a href="/admin/checklist_template_list.php">Checklists</a></li>
  <li><a href="/admin/skill_list.php">Skills</a></li>
  <li><a href="/admin/job_deletion_log.php">Job Deletion Log</a></li>
</ul>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
