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

$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : 'id';
$dir  = isset($_GET['dir']) && strtolower((string)$_GET['dir']) === 'desc' ? 'desc' : 'asc';

$baseParams = ['q' => $q, 'city' => $city, 'state' => $state, 'limit' => $limit];

/** Build sortable link */
function sort_link(string $label, string $column, array $params, string $sort, string $dir): string {
    $nextDir = ($sort === $column && $dir === 'asc') ? 'desc' : 'asc';
    $query   = array_merge($params, ['sort' => $column, 'dir' => $nextDir]);
    $qs      = http_build_query(array_filter($query, fn($v) => $v !== null && $v !== ''));
    $arrow   = '';
    if ($sort === $column) {
        $arrow = $dir === 'asc' ? ' ▲' : ' ▼';
    }
    return '<a href="/customers.php?' . s($qs) . '">' . s($label) . $arrow . '</a>';
}
$rows = CustomerDataProvider::getFiltered($pdo, $q, $city, $state, $limit, $sort, $dir);
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
            <th><?= sort_link('ID', 'id', $baseParams, $sort, $dir) ?></th>
            <th><?= sort_link('Name', 'name', $baseParams, $sort, $dir) ?></th>
            <th><?= sort_link('Email', 'email', $baseParams, $sort, $dir) ?></th>
            <th><?= sort_link('Phone', 'phone', $baseParams, $sort, $dir) ?></th>
            <th><?= sort_link('City', 'city', $baseParams, $sort, $dir) ?></th>
            <th><?= sort_link('State', 'state', $baseParams, $sort, $dir) ?></th>
            <th>Address</th>
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
            <td><?php
              $parts = [
                  $r['address_line1'] ?? null,
                  $r['address_line2'] ?? null,
                  $r['city'] ?? null,
                  $r['state'] ?? null,
                  $r['postal_code'] ?? null,
                  $r['country'] ?? null,
              ];
              $parts = array_filter($parts, fn($v) => $v !== null && $v !== '');
              echo s(implode(', ', $parts));
            ?></td>
            <td class="text-end">
              <a href="/edit_customer.php?id=<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
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
