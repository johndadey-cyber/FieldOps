<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../models/Customer.php';
$customerModel = new Customer(getPDO());
$customers = $customerModel->getAll();
require_once __DIR__ . '/../models/Job.php';
$statuses = Job::allowedStatuses();

$pdo = getPDO();
$__csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Create Job</title>
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

  <h1>Create Job</h1>
  <form method="post" action="job_save.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
    <fieldset>
      <legend>Basics</legend>
      <label>Customer
        <select name="customer_id" required>
          <option value="">-- Select --</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?= (int)$c['id'] ?>"><?= s($c['first_name'] . ' ' . $c['last_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Description
        <input type="text" name="description" required>
      </label>
      <label>Status
        <select name="status" required>
          <?php foreach ($statuses as $st): ?>
            <option value="<?= s($st) ?>"><?= s($st) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </fieldset>
    <fieldset>
      <legend>Schedule</legend>
      <label>Date
        <input type="date" name="scheduled_date" required>
      </label>
      <label>Time
        <input type="time" name="scheduled_time" required>
      </label>
      <label>Duration (minutes)
        <input type="number" name="duration_minutes" min="0" step="5" value="60">
      </label>
    </fieldset>
    <button type="submit">Save Job</button>
  </form>

</body>
</html>
