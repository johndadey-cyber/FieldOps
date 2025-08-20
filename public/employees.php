<?php
// /public/employees.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
if (!isset($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(16)); }
$CSRF = $_SESSION['csrf_token'];

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/EmployeeDataProvider.php';
require_once __DIR__ . '/../models/Skill.php';
require_once __DIR__ . '/../models/Availability.php';

/** HTML escape */
function s(?string $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

/** Parse CSV "skill|proficiency" string into an array */
function parseSkillProficiencies(string $skills): array {
    $list = [];
    foreach (array_filter(explode(',', $skills)) as $chunk) {
        [$name, $prof] = array_pad(explode('|', $chunk), 2, '');
        $list[] = ['name' => $name, 'proficiency' => $prof];
    }
    return $list;
}

/** Map proficiency values to readable labels */
function formatProficiency(string $p): string {
    $p = strtolower($p);
    if ($p === '1' || $p === 'beginner') { return 'Beginner'; }
    if ($p === '2' || $p === 'intermediate') { return 'Intermediate'; }
    if ($p === '3' || $p === 'expert') { return 'Expert'; }
    return ucfirst($p);
}

$skillClasses = [
    'pressure washing' => 'badge-pressure-washing',
    'window washing'   => 'badge-window-washing',
];

function skillClass(string $name, array $map): string {
    $key = strtolower($name);
    return $map[$key] ?? 'badge-default-skill';
}

function statusBadge(string $status): string {
    $lower = strtolower($status);
    return match ($lower) {
        'active' => '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active</span>',
        'inactive' => '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Inactive</span>',
        'on leave' => '<span class="badge bg-warning text-dark"><i class="bi bi-pause-circle me-1"></i>On Leave</span>',
        default => '<span class="badge bg-secondary">'.s($status).'</span>',
    };
}

function scheduleBadge(string $status): string {
    $lower = strtolower($status);
    return match ($lower) {
        'available' => '<span class="badge bg-success">Available</span>',
        'booked' => '<span class="badge bg-danger">Booked</span>',
        'partially booked' => '<span class="badge bg-warning text-dark">Partially Booked</span>',
        'no hours' => '<span class="badge bg-secondary">No Hours</span>',
        default => '<span class="badge bg-secondary">'.s($status).'</span>',
    };
}

$pdo  = getPDO();
$page = isset($_GET['page']) && ctype_digit((string)$_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = isset($_GET['perPage']) && ctype_digit((string)$_GET['perPage']) ? max(1, (int)$_GET['perPage']) : 25;
$sort = isset($_GET['sort']) ? (string)$_GET['sort'] : null;
$direction = isset($_GET['direction']) ? (string)$_GET['direction'] : null;
$search = isset($_GET['search']) ? (string)$_GET['search'] : null;
$skills = array_values(array_filter(array_map('strval', (array)($_GET['skills'] ?? []))));
$data = EmployeeDataProvider::getFiltered($pdo, $skills, $page, $perPage, $sort, $direction, $search);
$rows = $data['rows'];
$ids = array_map(static fn(array $r): int => (int)$r['employee_id'], $rows);
$todayStatus = Availability::statusForEmployeesOnDate($pdo, $ids, date('Y-m-d'));
// Status now derives from availability plus jobs
foreach ($rows as &$r) {
    $eid = (int)$r['employee_id'];
    $info = $todayStatus[$eid] ?? ['summary' => 'Off', 'status' => 'No Hours'];
    $r['availability'] = $info['summary'];
    $r['schedule_status'] = $info['status'];
}
unset($r);
$total = $data['total'];
$totalPages = (int)ceil($total / $perPage);
$allSkills = array_map(static fn(array $r): string => (string)$r['name'], Skill::all($pdo));
$skillQuery = '';
foreach ($skills as $s) { $skillQuery .= '&skills[]=' . urlencode($s); }
$searchQuery = $search !== null && $search !== '' ? '&search=' . urlencode($search) : '';
?>
$title = 'Employees';
$headExtra = <<<HTML
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="/css/skills.css" rel="stylesheet">
HTML;
require __DIR__ . '/../partials/header.php';
?>

  <div class="mb-3 text-end">
    <a href="add_employee.php" class="btn btn-sm btn-primary">Add New</a>
    <a href="/admin/skill_list.php" class="btn btn-sm btn-secondary ms-2">Skills</a>
  </div>
  <div class="mb-3">
    <select id="skill-filter" class="form-select" multiple>
      <?php foreach ($allSkills as $sk): ?>
        <option value="<?= s($sk) ?>"<?= in_array($sk, $skills, true) ? ' selected' : '' ?>><?= s($sk) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <form id="search-form" class="mb-3">
    <input type="search" id="employee-search" name="search" class="form-control form-control-sm" placeholder="Search employees" value="<?= s($search) ?>">
  </form>
  <div class="mb-3 d-flex">
    <select id="bulk-action" class="form-select form-select-sm w-auto me-2">
      <option value="">Bulk Actions</option>
      <option value="activate">Activate</option>
      <option value="deactivate">Deactivate</option>
    </select>
    <button id="bulk-apply" class="btn btn-sm btn-secondary">Apply</button>
  </div>
  <div class="card">
    <div class="table-responsive">
      <table class="table table-striped table-hover m-0" id="employees-table">
        <thead class="table-light">
          <?php
            $idDir = ($sort === 'employee_id' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
            $nameDir = ($sort === 'last_name' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
            $statusDir = ($sort === 'status' && strtolower((string)$direction) === 'asc') ? 'desc' : 'asc';
          ?>
          <tr>
            <th><input type="checkbox" id="select-all"></th>
            <th><a href="?perPage=<?= $perPage ?>&sort=employee_id&direction=<?= $idDir ?><?= $skillQuery ?><?= $searchQuery ?>">ID</a></th>
            <th><a href="?perPage=<?= $perPage ?>&sort=last_name&direction=<?= $nameDir ?><?= $skillQuery ?><?= $searchQuery ?>">Name</a></th>
            <th>Email</th>
            <th>Phone</th>
            <th>Skills</th>
            <th>Availability</th>
            <th>Today</th>
            <th><a href="?perPage=<?= $perPage ?>&sort=status&direction=<?= $statusDir ?><?= $skillQuery ?><?= $searchQuery ?>">Status</a></th>
            <th>Info</th>
            <th>Edit</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><input type="checkbox" class="emp-check" value="<?= (int)$r['employee_id'] ?>"></td>
            <td><?= (int)$r['employee_id'] ?></td>
            <td><?= s($r['first_name'] . ' ' . $r['last_name']) ?></td>
            <td>
              <?php if (!empty($r['email'])): ?>
                <a href="mailto:<?= s($r['email']) ?>"><?= s($r['email']) ?></a>
              <?php endif; ?>
            </td>
            <td>
              <?php if (!empty($r['phone'])): ?>
                <a href="tel:<?= s($r['phone']) ?>"><?= s($r['phone']) ?></a>
              <?php endif; ?>
            </td>
            <td>
              <?php foreach (parseSkillProficiencies($r['skills']) as $sk):
                  $cls = skillClass($sk['name'], $skillClasses);
                  $prof = $sk['proficiency'] !== '' ? ' (' . formatProficiency($sk['proficiency']) . ')' : '';
              ?>
                <span class="badge <?= s($cls) ?>"><?= s($sk['name'] . $prof) ?></span>
              <?php endforeach; ?>
            </td>
            <td><?= s($r['availability']) ?></td>
            <td><?= scheduleBadge((string)$r['schedule_status']) ?></td>
            <td><?= statusBadge((string)($r['status'] ?? '')) ?></td>
            <td>
              <?php
                $info = '';
                if (!empty($r['email'])) {
                    $e = s($r['email']);
                    $info .= "Email: <a href='mailto:$e'>$e</a>";
                }
                if (!empty($r['phone'])) {
                    $p = s($r['phone']);
                    if ($info !== '') { $info .= '<br>'; }
                    $info .= "Phone: <a href='tel:$p'>$p</a>";
                }
              ?>
              <?php if ($info !== ''): ?>
                <i class="bi bi-info-circle" data-bs-toggle="tooltip" data-bs-html="true" title="<?= $info ?>"></i>
              <?php endif; ?>
            </td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-secondary" href="edit_employee.php?id=<?= (int)$r['employee_id'] ?>">Edit</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <nav class="p-2">
      <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= max(1, $page - 1) ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?><?= $searchQuery ?>">Previous</a>
        </li>
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
        <li class="page-item <?= $i === $page ? 'active' : '' ?>">
          <a class="page-link" href="?page=<?= $i ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?><?= $searchQuery ?>"><?= $i ?></a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
          <a class="page-link" href="?page=<?= min($totalPages, $page + 1) ?>&perPage=<?= $perPage ?>&sort=<?= s($sort) ?>&direction=<?= s($direction) ?><?= $skillQuery ?><?= $searchQuery ?>">Next</a>
        </li>
      </ul>
    </nav>
    <?php endif; ?>
  </div>
  <div class="mt-3 small">
    <strong>Legend:</strong>
    <span class="badge bg-success me-1">Available</span> Available
    <span class="badge bg-secondary ms-3 me-1">No Hours</span> No Hours
    <span class="badge bg-danger ms-3 me-1">Booked</span> Booked
    <span class="badge bg-warning text-dark ms-3 me-1">Partially Booked</span> Partially Booked
  </div>
<?php
$pageScripts = <<<HTML
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="/js/employees.js"></script>
HTML;
ob_start();
?>
<script>
document.addEventListener('DOMContentLoaded', function(){
  const searchForm=document.getElementById('search-form');
  const searchInput=document.getElementById('employee-search');
  searchForm.addEventListener('submit',function(e){
    e.preventDefault();
    const params=new URLSearchParams(window.location.search);
    const val=searchInput.value.trim();
    if(val){params.set('search',val);}else{params.delete('search');}
    window.location='?'+params.toString();
  });

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

  $('#select-all').on('change',function(){
    const checked=this.checked;
    $('.emp-check').prop('checked',checked);
  });

  $('#bulk-apply').on('click',function(){
    const action=$('#bulk-action').val();
    const ids=$('.emp-check:checked').map((_,el)=>el.value).get();
    if(!action||ids.length===0){return;}
    $.post('employee_bulk_update.php',{action:action,ids:ids,csrf_token:'<?= $CSRF ?>'},function(res){
      if(res.ok){location.reload();}else{
        const msg=res.error||'Error';
        if(window.FieldOpsToast){FieldOpsToast.show(msg,'danger');}else{alert(msg);}
      }
    },'json');
  });

  const tooltipTriggerList=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  tooltipTriggerList.map(el=>new bootstrap.Tooltip(el));
});
</script>
<?php
$pageScripts .= ob_get_clean();
require __DIR__ . '/../partials/footer.php';
?>
