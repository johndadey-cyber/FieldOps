<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

$pdo = getPDO();
$__csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Set Employee Availability</title>
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

  <h1>Weekly Availability</h1>
  <form method="post" action="availability_save.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
    <label>Employee ID
      <input type="number" name="employee_id" value="<?= s(sticky('employee_id')) ?>" required min="1">
    </label>
    <fieldset>
      <legend>Day & Time Window</legend>
      <label>Day of Week
        <select name="day_of_week" required>
          <?php $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday']; $sel = sticky('day_of_week'); foreach ($days as $d): ?>
            <option value="<?= s($d) ?>" <?= $sel === $d ? 'selected' : '' ?>><?= s($d) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Start Time
        <input type="time" name="start_time" value="<?= s(sticky('start_time', '09:00')) ?>" required>
      </label>
      <label>End Time
        <input type="time" name="end_time" value="<?= s(sticky('end_time', '17:00')) ?>" required>
      </label>
    </fieldset>
    <button type="submit">Add Window</button>
  </form>

</body>
</html>
