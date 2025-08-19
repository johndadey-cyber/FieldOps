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
$types = JobType::all($pdo);
$__csrf = csrf_token();
$title = 'Job Types';
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<div class="d-flex mb-3">
  <a href="/admin/job_type_form.php" class="btn btn-primary ms-auto">Add Job Type</a>
</div>
<div class="card">
  <div class="table-responsive">
    <table class="table table-striped table-hover m-0">
      <thead class="table-light">
        <tr>
          <th>ID</th>
          <th>Name</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($types as $t): ?>
        <tr>
          <td><?= (int)$t['id'] ?></td>
          <td><?= htmlspecialchars((string)$t['name'], ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-end">
            <a href="/admin/job_type_form.php?id=<?= (int)$t['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            <form method="post" action="/admin/job_type_save.php" class="d-inline" onsubmit="return confirm('Delete this job type?');">
              <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger">Delete</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$types): ?>
        <tr><td colspan="3" class="text-center text-muted py-4">No job types found.</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
