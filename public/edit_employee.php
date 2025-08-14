<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../models/Employee.php';
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$employee = $id > 0 ? Employee::getById(getPDO(), $id) : null;
$first    = $employee['first_name'] ?? '';
$last     = $employee['last_name'] ?? '';
$isActive = isset($employee['is_active']) ? (int)$employee['is_active'] : 1;

$pdo = getPDO();
$__csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Employee</title>
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

  <h1>Edit Employee</h1>
  <?php if (!$employee): ?>
    <p>Employee not found.</p>
  <?php else: ?>
    <form method="post" action="employee_save.php" autocomplete="off">
      <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
      <input type="hidden" name="id" value="<?= (int)$id ?>">
      <fieldset>
        <legend>Profile</legend>
        <label>First Name
          <input type="text" name="first_name" value="<?= s(sticky('first_name', $first)) ?>" required>
        </label>
        <label>Last Name
          <input type="text" name="last_name" value="<?= s(sticky('last_name', $last)) ?>" required>
        </label>
        <label>
          <input type="checkbox" name="is_active" value="1" <?= ($isActive ? 'checked' : '') ?>>
          Active
        </label>
      </fieldset>
      <button type="submit">Save Changes</button>
    </form>
  <?php endif; ?>

</body>
</html>
