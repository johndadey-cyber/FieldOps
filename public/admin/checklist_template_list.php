<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/JobType.php';
require_once __DIR__ . '/../../models/ChecklistTemplate.php';
require_once __DIR__ . '/../_csrf.php';

$pdo = getPDO();
$types = JobType::all($pdo);
$templates = ChecklistTemplate::allByJobType($pdo);
$__csrf = csrf_token();
$title = 'Checklist Templates';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<?php foreach ($types as $t): $tid = (int)$t['id']; ?>
  <h5 class="mt-4"><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></h5>
  <div class="d-flex mb-2">
    <a href="/admin/checklist_template_form.php?job_type_id=<?= $tid ?>" class="btn btn-primary btn-sm ms-auto">Add Template</a>
  </div>
  <div class="card mb-4">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0">
        <thead class="table-light">
          <tr>
            <th>Pos</th>
            <th>Description</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($templates[$tid] ?? [] as $tpl): ?>
          <tr>
            <td><?= htmlspecialchars($tpl['position'] !== null ? (string)$tpl['position'] : '', ENT_QUOTES, 'UTF-8') ?></td>
            <td><?= htmlspecialchars($tpl['description'], ENT_QUOTES, 'UTF-8') ?></td>
            <td class="text-end">
              <a href="/admin/checklist_template_form.php?id=<?= (int)$tpl['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
              <form method="post" action="/admin/checklist_template_save.php" class="d-inline" onsubmit="return confirm('Delete this template?');">
                <input type="hidden" name="id" value="<?= (int)$tpl['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (empty($templates[$tid])): ?>
          <tr><td colspan="3" class="text-center text-muted py-4">No templates found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endforeach; ?>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
