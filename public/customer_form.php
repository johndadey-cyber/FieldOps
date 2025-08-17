<?php
declare(strict_types=1);

require_once __DIR__ . '/_csrf.php';
require_once __DIR__ . '/../config/api_keys.php';

$mode      = $mode ?? 'add';
$customer  = $customer ?? [];
$isEdit    = $mode === 'edit';
$__csrf    = csrf_token();

/** HTML escape */
function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/** Sticky helper */
function sticky(string $name, ?string $default = null): string {
    global $customer;
    $v = $_POST[$name] ?? $_GET[$name] ?? $customer[$name] ?? $default ?? '';
    return is_string($v) ? $v : (string)$v;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $isEdit ? 'Edit Customer' : 'Add Customer' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
  <div class="container mt-4">
    <h1 class="mb-4"><?= $isEdit ? 'Edit Customer' : 'Add Customer' ?></h1>
    <div id="form-errors" class="text-danger mb-3"></div>
    <form id="customerForm" method="post" action="customer_save.php" autocomplete="off" class="needs-validation" novalidate data-mode="<?= $isEdit ? 'edit' : 'add' ?>">
      <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
      <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= s((string)($customer['id'] ?? '')) ?>">
      <?php endif; ?>

      <fieldset class="row g-3">
        <legend class="col-12">Contact Information</legend>
        <div class="col-md-6">
          <label class="form-label" for="first_name">First Name <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="first_name" name="first_name" maxlength="50" value="<?= s(sticky('first_name', $customer['first_name'] ?? '')) ?>" required aria-required="true">
          <div class="invalid-feedback">First name is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="last_name">Last Name <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="text" class="form-control" id="last_name" name="last_name" maxlength="50" value="<?= s(sticky('last_name', $customer['last_name'] ?? '')) ?>" required aria-required="true">
          <div class="invalid-feedback">Last name is required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="company">Company</label>
          <input type="text" class="form-control" id="company" name="company" maxlength="255" value="<?= s(sticky('company', $customer['company'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="email">Email</label>
          <input type="email" class="form-control" id="email" name="email" value="<?= s(sticky('email', $customer['email'] ?? '')) ?>">
          <div class="invalid-feedback">Valid email required.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="phone">Phone <span class="text-danger">*</span><span class="visually-hidden"> required</span></label>
          <input type="tel" class="form-control" id="phone" name="phone" value="<?= s(sticky('phone', $customer['phone'] ?? '')) ?>" required aria-required="true" placeholder="(xxx) xxx-xxxx" maxlength="14">
          <div class="invalid-feedback">Valid phone is required.</div>
        </div>
      </fieldset>

      <fieldset class="row g-3">
        <legend class="col-12">Address</legend>
        <div class="col-md-6">
          <label class="form-label" for="address_line1">Address Line 1</label>
          <input type="text" class="form-control" id="address_line1" name="address_line1" value="<?= s(sticky('address_line1', $customer['address_line1'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="address_line2">Address Line 2</label>
          <input type="text" class="form-control" id="address_line2" name="address_line2" value="<?= s(sticky('address_line2', $customer['address_line2'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="city">City</label>
          <input type="text" class="form-control" id="city" name="city" value="<?= s(sticky('city', $customer['city'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="state">State</label>
          <input type="text" class="form-control" id="state" name="state" value="<?= s(sticky('state', $customer['state'] ?? '')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label" for="postal_code">Postal Code</label>
          <input type="text" class="form-control" id="postal_code" name="postal_code" value="<?= s(sticky('postal_code', $customer['postal_code'] ?? '')) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label" for="country">Country</label>
          <input type="text" class="form-control" id="country" name="country" value="<?= s(sticky('country', $customer['country'] ?? '')) ?>">
        </div>
        <input type="hidden" id="google_place_id" name="google_place_id" value="<?= s(sticky('google_place_id', $customer['google_place_id'] ?? '')) ?>">
        <input type="hidden" id="latitude" name="latitude" value="<?= s(sticky('latitude', isset($customer['latitude']) ? (string)$customer['latitude'] : '')) ?>">
        <input type="hidden" id="longitude" name="longitude" value="<?= s(sticky('longitude', isset($customer['longitude']) ? (string)$customer['longitude'] : '')) ?>">
      </fieldset>

      <fieldset class="row g-3">
        <legend class="col-12">Notes</legend>
        <div class="col-12">
          <label class="form-label" for="notes">Notes</label>
          <textarea class="form-control" id="notes" name="notes" rows="4" maxlength="1000"><?= s(sticky('notes', $customer['notes'] ?? '')) ?></textarea>
        </div>
      </fieldset>

      <div class="mt-3">
        <button type="submit" class="btn btn-primary"><?= $isEdit ? 'Save Changes' : 'Save Customer' ?></button>
        <button type="button" class="btn btn-secondary" onclick="window.location.href='customers.php'">Cancel</button>
      </div>
    </form>
  </div>
  <script src="https://maps.googleapis.com/maps/api/js?key=<?= htmlspecialchars(MAPS_API_KEY, ENT_QUOTES, 'UTF-8') ?>&libraries=places"></script>
  <script src="js/google_address_autocomplete.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      initializeAddressAutocomplete('address_line1', {
        latitude: 'latitude',
        longitude: 'longitude'
      });

      const phoneEl = document.getElementById('phone');
      if (phoneEl) {
        phoneEl.addEventListener('input', function (e) {
          const digits = e.target.value.replace(/\D/g, '').slice(0, 10);
          let formatted = digits;
          if (digits.length > 6) {
            formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
          } else if (digits.length > 3) {
            formatted = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
          } else if (digits.length > 0) {
            formatted = '(' + digits;
          }
          e.target.value = formatted;
          const valid = digits.length === 10;
          e.target.classList.toggle('is-invalid', !valid);
          e.target.setCustomValidity(valid ? '' : 'Invalid phone number');
        });
      }

      const form = document.getElementById('customerForm');
      if (form) {
        form.addEventListener('submit', async function (event) {
          event.preventDefault();
          event.stopPropagation();

          if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
          }

          form.classList.add('was-validated');

          const formData = new FormData(form);
          try {
            const res = await fetch(form.action, {
              method: 'POST',
              body: formData,
              headers: { 'Accept': 'application/json' }
            });

            if (!res.ok) throw new Error('Network response was not ok');

            const data = await res.json();
            if (data.ok) {
              window.location.href = 'customers.php?ts=' + Date.now();
            } else if (data.errors) {
              document.getElementById('form-errors').textContent = data.errors.join(', ');
            } else if (data.error) {
              document.getElementById('form-errors').textContent = data.error;
            } else {
              document.getElementById('form-errors').textContent = 'Save failed';
            }
          } catch (err) {
            document.getElementById('form-errors').textContent = 'Save failed';
          }
        });
      }
    });
  </script>
</body>
</html>

