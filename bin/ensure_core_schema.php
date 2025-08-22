<?php
declare(strict_types=1);

/**
 * bin/ensure_core_schema.php  (v2 — safe PK handling)
 *
 * Purpose: Ensure core PRIMARY KEYS, FOREIGN KEYS, and UNIQUEs exist,
 * and clean up obvious orphan rows in dev DB.
 *
 * Idempotent. Detects existing PKs/cols before altering.
 */

require_once __DIR__ . '/../config/database.php';

/** @var PDO $pdo */
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $msg): void { echo $msg, PHP_EOL; }

function tableExists(PDO $pdo, string $t): bool {
    $st = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t");
    $st->execute([':t'=>$t]);
    return (bool)$st->fetchColumn();
}
function columns(PDO $pdo, string $t): array {
    $st = $pdo->prepare("
      SELECT COLUMN_NAME, COLUMN_KEY, EXTRA, COLUMN_TYPE, IS_NULLABLE
      FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t
      ORDER BY ORDINAL_POSITION
    ");
    $st->execute([':t'=>$t]);
    $out=[];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) $out[$r['COLUMN_NAME']] = $r;
    return $out;
}
function primaryKeyCols(PDO $pdo, string $table): array {
    $st = $pdo->prepare("
        SELECT COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :t
          AND CONSTRAINT_NAME = 'PRIMARY'
        ORDER BY ORDINAL_POSITION
    ");
    $st->execute([':t'=>$table]);
    return array_map(fn($r)=>$r['COLUMN_NAME'], $st->fetchAll(PDO::FETCH_ASSOC));
}
function hasAutoPk(array $cols, string $id='id'): bool {
    if (!isset($cols[$id])) return false;
    return strtoupper((string)$cols[$id]['COLUMN_KEY'])==='PRI'
        && str_contains(strtoupper((string)$cols[$id]['EXTRA']), 'AUTO_INCREMENT');
}

/**
 * Ensure `id INT NOT NULL AUTO_INCREMENT PRIMARY KEY` exists on a table.
 * - Adds `id` if missing
 * - Adds PRIMARY KEY on id if PK missing or on a different column
 * - Adds AUTO_INCREMENT if missing
 * Never drops a PRIMARY KEY unless it exists and isn’t on `id`.
 */
function ensureAutoPk(PDO $pdo, string $table): void {
    if (!tableExists($pdo,$table)) { out("[-] Table missing: {$table}"); return; }
    $cols = columns($pdo,$table);
    $pkCols = primaryKeyCols($pdo,$table);
    $hasId = array_key_exists('id', $cols);

    // Case A: id exists and is AUTO_INCREMENT PK -> done
    if ($hasId && hasAutoPk($cols,'id')) {
        out("[OK] {$table}.id is AUTO_INCREMENT PRIMARY KEY");
        return;
    }

    // Case B: id column missing -> add id first
    if (!$hasId) {
        out("[..] Adding `id INT UNSIGNED NOT NULL` to {$table} (first column) ...");
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `id` INT UNSIGNED NOT NULL FIRST");
        $cols = columns($pdo,$table);
        $hasId = true;
    }

    // Ensure id is NOT NULL INT UNSIGNED
    if (stripos((string)$cols['id']['COLUMN_TYPE'], 'int') === false
        || stripos((string)$cols['id']['COLUMN_TYPE'], 'unsigned') === false
        || strtoupper((string)$cols['id']['IS_NULLABLE']) === 'YES') {
        out("[..] Normalizing {$table}.id to INT UNSIGNED NOT NULL ...");
        $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` INT UNSIGNED NOT NULL");
        $cols = columns($pdo,$table);
    }

    // If a PK exists and is NOT on id, drop it first
    if (!empty($pkCols) && !(count($pkCols) === 1 && strtolower($pkCols[0]) === 'id')) {
        out("[..] Dropping existing PRIMARY KEY on `".implode(',', $pkCols)."` (not on id) ...");
        $pdo->exec("ALTER TABLE `{$table}` DROP PRIMARY KEY");
    }

    // Ensure PRIMARY KEY on id
    $pkCols = primaryKeyCols($pdo,$table);
    if (empty($pkCols)) {
        out("[..] Adding PRIMARY KEY(id) on {$table} ...");
        $pdo->exec("ALTER TABLE `{$table}` ADD PRIMARY KEY (`id`)");
    }

    // Ensure AUTO_INCREMENT on id
    $cols = columns($pdo,$table);
    if (!hasAutoPk($cols,'id')) {
        out("[..] Adding AUTO_INCREMENT to {$table}.id ...");
        $pdo->exec("ALTER TABLE `{$table}` MODIFY `id` INT UNSIGNED NOT NULL AUTO_INCREMENT");
    }

    out("[OK] {$table}.id set to AUTO_INCREMENT PRIMARY KEY");
}

function fkInfo(PDO $pdo, string $table, string $column): array {
    $sql = "
      SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
      FROM information_schema.REFERENTIAL_CONSTRAINTS rc
      JOIN information_schema.KEY_COLUMN_USAGE k
        ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
       AND k.CONSTRAINT_NAME  = rc.CONSTRAINT_NAME
      WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
        AND k.TABLE_NAME = :t AND k.COLUMN_NAME = :c
    ";
    $st=$pdo->prepare($sql); $st->execute([':t'=>$table,':c'=>$column]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}
function ensureFk(PDO $pdo, string $table, string $col, string $refTable, string $refCol='id', ?string $nameHint=null, string $onDelete='RESTRICT', string $onUpdate='CASCADE'): void {
    if (!tableExists($pdo,$table) || !tableExists($pdo,$refTable)) { out("[-] Skip FK {$table}.{$col} → {$refTable}.{$refCol} (table missing)"); return; }
    $have = fkInfo($pdo,$table,$col);

    $existingName=null; $matches=false; $needsRuleChange=false;
    foreach ($have as $fk) {
      if (($fk['REFERENCED_TABLE_NAME']??'')===$refTable) {
        $existingName = $fk['CONSTRAINT_NAME'];
        $matches = true;
        if (strtoupper((string)$fk['DELETE_RULE']) !== strtoupper($onDelete)) $needsRuleChange=true;
        break;
      }
    }

    if (!$matches) {
        $cn = $nameHint ?: "fk_{$table}_{$col}";
        out("[..] Adding FK {$cn}: {$table}.{$col} → {$refTable}.{$refCol} ON DELETE {$onDelete} ...");
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$cn}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}`(`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
        out("[OK] FK added");
        return;
    }

    if ($needsRuleChange && $existingName) {
        out("[..] Updating FK {$existingName} to ON DELETE {$onDelete} ...");
        $pdo->exec("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$existingName}`");
        $cn = $nameHint ?: "fk_{$table}_{$col}";
        $pdo->exec("ALTER TABLE `{$table}` ADD CONSTRAINT `{$cn}` FOREIGN KEY (`{$col}`) REFERENCES `{$refTable}`(`{$refCol}`) ON DELETE {$onDelete} ON UPDATE {$onUpdate}");
        out("[OK] FK updated");
        return;
    }

    out("[OK] FK {$table}.{$col} → {$refTable}.{$refCol} present");
}
function uniqueExists(PDO $pdo, string $table, array $cols): bool {
    $st=$pdo->prepare("SHOW INDEX FROM `{$table}` WHERE Non_unique=0");
    $st->execute();
    $byName=[];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byName[$r['Key_name']][$r['Seq_in_index']] = $r['Column_name'];
    }
    foreach ($byName as $colsBySeq){
        ksort($colsBySeq);
        if (array_values($colsBySeq) === array_values($cols)) return true;
    }
    return false;
}
function ensureUnique(PDO $pdo, string $table, array $cols, string $name): void {
    if (!tableExists($pdo,$table)) { out("[-] Table missing: {$table}"); return; }
    if (uniqueExists($pdo,$table,$cols)) { out("[OK] UNIQUE(".implode(',',$cols).") on {$table} present"); return; }
    out("[..] Adding UNIQUE {$name} on {$table}(".implode(',',$cols).") ...");
    $pdo->exec("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$name}` (`" . implode('`,`', $cols) . "`)");
    out("[OK] UNIQUE added");
}

function ensureVarchar100NotNull(PDO $pdo, string $table, string $col): void {
    if (!tableExists($pdo, $table)) { out("[-] Table missing: {$table}"); return; }
    $cols = columns($pdo, $table);
    if (!array_key_exists($col, $cols)) { out("[-] Column missing: {$table}.{$col}"); return; }
    $type = strtoupper((string)$cols[$col]['COLUMN_TYPE']);
    $nullable = strtoupper((string)$cols[$col]['IS_NULLABLE']);
    if ($type !== 'VARCHAR(100)' || $nullable !== 'NO') {
        out("[..] Updating {$table}.{$col} to VARCHAR(100) NOT NULL ...");
        $pdo->exec("ALTER TABLE `{$table}` MODIFY `{$col}` VARCHAR(100) NOT NULL");
        out("[OK] {$table}.{$col} updated to VARCHAR(100) NOT NULL");
    } else {
        out("[OK] {$table}.{$col} is VARCHAR(100) NOT NULL");
    }
}

function indexExists(PDO $pdo, string $table, array $cols): bool {
    $st=$pdo->prepare("SHOW INDEX FROM `{$table}`");
    $st->execute();
    $byName=[];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $byName[$r['Key_name']][$r['Seq_in_index']] = $r['Column_name'];
    }
    foreach ($byName as $colsBySeq) {
        ksort($colsBySeq);
        if (array_values($colsBySeq) === array_values($cols)) return true;
    }
    return false;
}

