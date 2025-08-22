<?php
// /public/jobs_table.php
declare(strict_types=1);

/**
 * Outputs <tr>...</tr> rows only (no <table> wrapper).
 * Optional GET params:
 *   - days   int (default 30; 0 = today and later)
 *   - status string
 *   - search string (job description or customer name)
 */

require __DIR__ . '/_cli_guard.php';
require __DIR__ . '/../config/database.php';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function badge_class(string $status): string {
  $status = strtolower($status);
  return match ($status) {
    'assigned'     => 'bg-primary-subtle text-primary border',
    'in_progress'  => 'bg-warning-subtle text-warning border',
    'completed'    => 'bg-success-subtle text-success border',
    'cancelled', 'closed'
                   => 'bg-secondary-subtle text-secondary border',
    default        => 'bg-light text-dark border',
  };
}

// Params
$days   = isset($_GET['days']) && is_numeric($_GET['days']) ? max(0, (int)$_GET['days']) : 30;
$status = isset($_GET['status']) ? strtolower(str_replace(' ','_',trim((string)$_GET['status']))) : '';
$search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';

$applyDateFilter = $status !== 'completed';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Build WHERE
$where = ['j.deleted_at IS NULL'];
$args  = [];

if ($applyDateFilter) {
  if ($days === 0) {
    $where[] = "DATE(j.scheduled_date) >= CURDATE()";
  } else {
    $where[] = "DATE(j.scheduled_date) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)";
    $args[':days'] = $days;
  }
}
if ($status !== '') {
  $where[] = "LOWER(j.status) = :status";
  $args[':status'] = $status;
}
if ($search !== '') {
  $needle = str_replace(['\\','%','_'], ['\\\\','\\%','\\_'], $search);
  $args[':q1'] = "%{$needle}%";
  $args[':q2'] = "%{$needle}%";
  $args[':q3'] = "%{$needle}%";
  $where[] = "(j.description LIKE :q1 ESCAPE '\\\\'
               OR c.first_name LIKE :q2 ESCAPE '\\\\'
               OR c.last_name  LIKE :q3 ESCAPE '\\\\')";
}

$sql = "
  SELECT
    j.id,
    j.description,
    j.scheduled_date,
    j.scheduled_time,
    j.status,
    c.first_name AS cust_first,
    c.last_name  AS cust_last,
    COALESCE(crew.cnt, 0) AS crew_count
  FROM jobs j
  LEFT JOIN customers c ON c.id = j.customer_id
  LEFT JOIN (
    SELECT job_id, COUNT(*) AS cnt
    FROM (
      SELECT job_id FROM job_employee
      UNION ALL
      SELECT job_id FROM job_employee_assignment
    ) u
    GROUP BY job_id
  ) crew ON crew.job_id = j.id
  " . ($where ? ("WHERE " . implode(" AND ", $where)) : '') . "
  ORDER BY j.scheduled_date DESC, j.scheduled_time DESC, j.id DESC
  LIMIT 200
";

$st = $pdo->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

$locked = ['cancelled','completed','closed','in_progress'];

foreach ($rows as $r) {
  $id     = (int)($r['id'] ?? 0);
  $desc   = (string)($r['description'] ?? '');
  $date   = (string)($r['scheduled_date'] ?? '');
  $time   = (string)($r['scheduled_time'] ?? '');
  $status = strtolower((string)($r['status'] ?? ''));
  $crew   = (int)($r['crew_count'] ?? 0);
  $cust   = trim(($r['cust_first'] ?? '') . ' ' . ($r['cust_last'] ?? ''));

  $disabled = in_array($status, $locked, true) ? ' disabled' : '';
  $title    = $disabled ? ' title="Assignments locked for this status"' : '';

  echo "<tr data-job-id=\"{$id}\">";
  echo "  <td>".h((string)$id)."</td>";
  echo "  <td>".h($desc)."</td>";
  echo "  <td>".h($cust ?: '—')."</td>";
  echo "  <td>".h($date ?: '—')."</td>";
  echo "  <td>".h($time ?: '—')."</td>";
  echo "  <td><span class=\"crew-count\" data-job-id=\"{$id}\">{$crew}</span></td>";
  echo "  <td><span class=\"badge ".badge_class($status)." badge-status\">".h(str_replace('_',' ', $status))."</span></td>";
  echo "  <td class=\"text-end\">";
  echo "    <button type=\"button\" class=\"btn btn-sm btn-outline-primary btn-assign\" data-bs-toggle=\"modal\" data-bs-target=\"#assignmentsModal\" data-job-id=\"{$id}\"{$disabled}{$title}>Assign</button>";
  echo "  </td>";
  echo "</tr>";
}
