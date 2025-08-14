<?php
// /public/employees.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/EmployeeDataProvider.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo  = getPDO();
$rows = EmployeeDataProvider::getFiltered($pdo, null);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0 me-2">Employees</h1>
  </div>
  <div class="mb-3">
    <input type="search" id="employee-search" class="form-control form-control-sm" placeholder="Search employees">
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0" id="employees-table">
        <thead class="table-light">
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
    </div>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  function ready(fn){document.readyState!='loading'?fn():document.addEventListener('DOMContentLoaded',fn);}
  ready(() => {
    const search=document.getElementById('employee-search');
    const rows=[...document.querySelectorAll('#employees-table tbody tr')];
    function filter(){
      const q=search.value.toLowerCase();
      rows.forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';});
    }
    search.addEventListener('input',filter);
  });
})();
</script>
</body>
</html>
