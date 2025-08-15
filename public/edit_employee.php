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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$employee = null;
$skillIds = [];
if ($id > 0) {
    $st = $pdo->prepare(
        'SELECT e.id, e.person_id, e.employment_type, e.hire_date, e.status, e.role_id,
                p.first_name, p.last_name, p.email, p.phone,
                p.address_line1, p.address_line2, p.city, p.state, p.postal_code,
                p.google_place_id, p.latitude, p.longitude
         FROM employees e
         JOIN people p ON p.id = e.person_id
         WHERE e.id = :id'
    );
    if ($st) {
        $st->execute([':id' => $id]);
        $employee = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($employee) {
            // employee_skills stores job_type_id; fetch those ids for the form
            $st2 = $pdo->prepare('SELECT job_type_id FROM employee_skills WHERE employee_id = :id');
            if ($st2) {
                $st2->execute([':id' => $id]);
                $skillIds = array_map('strval', $st2->fetchAll(PDO::FETCH_COLUMN));
            }
        }
    }
}

/** @var list<array{id:int|string,name:string}> $skills */
$skills = JobType::all($pdo);
/** @var list<array{id:int|string,name:string}> $roles */
$roles = Role::all($pdo);
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
function stickyArr(string $name, array $default = []): array {
    $v = $_POST[$name] ?? $default;
    return is_array($v) ? $v : [];
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
        <legend>Personal Information</legend>
        <label>First Name
          <input type="text" name="first_name" maxlength="50" value="<?= s(sticky('first_name', $employee['first_name'] ?? '')) ?>" required>
        </label>
        <label>Last Name
          <input type="text" name="last_name" maxlength="50" value="<?= s(sticky('last_name', $employee['last_name'] ?? '')) ?>" required>
        </label>
        <label>Email
          <input type="email" name="email" value="<?= s(sticky('email', $employee['email'] ?? '')) ?>" required>
        </label>
        <label>Phone
          <input type="tel" name="phone" value="<?= s(sticky('phone', $employee['phone'] ?? '')) ?>" required>
        </label>
      </fieldset>

      <fieldset>
        <legend>Contact &amp; Address</legend>
        <label>Address Line 1
          <input type="text" id="address_line1" name="address_line1" value="<?= s(sticky('address_line1', $employee['address_line1'] ?? '')) ?>" required>
        </label>
        <label>Address Line 2
          <input type="text" id="address_line2" name="address_line2" value="<?= s(sticky('address_line2', $employee['address_line2'] ?? '')) ?>">
        </label>
        <label>City
          <input type="text" id="city" name="city" value="<?= s(sticky('city', $employee['city'] ?? '')) ?>" required>
        </label>
        <label>State
          <input type="text" id="state" name="state" value="<?= s(sticky('state', $employee['state'] ?? '')) ?>" required>
        </label>
        <label>Postal Code
          <input type="text" id="postal_code" name="postal_code" value="<?= s(sticky('postal_code', $employee['postal_code'] ?? '')) ?>" required>
        </label>
        <input type="hidden" id="home_address_lat" name="home_address_lat" value="<?= s(sticky('home_address_lat', isset($employee['latitude']) ? (string)$employee['latitude'] : '')) ?>">
        <input type="hidden" id="home_address_lon" name="home_address_lon" value="<?= s(sticky('home_address_lon', isset($employee['longitude']) ? (string)$employee['longitude'] : '')) ?>">
        <input type="hidden" id="google_place_id" name="google_place_id" value="<?= s(sticky('google_place_id', $employee['google_place_id'] ?? '')) ?>">
      </fieldset>

      <fieldset>
        <legend>Employment Details</legend>
        <label>Employment Type
          <?php $et = sticky('employment_type', $employee['employment_type'] ?? ''); ?>
          <select name="employment_type" required>
            <option value="">-- Select --</option>
            <option value="Full-Time" <?= $et==='Full-Time'? 'selected':''; ?>>Full-Time</option>
            <option value="Part-Time" <?= $et==='Part-Time'? 'selected':''; ?>>Part-Time</option>
            <option value="Contractor" <?= $et==='Contractor'? 'selected':''; ?>>Contractor</option>
          </select>
        </label>
        <label>Hire Date
          <input type="date" name="hire_date" value="<?= s(sticky('hire_date', $employee['hire_date'] ?? '')) ?>" required>
        </label>
        <label>Status
          <?php $st = sticky('status', $employee['status'] ?? 'Active'); ?>
          <select name="status" required>
            <option value="Active" <?= $st==='Active'? 'selected':''; ?>>Active</option>
            <option value="Inactive" <?= $st==='Inactive'? 'selected':''; ?>>Inactive</option>
          </select>
        </label>
        <label>Role
          <select name="role_id">
            <?php $selRole = sticky('role_id', isset($employee['role_id']) ? (string)$employee['role_id'] : ''); ?>
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
        <?php $selSkills = stickyArr('skills', $skillIds); ?>
        <?php foreach ($skills as $sk): ?>
          <?php $sid = (string)$sk['id']; $sname = (string)$sk['name']; ?>
          <label>
            <input type="checkbox" name="skills[]" value="<?= $sid ?>" <?= in_array($sid, $selSkills, true) ? 'checked' : '' ?>>
            <?= s($sname) ?>
          </label>
        <?php endforeach; ?>
      </fieldset>

      <button type="submit">Save Changes</button>
      <button type="button" onclick="window.location.href='employees.php'">Cancel</button>
    </form>
  <?php endif; ?>
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
