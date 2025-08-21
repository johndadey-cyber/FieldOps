<?php
declare(strict_types=1);
require_once __DIR__ . '/../_auth.php';
require_role('admin');
?>
<nav class="mb-3">
  <a href="/admin/job_type_list.php" class="me-3">Job Types</a>
  <a href="/admin/checklist_template_list.php" class="me-3">Checklists</a>
</nav>
