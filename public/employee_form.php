<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/JobType.php';
require_once __DIR__ . '/../models/Role.php';
require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../config/api_keys.php';

$pdo   = getPDO();
$__csrf = csrf_token();

// Fetch job types to use as skills (id,name)
/** @var list<array{id:int|string,name:string}> $skills */
$skills = JobType::all($pdo);

// Fetch available roles
/** @var list<array{id:int|string,name:string}> $roles */
$roles = Role::all($pdo);
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
function stickyArr(string $name): array {
    $v = $_POST[$name] ?? [];
    return is_array($v) ? $v : [];
}
?>
  <h1>Add Employee</h1>
  <div id="form-errors" style="color:red"></div>
  <form id="employeeForm" method="post" action="employee_save.php" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">

    <fieldset>
      <legend>Personal Information</legend>
      <label>First Name
        <input type="text" name="first_name" maxlength="50" value="<?= s(sticky('first_name')) ?>" required>
      </label>
      <label>Last Name
        <input type="text" name="last_name" maxlength="50" value="<?= s(sticky('last_name')) ?>" required>
      </label>
      <label>Email
        <input type="email" name="email" value="<?= s(sticky('email')) ?>" required>
      </label>
      <label>Phone
        <input type="tel" name="phone" value="<?= s(sticky('phone')) ?>" required pattern="\d{3}[\s-]?\d{3}[\s-]?\d{4}" placeholder="123-456-7890" title="Enter a 10-digit phone number">
      </label>
    </fieldset>

    <fieldset>
      <legend>Contact &amp; Address</legend>
      <label>Address Line 1
        <input type="text" id="address_line1" name="address_line1" value="<?= s(sticky('address_line1')) ?>" required>
      </label>
      <label>Address Line 2
        <input type="text" id="address_line2" name="address_line2" value="<?= s(sticky('address_line2')) ?>">
      </label>
      <label>City
        <input type="text" id="city" name="city" value="<?= s(sticky('city')) ?>" required>
      </label>
      <label>State
        <input type="text" id="state" name="state" value="<?= s(sticky('state')) ?>" required>
      </label>
      <label>Postal Code
        <input type="text" id="postal_code" name="postal_code" value="<?= s(sticky('postal_code')) ?>" required>
      </label>
      <input type="hidden" id="home_address_lat" name="home_address_lat" value="<?= s(sticky('home_address_lat')) ?>">
      <input type="hidden" id="home_address_lon" name="home_address_lon" value="<?= s(sticky('home_address_lon')) ?>">
      <input type="hidden" id="google_place_id" name="google_place_id" value="<?= s(sticky('google_place_id')) ?>">
    </fieldset>

    <fieldset>
      <legend>Employment Details</legend>
      <label>Employment Type
        <select name="employment_type" required>
          <?php $et = sticky('employment_type'); ?>
          <option value="">-- Select --</option>
          <option value="Full-Time" <?= $et==='Full-Time'? 'selected':''; ?>>Full-Time</option>
          <option value="Part-Time" <?= $et==='Part-Time'? 'selected':''; ?>>Part-Time</option>
          <option value="Contractor" <?= $et==='Contractor'? 'selected':''; ?>>Contractor</option>
        </select>
      </label>
      <label>Hire Date
        <input type="date" name="hire_date" value="<?= s(sticky('hire_date', date('Y-m-d'))) ?>" required>
      </label>
      <label>Status
        <?php $st = sticky('status', 'Active'); ?>
        <select name="status" required>
          <option value="Active" <?= $st==='Active'? 'selected':''; ?>>Active</option>
          <option value="Inactive" <?= $st==='Inactive'? 'selected':''; ?>>Inactive</option>
        </select>
      </label>
      <label>Role
        <select name="role_id">
          <?php $selRole = sticky('role_id'); ?>
          <option value="">-- Select --</option>
          <?php foreach ($roles as $role): ?>
            <?php $rid = (string)$role['id']; $rname = (string)$role['name']; ?>
            <option value="<?= s($rid) ?>" <?= $selRole===$rid ? 'selected' : '' ?>><?= s($rname) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </fieldset>

    <fieldset>
      <legend>Skills</legend>
      <?php $selSkills = stickyArr('skills'); ?>
      <?php foreach ($skills as $sk): ?>
        <?php $id = (int)$sk['id']; $name = (string)$sk['name']; ?>
        <label>
          <input type="checkbox" name="skills[]" value="<?= $id ?>" <?= in_array((string)$id, $selSkills, true) ? 'checked' : '' ?>>
          <?= s($name) ?>
        </label>
      <?php endforeach; ?>
    </fieldset>

    <button type="submit">Save Employee</button>
    <button type="button" onclick="window.location.href='employees.php'">Cancel</button>
  </form>
  <script src="js/employee_form.js"></script>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(MAPS_API_KEY, ENT_QUOTES, 'UTF-8') ?>&libraries=places"></script>
  <script src="js/google_address_autocomplete.js"></script>
  <script>
  document.addEventListener('DOMContentLoaded', function () {
      initializeAddressAutocomplete('address_line1', {
          latitude: 'home_address_lat',
          longitude: 'home_address_lon'
      });
  });
  </script>
</body>
</html>
