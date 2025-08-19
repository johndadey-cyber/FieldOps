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
require_once __DIR__ . '/../../models/Skill.php';
require_once __DIR__ . '/../../models/JobTypeSkill.php';
require_once __DIR__ . '/../_csrf.php';

$pdo        = getPDO();
$jobTypeId  = isset($_GET['job_type_id']) ? (int)$_GET['job_type_id'] : 0;
$jobType    = $jobTypeId > 0 ? JobType::find($pdo, $jobTypeId) : null;
if (!$jobType) {
    header('Location: /admin/job_type_list.php');
    exit;
}

$skills      = Skill::all($pdo);
$current     = JobTypeSkill::listForJobType($pdo, $jobTypeId);
$currentSet  = array_flip($current);
$__csrf      = csrf_token();
$title       = 'Skill Mapping: ' . ($jobType['name'] ?? '');
require_once __DIR__ . '/../../partials/header.php';
require_once __DIR__ . '/nav.php';
?>
<?php if (isset($_GET['saved'])): ?>
<div class="alert alert-success">Mappings saved.</div>
<?php endif; ?>
<form method="post" action="/admin/job_type_skill_save.php">
  <input type="hidden" name="job_type_id" value="<?= (int)$jobTypeId ?>">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($__csrf, ENT_QUOTES, 'UTF-8') ?>">
  <div class="card mb-3">
    <div class="card-body">
    <?php foreach ($skills as $sk): ?>
      <?php $sid = (int)$sk['id']; ?>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="sk<?= $sid ?>" name="skills[]" value="<?= $sid ?>" <?= isset($currentSet[$sid]) ? 'checked' : '' ?>>
        <label class="form-check-label" for="sk<?= $sid ?>"><?= htmlspecialchars((string)$sk['name'], ENT_QUOTES, 'UTF-8') ?></label>
      </div>
    <?php endforeach; ?>
    <?php if (!$skills): ?>
      <p class="text-muted mb-0">No skills found.</p>
    <?php endif; ?>
    </div>
  </div>
  <button type="submit" class="btn btn-primary">Save</button>
  <a href="/admin/job_type_list.php" class="btn btn-secondary ms-2">Back</a>
</form>
<?php require_once __DIR__ . '/../../partials/footer.php'; ?>