function ensureIndex(PDO $pdo, string $table, array $cols, string $name): void {
    if (!tableExists($pdo,$table)) { out("[-] Table missing: {$table}"); return; }
    if (indexExists($pdo,$table,$cols)) { out("[OK] INDEX(".implode(',',$cols).") on {$table} present"); return; }
    out("[..] Adding INDEX {$name} on {$table}(".implode(',',$cols).") ...");
    $pdo->exec("ALTER TABLE `{$table}` ADD INDEX `{$name}` (`" . implode('`,`', $cols) . "`)");
    out("[OK] INDEX added");
}

function ensureColumn(PDO $pdo, string $table, string $col, string $definition): void {
    if (!tableExists($pdo, $table)) { out("[-] Table missing: {$table}"); return; }
    $cols = columns($pdo, $table);
    if (!array_key_exists($col, $cols)) {
        out("[..] Adding {$table}.{$col} ...");
        $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$definition}");
        out("[OK] {$table}.{$col} added");
    } else {
        out("[OK] {$table}.{$col} present");
    }
}

// ---- New tables (idempotent) ----
if (!tableExists($pdo, 'employee_availability_overrides')) {
    out('[..] Creating table employee_availability_overrides ...');
    $pdo->exec(
        "CREATE TABLE `employee_availability_overrides` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `date` DATE NOT NULL,
            `status` VARCHAR(20) NOT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'CUSTOM',
            `start_time` TIME NULL,
            `end_time` TIME NULL,
            `reason` VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] employee_availability_overrides created');
}


