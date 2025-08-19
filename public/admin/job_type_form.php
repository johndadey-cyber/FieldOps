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
require_once __DIR__ . '/../_csrf.php';

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$jobType = $id > 0 ? JobType::find($pdo, $id) : null;
$__csrf = csrf_token();
$title = $id > 0 ? 'Edit Job Type' : 'Add Job Type';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<form method="post" action="/admin/job_type_save.php" class="card p-3" autocomplete="off">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <?php if ($id > 0): ?>
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <input type="hidden" name="action" value="update">
  <?php else: ?>
    <input type="hidden" name="action" value="create">
  <?php endif; ?>
  <div class="mb-3">
    <label class="form-label" for="name">Name</label>
    <input type="text" class="form-control" id="name" name="name" required value="<?= htmlspecialchars($jobType['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
  </div>
  <div class="mt-3">
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="/admin/job_type_list.php" class="btn btn-secondary">Cancel</a>
  </div>
</form>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
