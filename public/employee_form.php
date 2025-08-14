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
  <title>Add Employee</title>
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

  <h1>Add Employee</h1>
  <form method="post" action="employee_save.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
    <fieldset>
      <legend>Profile</legend>
      <label>First Name
        <input type="text" name="first_name" value="<?= s(sticky('first_name')) ?>" required>
      </label>
      <label>Last Name
        <input type="text" name="last_name" value="<?= s(sticky('last_name')) ?>" required>
      </label>
      <label>Email
        <input type="email" name="email" value="<?= s(sticky('email')) ?>">
      </label>
      <label>Phone
        <input type="tel" name="phone" value="<?= s(sticky('phone')) ?>">
      </label>
      <label>
        <input type="checkbox" name="is_active" value="1" <?= sticky('is_active') ? 'checked' : '' ?>>
        Active
      </label>
    </fieldset>
    <button type="submit">Save Employee</button>
  </form>

</body>
</html>
