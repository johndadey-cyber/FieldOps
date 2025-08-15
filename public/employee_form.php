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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/employee_form.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/inputmask@5/dist/inputmask.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
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
  <div class="container mt-4">
    <h1 class="mb-4">Add Employee</h1>
    <div id="form-errors" class="text-danger mb-3"></div>
    <form id="employeeForm" method="post" action="employee_save.php" autocomplete="off" class="needs-validation" novalidate>
      <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">

      <fieldset class="row g-3">
        <legend class="col-12">Personal Information</legend>
        <div class="col-md-6">
          <label class="form-label" for="first_name">First Name <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="first_name" name="first_name" maxlength="50" value="<?= s(sticky('first_name')) ?>" required aria-required="true">
          <div class="invalid-feedback">First name is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="last_name" name="last_name" maxlength="50" value="<?= s(sticky('last_name')) ?>" required aria-required="true">
          <div class="invalid-feedback">Last name is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">Email <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="email" class="form-control" id="email" name="email" value="<?= s(sticky('email')) ?>" required aria-required="true">
          <div class="invalid-feedback">Valid email is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">Phone <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="tel" class="form-control" id="phone" name="phone" value="<?= s(sticky('phone')) ?>" required aria-required="true" pattern="\\(\\d{3}\\)[\\s\\u00A0]\\d{3}-\\d{4}" placeholder="(123) 456-7890" title="Enter a 10-digit phone number" inputmode="tel" data-phone-mask="(999) 999-9999">
          <div class="invalid-feedback">Valid phone is required.</div>
        </div>
        </fieldset>

      <fieldset class="row g-3">
        <legend class="col-12">Contact &amp; Address</legend>
        <div class="col-md-6">
          <label class="form-label" for="address_line1">Address Line 1 <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?= s(sticky('address_line1')) ?>" required aria-required="true">
          <div class="invalid-feedback">Address line 1 is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="address_line2">Address Line 2</label>
          <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= s(sticky('address_line2')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="city">City <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="city" name="city" value="<?= s(sticky('city')) ?>" required aria-required="true">
          <div class="invalid-feedback">City is required.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="state">State <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="state" name="state" value="<?= s(sticky('state')) ?>" required aria-required="true">
          <div class="invalid-feedback">State is required.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="postal_code">Postal Code <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= s(sticky('postal_code')) ?>" required aria-required="true">
          <div class="invalid-feedback">Postal code is required.</div>
        </div>
        <input type="hidden" id="home_address_lat" name="home_address_lat" value="<?= s(sticky('home_address_lat')) ?>">
        <input type="hidden" id="home_address_lon" name="home_address_lon" value="<?= s(sticky('home_address_lon')) ?>">
        <input type="hidden" id="google_place_id" name="google_place_id" value="<?= s(sticky('google_place_id')) ?>">
      </fieldset>

      <fieldset class="row g-3">
        <legend class="col-12">Employment Details</legend>
        <div class="col-md-6">
          <label class="form-label" for="employment_type">Employment Type <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <select class="form-select" id="employment_type" name="employment_type" required aria-required="true">
            <?php $et = sticky('employment_type'); ?>
            <option value="">-- Select --</option>
            <option value="Full-Time" <?= $et==='Full-Time'? 'selected':''; ?>>Full-Time</option>
            <option value="Part-Time" <?= $et==='Part-Time'? 'selected':''; ?>>Part-Time</option>
            <option value="Contractor" <?= $et==='Contractor'? 'selected':''; ?>>Contractor</option>
          </select>
          <div class="invalid-feedback">Employment type is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="hire_date">Hire Date <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="date" class="form-control" id="hire_date" name="hire_date" value="<?= s(sticky('hire_date', date('Y-m-d'))) ?>" required aria-required="true">
          <div class="invalid-feedback">Hire date is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="status">Status <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <?php $st = sticky('status', 'Active'); ?>
          <select class="form-select" id="status" name="status" required aria-required="true">
            <option value="Active" <?= $st==='Active'? 'selected':''; ?>>Active</option>
            <option value="Inactive" <?= $st==='Inactive'? 'selected':''; ?>>Inactive</option>
          </select>
          <div class="invalid-feedback">Status is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">Role
            <select class="form-select" name="role_id">
              <?php $selRole = sticky('role_id'); ?>
              <option value="">-- Select --</option>
              <?php foreach ($roles as $role): ?>
                <?php $rid = (string)$role['id']; $rname = (string)$role['name']; ?>
                <option value="<?= s($rid) ?>" <?= $selRole===$rid ? 'selected' : '' ?>><?= s($rname) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </fieldset>

      <fieldset class="row g-3">
        <legend class="col-12">Skills</legend>
        <?php $selSkills = stickyArr('skills'); ?>
        <div class="col-12">
          <label class="form-label">Select Skills
            <select class="form-select" id="skills" name="skills[]" multiple>
              <?php foreach ($skills as $sk): ?>
                <?php $id = (string)$sk['id']; $name = (string)$sk['name']; ?>
                <option value="<?= s($id) ?>" <?= in_array($id, $selSkills, true) ? 'selected' : '' ?>><?= s($name) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
      </fieldset>

      <div class="mt-3">
        <button type="submit" class="btn btn-primary">Save Employee</button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='employees.php'">Cancel</button>
      </div>
    </form>
  </div>
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
