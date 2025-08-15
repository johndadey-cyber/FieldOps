<?php
// /public/employees.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/EmployeeDataProvider.php';
require_once __DIR__ . '/../models/JobType.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

$pdo  = getPDO();
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) && ctype_digit((string)$_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 25;
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : null;
$direction = isset($_GET['direction']) ? (string)$_GET['direction'] : null;
$skills = array_values(array_filter(array_map('strval', (array)($_GET['skills'] ?? []))));
$data = EmployeeDataProvider::getFiltered($pdo, $skills, $page, $perPage, $sort, $direction);
$rows = $data['rows'];
$total = $data['total'];
$totalPages = (int)ceil($total / $perPage);
$allSkills = array_map(static fn(array $r): string => (string)$r['name'], JobType::all($pdo));
$skillQuery = '';
foreach ($skills as $s) { $skillQuery .= '&skills[]=' . urlencode($s); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Employees</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
</head>
<body class="bg-light">
<div class="container py-3">
  <div class="d-flex align-items-center mb-3">
    <h1 class="h4 m-0 me-2">Employees</h1>
  </div>
  <div class="mb-3">
    <select id="skill-filter" class="form-select" multiple>
      <?php foreach ($allSkills as $sk): ?>
        <option value="<?= s($sk) ?>"<?= in_array($sk, $skills, true) ? ' selected' : '' ?>><?= s($sk) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="mb-3">
    <input type="search" id="employee-search" class="form-control form-control-sm" placeholder="Search employees">
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0" id="employees-table">
        <thead class="table-light">
          <?php
            $idDir = ($sort === 'employee_id' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
            $nameDir = ($sort === 'last_name' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
            $activeDir = ($sort === 'is_active' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
          ?>
          <tr>
            <th><a href="?perPage=<?= $perPage ?>&sort=employee_id&direction=<?= $idDir ?><?= $skillQuery ?>">ID</a></th>
            <th><a href="?perPage=<?= $perPage ?>&sort=last_name&direction=<?= $nameDir ?><?= $skillQuery ?>">Name</a></th>
            <th>Skills</th>
            <th><a href="?perPage=<?= $perPage ?>&sort=is_active&direction=<?= $activeDir ?><?= $skillQuery ?>">Active</a></th>
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
    <?php if ($totalPages > 1): ?>
    <nav class="p-2">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(function(){
  const search=document.getElementById('employee-search');
  const rows=[...document.querySelectorAll('#employees-table tbody tr')];
  function filter(){
    const q=search.value.toLowerCase();
    rows.forEach(row=>{row.style.display=row.textContent.toLowerCase().includes(q)?'':'none';});
  }
  search.addEventListener('input',filter);

  const skillFilter=$('#skill-filter');
  skillFilter.select2({width:'100%'});
  skillFilter.on('change',function(){
    const params=new URLSearchParams(window.location.search);
    params.delete('skills');
    params.delete('skills[]');
    const vals=skillFilter.val()||[];
    vals.forEach(v=>params.append('skills[]',v));
    window.location='?'+params.toString();
  });
});
</script>
</body>
</html>
