<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/CustomerDataProvider.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo   = getPDO();
$q     = isset($_GET['q']) ? (string)$_GET['q'] : null;
$city  = isset($_GET['city']) ? (string)$_GET['city'] : null;
$state = isset($_GET['state']) ? (string)$_GET['state'] : null;
$limit = isset($_GET['limit']) ? (string)$_GET['limit'] : null;

$rows = CustomerDataProvider::getFiltered($pdo, $q, $city, $state, $limit);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Customers</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0">Customers</h1>
    <a href="/customer_form.php" class="btn btn-sm btn-primary ms-auto">Add Customer</a>
  </div>

  <form class="mb-3" method="get">
    <input type="search" name="q" value="<?= s($q) ?>" class="form-control form-control-sm" placeholder="Search customers">
  </form>

  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>City</th>
            <th>State</th>
            <th>Short Address</th>
            <th class="text-end">Actions</th>
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
            <td class="text-end">
              <a href="/customer_form.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$rows): ?>
          <tr><td colspan="8" class="text-center text-muted py-4">No customers found.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php require_once __DIR__ . '/../partials/flash_toast.php'; ?>
</body>
</html>
