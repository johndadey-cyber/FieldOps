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
  <div class="alert alert-info small" role="alert">
    Each submission creates a single checklist item. To add multiple items, save and return to the
    <a href="/admin/checklist_template_list.php" class="alert-link">Checklist Templates</a> page to add more.
  </div>
  <div class="mb-3">
    <label class="form-label" for="job_type_id">Job Type</label>
    <select class="form-select" id="job_type_id" name="job_type_id" required>
      <?php foreach ($types as $t): $tid = (int)$t['id']; ?>
        <option value="<?= $tid ?>" <?= $jobTypeId === $tid ? 'selected' : '' ?>><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div id="items">
    <?php
    $existing = $template ? [
        ['description' => $template['description'] ?? '', 'position' => $template['position'] ?? null],
    ] : [['description' => '', 'position' => null]];
    foreach ($existing as $i => $item): ?>
      <div class="row g-2 mb-2">
        <div class="col">
          <label class="form-label" for="desc-<?= $i ?>">Description</label>
          <input type="text" class="form-control" id="desc-<?= $i ?>" name="items[<?= $i ?>][description]" required value="<?= htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="col-2">
          <label class="form-label" for="pos-<?= $i ?>">Position</label>
          <input type="number" class="form-control" id="pos-<?= $i ?>" name="items[<?= $i ?>][position]" value="<?= htmlspecialchars((string)($item['position'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($id === 0): ?>
  <button type="button" class="btn btn-secondary mb-3" id="add-item">+ Add Item</button>
  <?php endif; ?>
  <div class="mt-3">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="/admin/checklist_template_list.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php if ($id === 0): ?>
<script>
document.getElementById('add-item').addEventListener('click', function () {
  const container = document.getElementById('items');
  const idx = container.children.length;
  const row = document.createElement('div');
  row.className = 'row g-2 mb-2';
  row.innerHTML = `
    <div class="col">
      <label class="form-label" for="desc-${idx}">Description</label>
      <input type="text" class="form-control" id="desc-${idx}" name="items[${idx}][description]" required>
    </div>
    <div class="col-2">
      <label class="form-label" for="pos-${idx}">Position</label>
      <input type="number" class="form-control" id="pos-${idx}" name="items[${idx}][position]">
    </div>`;
  container.appendChild(row);
});
</script>
<?php endif; ?>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
