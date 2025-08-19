<?php
declare(strict_types=1);
require __DIR__ . '/../_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/../_auth.php';
require_role('admin');
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Skill.php';
require_once __DIR__ . '/../_csrf.php';

$pdo = getPDO();
/** @var list<array{id:int|string,name:string}> $skills */
$skills = Skill::all($pdo);
$__csrf = csrf_token();

function s(?string $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Skills</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0">Skills</h1>
    <a href="skill_form.php" class="btn btn-sm btn-primary ms-auto">Add Skill</a>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0">
        <thead class="table-light">
          <tr><th>ID</th><th>Name</th><th class="text-end">Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($skills as $sk): ?>
          <tr>
            <td><?= (int)$sk['id'] ?></td>
            <td><?= s($sk['name']) ?></td>
            <td class="text-end">
              <a href="skill_form.php?id=<?= (int)$sk['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
              <form method="post" action="skill_save.php" class="d-inline" onsubmit="return confirm('Delete this skill?');">
                <input type="hidden" name="csrf_token" value="<?= s($__csrf) ?>">
                <input type="hidden" name="id" value="<?= (int)$sk['id'] ?>">
                <input type="hidden" name="delete" value="1">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body>
</html>
