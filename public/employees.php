<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/EmployeeDataProvider.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo   = getPDO();
$skill = isset($_GET['skill']) ? (string)$_GET['skill'] : null;

$rows = EmployeeDataProvider::getFiltered($pdo, $skill);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <h1>Employees</h1>

  <form method="get" action="">
    <label>Filter by Skill
      <input type="text" name="skill" value="<?= s($skill) ?>">
    </label>
    <button type="submit">Apply</button>
  </form>

  <table border="1" cellpadding="6" cellspacing="0">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Skills</th>
        <th>Active</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['employee_id'] ?></td>
        <td><?= s($r['first_name'] . ' ' . $r['last_name']) ?></td>
        <td><?= s($r['skills']) ?></td>
        <td><?= (int)$r['is_active'] ? 'Yes' : 'No' ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
