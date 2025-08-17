<?php
declare(strict_types=1);

/**
 * FieldOps schema checker
 * Usage: php bin/schema_check.php [--json]
 */

require_once __DIR__ . '/../config/database.php';

function pdo(): PDO {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $pdo;
}
function dbname(PDO $pdo): string {
    return (string)$pdo->query("SELECT DATABASE()")->fetchColumn();
}
function tableExists(PDO $pdo, string $t): bool {
    $q = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $q->execute([':t'=>$t]);
    return (bool)$q->fetchColumn();
}
/** @return array<string, array<string, string>> */
function columns(PDO $pdo, string $t): array {
    $sql = "
        SELECT
          COLUMN_NAME  AS column_name,
          DATA_TYPE    AS data_type,
          COLUMN_TYPE  AS column_type,
          IS_NULLABLE  AS is_nullable,
          COLUMN_KEY   AS column_key,
          EXTRA        AS extra
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ORDER BY ORDINAL_POSITION
    ";
    $q = $pdo->prepare($sql);
    $q->execute([':t'=>$t]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        if (!isset($r['column_name'])) { continue; }
        $out[(string)$r['column_name']] = $r;
    }
    return $out;
}
function hasAutoPk(array $cols, string $id='id'): bool {
    if (!isset($cols[$id])) return false;
    $extra = strtoupper((string)$cols[$id]['extra']);
    $key   = strtoupper((string)$cols[$id]['column_key']);
    return str_contains($extra, 'AUTO_INCREMENT') && $key === 'PRI';
}
/** @return array<string, array{columns: array<int, string>, ref_table: string, ref_cols: array<int, string>}> */
function fks(PDO $pdo, string $table): array {
    $sql = "
        SELECT
          CONSTRAINT_NAME        AS constraint_name,
          COLUMN_NAME            AS column_name,
          REFERENCED_TABLE_NAME  AS ref_table,
          REFERENCED_COLUMN_NAME AS ref_column
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = :t
          AND REFERENCED_TABLE_NAME IS NOT NULL
        ORDER BY CONSTRAINT_NAME, ORDINAL_POSITION
    ";
    $q = $pdo->prepare($sql);
    $q->execute([':t'=>$table]);
    $out = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = (string)($r['constraint_name'] ?? '');
        if ($name === '') continue;
        $out[$name]['columns'][] = (string)($r['column_name'] ?? '');
        $out[$name]['ref_table'] = (string)($r['ref_table'] ?? '');
        $out[$name]['ref_cols'][]= (string)($r['ref_column'] ?? '');
    }
    return $out;
}
/** @return array<string, array{non_unique:int, cols:array<int,string>}> */
function indexes(PDO $pdo, string $table): array {
    $sql = "
        SELECT
          INDEX_NAME   AS index_name,
          NON_UNIQUE   AS non_unique,
          SEQ_IN_INDEX AS seq_in_index,
          COLUMN_NAME  AS column_name
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
        ORDER BY index_name, seq_in_index
    ";
    $q = $pdo->prepare($sql);
    $q->execute([':t'=>$table]);
    $idx = [];
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $name = (string)($r['index_name'] ?? '');
        if ($name === '') continue;
        $idx[$name]['non_unique'] = (int)($r['non_unique'] ?? 1);
        $idx[$name]['cols'][]     = (string)($r['column_name'] ?? '');
    }
    return $idx;
}
function hasUniqueIndex(PDO $pdo, string $table, array $colsInOrder, ?string $nameHint=null): bool {
    $idx = indexes($pdo, $table);
    foreach ($idx as $name => $d) {
        if (($d['non_unique'] ?? 1) === 0 && ($d['cols'] ?? []) === $colsInOrder) return true;
        if ($nameHint && $name === $nameHint && ($d['cols'] ?? []) === $colsInOrder) return true;
    }
    return false;
}

$pdo = pdo();
$db  = dbname($pdo);
$issues = [];
$notes  = [];

// Required tables & columns
$required = [
  'people' => ['id','first_name','last_name'],
  'employees' => ['id','person_id','is_active'],
  'customers' => ['id','first_name','last_name','company','notes'],
  'jobs' => ['id','customer_id','description','status','scheduled_date','scheduled_time','duration_minutes'],
  'job_types' => ['id','name'],
  'skills' => ['id','name','description'],
  'employee_skills' => ['employee_id','skill_id','proficiency'],
  'jobtype_skills' => ['job_type_id','skill_id'],
  'employee_availability' => ['id','employee_id','day_of_week','start_time','end_time'],
  'job_employee_assignment' => ['id','job_id','employee_id','assigned_at'],
];

foreach ($required as $t => $colsNeed) {
    if (!tableExists($pdo, $t)) { $issues[] = "Missing table: $t"; continue; }
    $cols = columns($pdo, $t);
    foreach ($colsNeed as $c) {
        if (!array_key_exists($c, $cols)) { $issues[] = "Missing column: $t.$c"; }
    }
}

// AUTO_INCREMENT PKs
foreach (['people','employees','customers','jobs','job_types','skills','employee_availability','job_employee_assignment'] as $t) {
    if (!tableExists($pdo, $t)) continue;
    if (!hasAutoPk(columns($pdo, $t), 'id')) {
        $issues[] = "Primary key AUTO_INCREMENT missing or not primary on $t.id";
    }
}