if (!tableExists($pdo, 'availability_audit')) {
    out('[..] Creating table availability_audit ...');
    $pdo->exec(
        "CREATE TABLE `availability_audit` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `employee_id` INT NOT NULL,
            `user_id` INT NULL,
            `action` VARCHAR(50) NOT NULL,
            `details` TEXT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] availability_audit created');
}

if (!tableExists($pdo, 'skills')) {
    out('[..] Creating table skills ...');
    $pdo->exec(
        "CREATE TABLE `skills` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(100) NOT NULL,
            `description` VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] skills created');
}

// Ensure employee_skills structure (employee_id, skill_id, proficiency)
if (!tableExists($pdo, 'employee_skills')) {
    out('[..] Creating table employee_skills ...');
    $pdo->exec(
        "CREATE TABLE `employee_skills` (
            `employee_id` INT NOT NULL,
            `skill_id` INT NOT NULL,
            `proficiency` VARCHAR(20) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] employee_skills created');
} else {
    $cols = columns($pdo, 'employee_skills');
    if (array_key_exists('job_type_id', $cols)) {
        out('[..] Dropping obsolete column employee_skills.job_type_id ...');
        $pdo->exec("ALTER TABLE `employee_skills` DROP COLUMN `job_type_id`");
        $cols = columns($pdo, 'employee_skills');
    }
    if (!array_key_exists('skill_id', $cols)) {
        out('[..] Adding `skill_id` column to employee_skills ...');
        $pdo->exec("ALTER TABLE `employee_skills` ADD COLUMN `skill_id` INT NOT NULL");
    }
    if (!array_key_exists('proficiency', $cols)) {
        out('[..] Adding `proficiency` column to employee_skills ...');
        $pdo->exec("ALTER TABLE `employee_skills` ADD COLUMN `proficiency` VARCHAR(20) NULL");
    }
}

// Ensure jobtype_skills table
if (!tableExists($pdo, 'jobtype_skills')) {
    out('[..] Creating table jobtype_skills ...');
    $pdo->exec(
        "CREATE TABLE `jobtype_skills` (
            `job_type_id` INT NOT NULL,
            `skill_id` INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] jobtype_skills created');
}

