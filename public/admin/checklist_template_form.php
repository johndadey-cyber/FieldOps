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
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$template = $id > 0 ? ChecklistTemplate::find($pdo, $id) : null;
$types = JobType::all($pdo);
$jobTypeId = $template['job_type_id'] ?? (isset($_GET['job_type_id']) ? (int)$_GET['job_type_id'] : 0);
$__csrf = csrf_token();
$title = $id > 0 ? 'Edit Checklist Template' : 'Add Checklist Template';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<form method="post" action="/admin/checklist_template_save.php" class="card p-3" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($id > 0): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="action" value="update">
  <?php else: ?>
    <input type="hidden" name="action" value="create">
  <?php endif; ?>
  <div class="mb-3">
    <label class="form-label" for="job_type_id">Job Type</label>
    <select class="form-select" id="job_type_id" name="job_type_id" required>
      <?php foreach ($types as $t): $tid = (int)$t['id']; ?>
        <option value="<?= $tid ?>" <?= $jobTypeId === $tid ? 'selected' : '' ?>><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <label class="form-label" for="description">Description</label>
    <input type="text" class="form-control" id="description" name="description" required value="<?= htmlspecialchars($template['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="mb-3">
    <label class="form-label" for="position">Position</label>
    <input type="number" class="form-control" id="position" name="position" value="<?= htmlspecialchars($template['position'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="mt-3">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="/admin/checklist_template_list.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
