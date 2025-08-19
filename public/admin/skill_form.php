<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Skill.php';
require_once __DIR__ . '/../_csrf.php';

$pdo = getPDO();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$skill = $id > 0 ? Skill::find($pdo, $id) : null;
$__csrf = csrf_token();

function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title><?= $id > 0 ? 'Edit Skill' : 'Add Skill' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <h1 class="h4 mb-4"><?= $id > 0 ? 'Edit Skill' : 'Add Skill' ?></h1>
  <?php if ($id > 0 && !$skill): ?>
    <p>Skill not found.</p>
  <?php else: ?>
  <form method="post" action="skill_save.php" autocomplete="off" class="needs-validation" novalidate>
    <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
    <?php if ($id > 0): ?><input type="hidden" name="id" value="<?= (int)$id ?>"><?php endif; ?>
    <div class="mb-3">
      <label for="name" class="form-label">Name</label>
      <input type="text" id="name" name="name" class="form-control" value="<?= s($skill['name'] ?? '') ?>" required>
      <div class="invalid-feedback">Name is required.</div>
    </div>
    <button type="submit" class="btn btn-primary">Save</button>
    <a href="skill_list.php" class="btn btn-secondary ms-2">Cancel</a>
  </form>
  <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  'use strict';
  const forms = document.querySelectorAll('.needs-validation');
  Array.from(forms).forEach(form => {
    form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault();
        event.stopPropagation();
      }
      form.classList.add('was-validated');
    }, false);
  });
})();
</script>
</body>
</html>