// Ensure job_skill table
if (!tableExists($pdo, 'job_skill')) {
    out('[..] Creating table job_skill ...');
    $pdo->exec(
        "CREATE TABLE `job_skill` (
            `job_id` INT NOT NULL,
            `skill_id` INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_skill created');
}

// Ensure job_job_type table
if (!tableExists($pdo, 'job_job_type')) {
    out('[..] Creating table job_job_type ...');
    $pdo->exec(
        "CREATE TABLE `job_job_type` (
            `job_id` INT NOT NULL,
            `job_type_id` INT NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_job_type created');
}

// Ensure job_notes table
if (!tableExists($pdo, 'job_notes')) {
    out('[..] Creating table job_notes ...');
    $pdo->exec(
        "CREATE TABLE `job_notes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` INT NOT NULL,
            `technician_id` INT NOT NULL,
            `note` TEXT NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_notes created');
}

// Ensure job_photos table
if (!tableExists($pdo, 'job_photos')) {
    out('[..] Creating table job_photos ...');
    $pdo->exec(
        "CREATE TABLE `job_photos` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` INT NOT NULL,
            `technician_id` INT NOT NULL,
            `path` VARCHAR(255) NOT NULL,
            `label` VARCHAR(255) NOT NULL DEFAULT '',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_photos created');
}

// Ensure job_checklist_items table
if (!tableExists($pdo, 'job_checklist_items')) {
    out('[..] Creating table job_checklist_items ...');
    $pdo->exec(
        "CREATE TABLE `job_checklist_items` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `job_id` INT NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `is_completed` TINYINT(1) NOT NULL DEFAULT 0,
            `completed_at` DATETIME NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_checklist_items created');
}

// Ensure job_deletion_log table
if (!tableExists($pdo, 'job_deletion_log')) {
    out('[..] Creating table job_deletion_log ...');
    $pdo->exec(
        "CREATE TABLE `job_deletion_log` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `job_id` INT NOT NULL,
            `user_id` INT NULL,
            `reason` VARCHAR(255) NULL,
            `deleted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    out('[OK] job_deletion_log created');
}

// Drop deprecated job_jobtype table if present
if (tableExists($pdo, 'job_jobtype')) {
    out('[..] Dropping table job_jobtype ...');
    $pdo->exec('DROP TABLE job_jobtype');
    out('[OK] job_jobtype dropped');
}

// Ensure optional columns on existing tables
if (tableExists($pdo, 'customers')) {
    $cols = columns($pdo, 'customers');
    if (!array_key_exists('company', $cols)) {
        out('[..] Adding `company` column to customers ...');
        $pdo->exec("ALTER TABLE `customers` ADD COLUMN `company` VARCHAR(255) NULL AFTER `last_name`");
    }
    if (!array_key_exists('notes', $cols)) {
        out('[..] Adding `notes` column to customers ...');
        $pdo->exec("ALTER TABLE `customers` ADD COLUMN `notes` TEXT NULL");
    }
}

if (tableExists($pdo, 'employee_availability_overrides')) {
    $cols = columns($pdo, 'employee_availability_overrides');
    if (!array_key_exists('type', $cols)) {
        out('[..] Adding `type` column to employee_availability_overrides ...');
        $pdo->exec("ALTER TABLE `employee_availability_overrides` ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'CUSTOM' AFTER `status`");
    }
}

out("== Ensuring PRIMARY KEYS ==");
foreach (['people','employees','job_types','employee_availability_overrides','availability_audit','job_checklist_items','job_deletion_log'] as $t) {
    ensureAutoPk($pdo, $t);
}

out(PHP_EOL . "== Ensuring FOREIGN KEYS ==");
ensureFk($pdo, 'employees', 'person_id', 'people', 'id', 'fk_employees_person', 'CASCADE', 'CASCADE');
ensureIndex($pdo, 'employees', ['person_id'], 'idx_employees_person_id');
ensureFk($pdo, 'jobs',      'customer_id', 'customers', 'id', 'fk_jobs_customer', 'RESTRICT', 'CASCADE');

// Use unique, stable FK names to avoid cross-table conflicts
ensureFk($pdo, 'employee_skills', 'employee_id', 'employees', 'id', 'fk_es_employee', 'RESTRICT', 'CASCADE');
ensureFk($pdo, 'employee_skills', 'skill_id', 'skills', 'id', 'fk_es_skill', 'RESTRICT', 'CASCADE');


ensureFk($pdo, 'jobtype_skills', 'job_type_id', 'job_types', 'id', 'fk_jobtype_skills_jobtype', 'RESTRICT', 'CASCADE');
ensureFk($pdo, 'jobtype_skills', 'skill_id', 'skills', 'id', 'fk_jobtype_skills_skill', 'RESTRICT', 'CASCADE');

ensureFk($pdo, 'job_skill', 'job_id', 'jobs', 'id', 'fk_job_skill_job', 'CASCADE', 'RESTRICT');
ensureFk($pdo, 'job_skill', 'skill_id', 'skills', 'id', 'fk_job_skill_skill', 'RESTRICT', 'CASCADE');

ensureFk($pdo, 'job_job_type', 'job_id', 'jobs', 'id', 'fk_job_jobtype_job', 'CASCADE', 'RESTRICT');
ensureFk($pdo, 'job_job_type', 'job_type_id', 'job_types', 'id', 'fk_job_jobtype_type', 'RESTRICT', 'CASCADE');

ensureFk($pdo, 'job_notes', 'job_id', 'jobs', 'id', 'fk_job_notes_job', 'CASCADE', 'RESTRICT');
ensureFk($pdo, 'job_notes', 'technician_id', 'employees', 'id', 'fk_job_notes_technician', 'RESTRICT', 'RESTRICT');

ensureFk($pdo, 'job_photos', 'job_id', 'jobs', 'id', 'fk_job_photos_job', 'CASCADE', 'RESTRICT');
ensureFk($pdo, 'job_photos', 'technician_id', 'employees', 'id', 'fk_job_photos_technician', 'RESTRICT', 'RESTRICT');

ensureFk($pdo, 'job_checklist_items', 'job_id', 'jobs', 'id', 'fk_job_checklist_job', 'CASCADE', 'RESTRICT');


ensureFk($pdo, 'job_employee_assignment', 'job_id', 'jobs', 'id', 'fk_jea_job', 'CASCADE', 'RESTRICT');
ensureFk($pdo, 'job_employee_assignment', 'employee_id', 'employees', 'id', 'fk_jea_employee', 'RESTRICT', 'RESTRICT');

ensureFk($pdo, 'employee_availability_overrides', 'employee_id', 'employees', 'id', 'fk_eao_employee', 'CASCADE', 'CASCADE');
ensureFk($pdo, 'availability_audit', 'employee_id', 'employees', 'id', 'fk_avail_audit_employee', 'CASCADE', 'CASCADE');
ensureFk($pdo, 'job_deletion_log', 'job_id', 'jobs', 'id', 'fk_job_deletion_log_job', 'CASCADE', 'CASCADE');
ensureFk($pdo, 'job_deletion_log', 'user_id', 'employees', 'id', 'fk_job_deletion_log_user', 'SET NULL', 'CASCADE');

out(PHP_EOL . "== Ensuring people name columns and index ==");
ensureVarchar100NotNull($pdo, 'people', 'first_name');
ensureVarchar100NotNull($pdo, 'people', 'last_name');
ensureIndex($pdo, 'people', ['first_name','last_name'], 'idx_people_first_last');

out(PHP_EOL . "== Ensuring job timing/location columns ==");
ensureColumn($pdo, 'jobs', 'started_at', 'DATETIME NULL');
ensureColumn($pdo, 'jobs', 'completed_at', 'DATETIME NULL');
ensureColumn($pdo, 'jobs', 'location_lat', 'DECIMAL(10,6) NULL');
ensureColumn($pdo, 'jobs', 'location_lng', 'DECIMAL(10,6) NULL');
ensureColumn($pdo, 'jobs', 'technician_id', 'INT NULL');
ensureColumn($pdo, 'jobs', 'deleted_at', 'DATETIME NULL');

out(PHP_EOL . "== Ensuring UNIQUE indexes ==");
ensureUnique($pdo, 'employee_availability', ['employee_id','day_of_week','start_time','end_time'], 'uq_availability_window');
ensureUnique($pdo, 'employee_skills', ['employee_id','skill_id'], 'uq_employee_skill');
ensureUnique($pdo, 'jobtype_skills', ['job_type_id','skill_id'], 'uq_jobtype_skill');
ensureUnique($pdo, 'job_skill', ['job_id','skill_id'], 'uq_job_skill');
ensureUnique($pdo, 'job_job_type', ['job_id','job_type_id'], 'uq_job_job_type');

out(PHP_EOL . "== Cleaning obvious orphan rows (dev only) ==");
try {
    $n = $pdo->exec("DELETE a FROM job_employee_assignment a LEFT JOIN jobs j ON j.id=a.job_id AND j.deleted_at IS NULL WHERE j.id IS NULL");
    out("[OK] job_employee_assignment orphans removed: " . (int)$n);
} catch (Throwable $e) {
    out("[warn] could not clean jea orphans: " . $e->Message());
}

out(PHP_EOL . "Done.");
