<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

// /public/assignments_table.php
require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();

// Filters (defensive defaults)
$employeeId = (int)($_GET['employee_id'] ?? 0);
$days       = (int)($_GET['days'] ?? 7);
$status     = trim((string)($_GET['status'] ?? ''));   // Unassigned / Scheduled / Completed, etc.
$search     = trim((string)($_GET['search'] ?? ''));   // job desc or customer

$sql = "
SELECT
  j.id AS job_id,
  j.description,
  j.status,
  j.scheduled_date,
  j.scheduled_time,
  j.duration_minutes,
  c.first_name AS cust_first,
  c.last_name  AS cust_last,
  CONCAT(c.address_line1, ' ', COALESCE(c.city,''), ' ', COALESCE(c.state,'')) AS short_address,
  e.id AS employee_id,
  CONCAT(p.first_name, ' ', p.last_name) AS employee_name,
  GROUP_CONCAT(DISTINCT jt.name ORDER BY jt.name SEPARATOR ', ') AS job_types
FROM jobs j
JOIN customers c ON c.id = j.customer_id
LEFT JOIN job_job_types jjt ON jjt.job_id = j.id
LEFT JOIN job_types jt ON jt.id = jjt.job_type_id
LEFT JOIN job_employee_assignment jea ON jea.job_id = j.id
LEFT JOIN employees e ON e.id = jea.employee_id
LEFT JOIN people p ON p.id = e.person_id
WHERE
  TIMESTAMP(j.scheduled_date, j.scheduled_time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL :days DAY)
";
$params = [':days' => $days];

if ($employeeId > 0) {
  $sql .= " AND e.id = :employee_id ";
  $params[':employee_id'] = $employeeId;
}

if ($status !== '') {
  $sql .= " AND j.status = :status ";
  $params[':status'] = $status;
}

if ($search !== '') {
  $sql .= " AND (
      j.description LIKE :q OR
      c.first_name LIKE :q OR
      c.last_name  LIKE :q
  )";
  $params[':q'] = '%' . $search . '%';
}

$sql .= "
GROUP BY j.id, e.id
ORDER BY e.id IS NULL, employee_name ASC, j.scheduled_date ASC, j.scheduled_time ASC, j.id ASC
";

$stmt = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$currentEmp = null;
foreach ($rows as $r) {
  $empName = trim((string)($r['employee_name'] ?? 'Unassigned'));
  if ($empName !== $currentEmp) {
    $currentEmp = $empName;
    // Group header row for employee
    echo '<tr class="table-active"><td colspan="7" class="fw-semibold">'
       . htmlspecialchars($currentEmp, ENT_QUOTES, 'UTF-8')
       . '</td></tr>';
  }

  $jid   = (int)($r['job_id'] ?? 0);
  $desc  = htmlspecialchars((string)($r['description'] ?? ''), ENT_QUOTES, 'UTF-8');
  $stat  = htmlspecialchars((string)($r['status'] ?? ''), ENT_QUOTES, 'UTF-8');
  $date  = htmlspecialchars((string)($r['scheduled_date'] ?? ''), ENT_QUOTES, 'UTF-8');
  $time  = htmlspecialchars((string)($r['scheduled_time'] ?? ''), ENT_QUOTES, 'UTF-8');
  $dur   = (int)($r['duration_minutes'] ?? 0);
  $cust  = trim(((string)($r['cust_first'] ?? '')) . ' ' . ((string)($r['cust_last'] ?? '')));
  $cust  = htmlspecialchars($cust, ENT_QUOTES, 'UTF-8');
  $addr  = htmlspecialchars((string)($r['short_address'] ?? ''), ENT_QUOTES, 'UTF-8');
  $types = htmlspecialchars((string)($r['job_types'] ?? ''), ENT_QUOTES, 'UTF-8');

  $badgeClass = 'secondary';
  if ($stat === 'Unassigned') $badgeClass = 'danger';
  else if ($stat === 'Scheduled') $badgeClass = 'primary';
  else if ($stat === 'Completed') $badgeClass = 'success';

  echo "<tr>";
  echo "  <td class=\"text-nowrap\">#{$jid}</td>";
  echo "  <td><div class=\"fw-semibold\">{$desc}</div><div class=\"small text-muted\">{$types}</div></td>";
  echo "  <td><div>{$cust}</div><div class=\"small text-muted\">{$addr}</div></td>";
  echo "  <td class=\"text-nowrap\"><div>{$date}</div><div class=\"small text-muted\">{$time} · {$dur} min</div></td>";
  echo "  <td><span class=\"badge bg-{$badgeClass}\">{$stat}</span></td>";
  echo "  <td class=\"text-end\">";
  echo "    <button class=\"btn btn-sm btn-outline-primary me-1\" data-bs-toggle=\"modal\" data-bs-target=\"#assignModal\" data-job-id=\"{$jid}\">Assign</button>";
  echo "    <a class=\"btn btn-sm btn-outline-secondary\" href=\"edit_job.php?id={$jid}\">Edit</a>";
  echo "  </td>";
  echo "</tr>";
}

// If no rows, render a friendly empty-state row
if (!$rows) {
  echo '<tr><td colspan="7" class="text-center text-muted py-4">No matching assignments.</td></tr>';
}
