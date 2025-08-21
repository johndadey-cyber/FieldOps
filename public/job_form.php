<?php
declare(strict_types=1);

require __DIR__ . '/_cli_guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/Job.php';
require_once __DIR__ . '/../models/Customer.php';
require_once __DIR__ . '/../models/JobChecklistItem.php';

$pdo = getPDO();
$__csrf = csrf_token();

$mode        = $mode ?? 'add';
$job         = $job ?? [];
$jobSkillIds = $jobSkillIds ?? [];
$jobTypeIds  = $jobTypeIds ?? [];
$isEdit      = $mode === 'edit';

$skills    = Skill::all($pdo);
$statuses  = $isEdit ? Job::allowedStatuses() : array_intersect(['scheduled','draft'], Job::allowedStatuses());
$customers = (new Customer($pdo))->getAll();
$jobTypes  = $pdo->query('SELECT id, name FROM job_types ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
$today     = date('Y-m-d');

// Existing checklist items when editing
$existingChecklist = [];
if ($isEdit && isset($job['id'])) {
    $existingChecklist = array_map(
        static fn(array $item): string => $item['description'],
        JobChecklistItem::listForJob($pdo, (int)$job['id'])
    );
}

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Sticky helper */
function sticky(string $name, ?string $default = null): string {
    global $job;
    $v = $_POST[$name] ?? $_GET[$name] ?? ($job[$name] ?? $default ?? '');
    return is_string($v) ? $v : (string)$v;
}

/** Sticky helper for arrays */
function stickyArr(string $name, array $default = []): array {
    $v = $_POST[$name] ?? $default;
    return is_array($v) ? $v : [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $isEdit ? 'Edit Job' : 'Add Job' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body>
  <div class="container mt-4">
    <h1 class="mb-4"><?= $isEdit ? 'Edit Job' : 'Add Job' ?></h1>
    <?php if ($isEdit && !$job): ?>
      <p>Job not found.</p>
    <?php else: ?>
    <div id="form-errors" class="text-danger mb-3"></div>
    <form id="jobForm" method="post" action="job_save.php" autocomplete="off" class="needs-validation" novalidate data-mode="<?= $isEdit ? 'edit' : 'add' ?>">
      <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= s((string)($job['id'] ?? '')) ?>">
      <?php endif; ?>

      <fieldset class="mb-4">
        <legend>Customer Information</legend>
        <div class="mb-3">
          <label for="customerId" class="form-label">Customer <span class="text-danger">*</span></label>
          <?php $selCust = sticky('customer_id', isset($job['customer_id']) ? (string)$job['customer_id'] : ''); ?>
          <select name="customer_id" id="customerId" class="form-select" required aria-required="true">
            <option value="">-- Select --</option>
            <?php foreach ($customers as $c): ?>
              <?php $cid = (string)$c['id']; ?>
              <option value="<?= s($cid) ?>" <?= $selCust === $cid ? 'selected' : '' ?>>
                <?= s(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '') . ' — ' . ($c['address_line1'] ?? '') . ', ' . ($c['city'] ?? ''))) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Customer is required.</div>
        </div>
      </fieldset>

      <fieldset class="mb-4">
        <legend>Job Details</legend>
        <div class="mb-3">
          <label for="description" class="form-label">Job Description <span class="text-danger">*</span></label>
          <textarea id="description" name="description" class="form-control" minlength="5" maxlength="255" required aria-required="true"><?= s(sticky('description', $job['description'] ?? '')) ?></textarea>
          <div class="invalid-feedback">Description must be between 5 and 255 characters.</div>
        </div>
        <div class="mb-3">
          <label for="skills" class="form-label">Skills</label>
          <?php $selSkills = stickyArr('skills', array_map('strval', $jobSkillIds)); ?>
          <select id="skills" name="skills[]" class="form-select" multiple>
            <?php foreach ($skills as $sk): ?>
              <?php $sid = (string)$sk['id']; ?>
              <option value="<?= s($sid) ?>" <?= in_array($sid, $selSkills, true) ? 'selected' : '' ?>><?= s($sk['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback d-block" id="jobSkillError" style="display:none">Select at least one skill.</div>
        </div>
        <div class="mb-3">
          <label for="job_type_ids" class="form-label">Job Types</label>
          <?php $selJobTypes = stickyArr('job_type_ids', $jobTypeIds); ?>
          <select name="job_type_ids[]" id="job_type_ids" class="form-select" multiple>
            <?php foreach ($jobTypes as $jt): ?>
              <?php $jtId = (string)$jt['id']; ?>
              <option value="<?= s($jtId) ?>" <?= in_array($jtId, $selJobTypes, true) ? 'selected' : '' ?>><?= s($jt['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
          <?php $selStatus = sticky('status', $isEdit ? ($job['status'] ?? 'draft') : 'scheduled'); ?>
          <select name="status" id="status" class="form-select" required aria-required="true">
            <option value="">-- Select --</option>
            <?php foreach ($statuses as $st): ?>
              <?php $label = ucwords(str_replace('_', ' ', $st)); ?>
              <option value="<?= s($st) ?>" <?= $selStatus === $st ? 'selected' : '' ?>><?= s($label) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Status is required.</div>
        </div>
      </fieldset>

      <fieldset class="mb-4" id="checklistFieldset" style="display:none;">
        <legend>Checklist Items</legend>
        <div id="checklistItems"></div>
        <button type="button" class="btn btn-outline-secondary mt-2" id="addChecklistItem">Add Item</button>
      </fieldset>

      <fieldset class="mb-4">
        <legend>Scheduling</legend>
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label for="scheduled_date" class="form-label">Scheduled Date <span class="text-danger">*</span></label>
            <input type="date" id="scheduled_date" name="scheduled_date" class="form-control" min="<?= s($today) ?>" max="9999-12-31" value="<?= s(sticky('scheduled_date', $job['scheduled_date'] ?? '')) ?>" required aria-required="true">
            <div class="invalid-feedback">Enter a valid scheduled date.</div>
          </div>
          <div class="col-md-4">
            <label for="scheduled_time" class="form-label">Scheduled Time <span class="text-danger">*</span></label>
            <?php $timeVal = $job['scheduled_time'] ?? ''; if (is_string($timeVal)) { $timeVal = substr($timeVal,0,5); } ?>
            <input type="time" id="scheduled_time" name="scheduled_time" class="form-control" value="<?= s(sticky('scheduled_time', $timeVal ?: '')) ?>" required aria-required="true">
            <div class="invalid-feedback">Enter a valid scheduled time.</div>
          </div>
          <div class="col-md-4">
            <label for="duration_minutes" class="form-label">Duration (minutes)</label>
            <input type="number" id="duration_minutes" name="duration_minutes" class="form-control" min="1" step="1" value="<?= s(sticky('duration_minutes', isset($job['duration_minutes']) ? (string)$job['duration_minutes'] : '60')) ?>">
          </div>
        </div>
      </fieldset>

      <div class="mt-3">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Save Job' ?></button>
        <a href="jobs.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
    <?php endif; ?>
  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="/js/toast.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
    window.jobChecklistTemplates = <?= json_encode(JobChecklistItem::defaultTemplates($pdo)); ?>;
    window.initialChecklistItems = <?= json_encode($existingChecklist); ?>;
  </script>
  <script src="js/job_form.js"></script>
</body>
</html>