// FK presence
$fkExpect = [
  'employees' => [['cols'=>['person_id'], 'ref'=>'people', 'refcols'=>['id']]],
  'jobs'      => [['cols'=>['customer_id'], 'ref'=>'customers', 'refcols'=>['id']]],
  'employee_skills' => [
      ['cols'=>['employee_id'],'ref'=>'employees','refcols'=>['id']],
      ['cols'=>['skill_id'], 'ref'=>'skills','refcols'=>['id']],
  ],
  'jobtype_skills' => [
      ['cols'=>['job_type_id'],'ref'=>'job_types','refcols'=>['id']],
      ['cols'=>['skill_id'],'ref'=>'skills','refcols'=>['id']],
  ],
  'job_employee_assignment' => [
      ['cols'=>['job_id'],'ref'=>'jobs','refcols'=>['id']],
      ['cols'=>['employee_id'],'ref'=>'employees','refcols'=>['id']],
  ],
];
foreach ($fkExpect as $t=>$list) {
    if (!tableExists($pdo,$t)) continue;
    $present = fks($pdo,$t);
    foreach ($list as $exp) {
        $found = false;
        foreach ($present as $def) {
            if (($def['ref_table'] ?? '') !== $exp['ref']) continue;
            if (($def['columns'] ?? []) === $exp['cols'] && ($def['ref_cols'] ?? []) === $exp['refcols']) { $found = true; break; }
        }
        if (!$found) {
            $issues[] = "Missing FK on $t(" . implode(',',$exp['cols']) . ") → {$exp['ref']}(" . implode(',',$exp['refcols']) . ")";
        }
    }
}

// Indexes/uniques
if (tableExists($pdo,'employee_availability') &&
    !hasUniqueIndex($pdo,'employee_availability',['employee_id','day_of_week','start_time','end_time'],'uniq_emp_day_start_end')) {
    $issues[] = "Missing UNIQUE index on employee_availability(employee_id, day_of_week, start_time, end_time)";
}
if (tableExists($pdo,'job_employee_assignment') &&
    !hasUniqueIndex($pdo,'job_employee_assignment',['job_id','employee_id'],'uniq_assignment_job_emp')) {
    $issues[] = "Missing UNIQUE index on job_employee_assignment(job_id, employee_id)";
}
if (tableExists($pdo,'employee_skills') &&
    !hasUniqueIndex($pdo,'employee_skills',['employee_id','skill_id'],'uq_employee_skill')) {
    $issues[] = "Missing UNIQUE index on employee_skills(employee_id, skill_id)";
}
if (tableExists($pdo,'jobtype_skills') &&
    !hasUniqueIndex($pdo,'jobtype_skills',['job_type_id','skill_id'],'uq_jobtype_skill')) {
    $issues[] = "Missing UNIQUE index on jobtype_skills(job_type_id, skill_id)";
}

// Data health
$checks = [
  'employees→people' => "SELECT COUNT(*) FROM employees e LEFT JOIN people p ON p.id=e.person_id WHERE p.id IS NULL",
  'jobs→customers'   => "SELECT COUNT(*) FROM jobs j LEFT JOIN customers c ON c.id=j.customer_id WHERE c.id IS NULL",
  'jea→jobs'         => "SELECT COUNT(*) FROM job_employee_assignment a LEFT JOIN jobs j ON j.id=a.job_id WHERE j.id IS NULL",
  'jea→employees'    => "SELECT COUNT(*) FROM job_employee_assignment a LEFT JOIN employees e ON e.id=a.employee_id WHERE e.id IS NULL",
];
foreach ($checks as $name=>$sql) {
    try {
        $c = (int)$pdo->query($sql)->fetchColumn();
        if ($c > 0) $issues[] = "Orphan rows: $name = $c";
    } catch (Throwable $e) {
        $notes[] = "Check skipped ($name): " . $e->getMessage();
    }
}

// Duplicate availability windows
if (tableExists($pdo,'employee_availability')) {
    $dupSql = "
        SELECT COUNT(*) FROM (
          SELECT employee_id, day_of_week, start_time, end_time, COUNT(*) c
          FROM employee_availability
          GROUP BY 1,2,3,4
          HAVING COUNT(*) > 1
        ) d
    ";
    $dup = (int)$pdo->query($dupSql)->fetchColumn();
    if ($dup > 0) $issues[] = "Duplicate availability windows found: {$dup} distinct duplicates";
}

// Output
$asJson = in_array('--json', $argv, true);
$out = [
  'database' => $db,
  'status'   => empty($issues) ? 'OK' : 'ISSUES',
  'issues'   => $issues,
  'notes'    => $notes,
];
if ($asJson) {
    echo json_encode($out, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), PHP_EOL;
} else {
    echo "FieldOps Schema Check — DB: {$db}\n";
    echo empty($issues) ? "✅ OK — no issues found.\n" : "❌ Issues:\n - " . implode("\n - ", $issues) . "\n";
    if (!empty($notes)) {
        echo "Notes:\n - " . implode("\n - ", $notes) . "\n";
    }
}
exit(empty($issues) ? 0 : 1);
