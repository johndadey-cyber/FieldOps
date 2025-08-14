<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

session_start();

// Simple RBAC gate (optional UI check; endpoint enforces RBAC too)
$role = $_SESSION['role'] ?? 'guest';
if ($role !== 'dispatcher') {
    http_response_code(403);
    ?>
    <!doctype html>
    <html lang="en"><head><meta charset="utf-8"><title>Forbidden</title></head>
    <body><div class="container mt-4"><h1>Forbidden</h1><p>You must be a dispatcher to add customers.</p></div></body></html>
    <?php
    exit;
}

// Flash handling
$flash = $_SESSION['flash'] ?? [];
$errors = $flash['errors'] ?? [];
$old    = $flash['old']    ?? [];
$success= $flash['success'] ?? null;
// Clear flash (one-time)
unset($_SESSION['flash']);

function h(?string $v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Add Customer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- If your layout already loads Bootstrap, you can omit CSS/JS here -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container py-4">
  <h1 class="mb-3">Add Customer</h1>

  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <?php if (!empty($errors['_global'] ?? '')): ?>
    <div class="alert alert-danger"><?= h($errors['_global']) ?></div>
  <?php endif; ?>

  <form method="POST" action="/add_customer.php" class="needs-validation" novalidate>
    <?php require __DIR__ . '/../partials/csrf_input.php'; ?>

    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label">First Name <span class="text-danger">*</span></label>
        <input name="first_name" class="form-control <?= isset($errors['first_name']) ? 'is-invalid' : '' ?>" value="<?= h($old['first_name'] ?? '') ?>" required>
        <?php if (isset($errors['first_name'])): ?><div class="invalid-feedback"><?= h($errors['first_name']) ?></div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label">Last Name <span class="text-danger">*</span></label>
        <input name="last_name" class="form-control <?= isset($errors['last_name']) ? 'is-invalid' : '' ?>" value="<?= h($old['last_name'] ?? '') ?>" required>
        <?php if (isset($errors['last_name'])): ?><div class="invalid-feedback"><?= h($errors['last_name']) ?></div><?php endif; ?>
      </div>

      <div class="col-md-6">
        <label class="form-label">Email</label>
        <input name="email" type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" value="<?= h($old['email'] ?? '') ?>">
        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= h($errors['email']) ?></div><?php endif; ?>
      </div>
      <div class="col-md-6">
        <label class="form-label">Phone <span class="text-danger">*</span></label>
        <input name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" value="<?= h($old['phone'] ?? '') ?>" required>
        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= h($errors['phone']) ?></div><?php endif; ?>
      </div>

      <!-- Address block (optional fields; supports Google Autocomplete if you enable it) -->
      <div class="col-12">
        <label class="form-label">Address Line 1</label>
        <input id="address_line1" name="address_line1" class="form-control" value="<?= h($old['address_line1'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label">Address Line 2</label>
        <input id="address_line2" name="address_line2" class="form-control" value="<?= h($old['address_line2'] ?? '') ?>">
      </div>

      <div class="col-md-4">
        <label class="form-label">City</label>
        <input id="city" name="city" class="form-control" value="<?= h($old['city'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">State</label>
        <input id="state" name="state" class="form-control" value="<?= h($old['state'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label">Postal Code</label>
        <input id="postal_code" name="postal_code" class="form-control" value="<?= h($old['postal_code'] ?? '') ?>">
      </div>

      <div class="col-md-6">
        <label class="form-label">Country</label>
        <input id="country" name="country" class="form-control" value="<?= h($old['country'] ?? '') ?>">
      </div>

      <!-- Hidden fields for Google Places (optional) -->
      <input type="hidden" id="google_place_id" name="google_place_id" value="<?= h($old['google_place_id'] ?? '') ?>">
      <input type="hidden" id="latitude" name="latitude" value="<?= h($old['latitude'] ?? '') ?>">
      <input type="hidden" id="longitude" name="longitude" value="<?= h($old['longitude'] ?? '') ?>">

      <div class="col-12 d-flex gap-2">
        <button class="btn btn-primary" type="submit">Save Customer</button>
        <a href="/customers.php" class="btn btn-outline-secondary">Cancel</a>
      </div>
    </div>
  </form>
</div>

<script>
// Basic client-side validation (Bootstrap 5)
(() => {
  const forms = document.querySelectorAll('.needs-validation');
  Array.prototype.slice.call(forms).forEach(form => {
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault(); event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>
