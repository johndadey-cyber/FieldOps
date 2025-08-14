<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/CustomerDataProvider.php';
require_once __DIR__ . '/../partials/flash_toast.php'; ?>


/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo = getPDO();
$q     = isset($_GET['q']) ? (string)$_GET['q'] : null;
$city  = isset($_GET['city']) ? (string)$_GET['city'] : null;
$state = isset($_GET['state']) ? (string)$_GET['state'] : null;
$limit = isset($_GET['limit']) ? (string)$_GET['limit'] : null;

$rows = CustomerDataProvider::getFiltered($pdo, $q, $city, $state, $limit);
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Customers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Your app likely includes Bootstrap 5 -->
</head>
<body>
  <h1>Customers</h1>

  <table border="1" cellpadding="6" cellspacing="0">
    <thead>
      <tr>
        <th>ID</th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>City</th>
        <th>State</th>
        <th>Short Address</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= s($r['first_name'] . ' ' . $r['last_name']) ?></td>
        <td><?= s($r['email'] ?? '') ?></td>
        <td><?= s($r['phone'] ?? '') ?></td>
        <td><?= s($r['city'] ?? '') ?></td>
        <td><?= s($r['state'] ?? '') ?></td>
        <td><?= s(trim(($r['city'] ?? '') . ', ' . ($r['state'] ?? ''), ' ,')) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
