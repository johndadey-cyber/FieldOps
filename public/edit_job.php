<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';



require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../models/Job.php';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$job = $id > 0 ? Job::getJobAndCustomerDetails(getPDO(), $id) : null;
$statuses = Job::allowedStatuses();
$jobTypes = $id > 0 ? Job::getJobTypesForJob(getPDO(), $id) : [];
$current  = strtolower((string)($job['status'] ?? 'draft'));

$pdo = getPDO();
$__csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Job</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>

<?php
/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
/** Sticky helper */
function sticky(string $name, ?string $default = null): string {
    $v = $_POST[$name] ?? $_GET[$name] ?? $default ?? '';
    return is_string($v) ? $v : (string)$v;
}
?>

  <h1>Edit Job</h1>
  <?php if (!$job): ?>
    <p>Job not found.</p>
  <?php else: ?>
    <form method="post" action="job_save.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <fieldset>
        <legend>Details</legend>
        <div><strong>Description:</strong> <?= s((string)($job['description'] ?? '')) ?></div>
        <label>Status
          <select name="status" required>
            <?php foreach ($statuses as $st): ?>
              <?php $label = ucwords(str_replace('_',' ', $st)); ?>
              <option value="<?= s($st) ?>" <?= $st === $current ? 'selected' : '' ?>><?= s($label) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <div><strong>Scheduled:</strong> <?= s((string)($job['scheduled_date'] ?? '')) ?> <?= s((string)($job['scheduled_time'] ?? '')) ?></div>
        <div><strong>Job Types:</strong> <?= s(implode(', ', array_column($jobTypes, 'name'))) ?></div>
      </fieldset>
      <button type="submit">Save Changes</button>
    </form>
  <?php endif; ?>

</body>
</html>
