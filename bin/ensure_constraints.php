<?php
declare(strict_types=1);

/**
 * bin/ensure_constraints.php
 *
 * Ensures:
 *  - job_employee_assignment has UNIQUE(job_id, employee_id)
 *  - job_employee_assignment.job_id → jobs.id has ON DELETE CASCADE
 *  - job_employee_assignment.employee_id → employees.id exists (RESTRICT)
 *  - employee_availability has UNIQUE(employee_id, day_of_week, start_time, end_time)
 *
 * Idempotent: checks information_schema first; only alters when needed.
 */

require_once __DIR__ . '/../config/database.php';

/** @var PDO $pdo */
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $msg): void { echo $msg, PHP_EOL; }

function indexExists(PDO $pdo, string $table, string $indexName): bool {
    $sql = "SHOW INDEX FROM `$table` WHERE Key_name = :idx";
    $st = $pdo->prepare($sql);
    $st->execute([':idx' => $indexName]);
    return (bool)$st->fetch();
}

function uniqueOnColumnsExists(PDO $pdo, string $table, array $cols): bool {
    // Verify a UNIQUE index exactly on the given column order
    $sql = "SHOW INDEX FROM `$table` WHERE Non_unique = 0";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    foreach ($rows as $r) {
        $byName[$r['Key_name']][$r['Seq_in_index']] = $r['Column_name'];
    }
    foreach ($byName as $colsBySeq) {
        ksort($colsBySeq);
        if (array_values($colsBySeq) === $cols) return true;
    }
    return false;
}

function fkInfo(PDO $pdo, string $table, string $column): array {
    $sql = "
        SELECT rc.CONSTRAINT_NAME, rc.DELETE_RULE, k.REFERENCED_TABLE_NAME, k.REFERENCED_COLUMN_NAME
        FROM information_schema.REFERENTIAL_CONSTRAINTS rc
        JOIN information_schema.KEY_COLUMN_USAGE k
          ON k.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
         AND k.CONSTRAINT_NAME  = rc.CONSTRAINT_NAME
        WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
          AND k.TABLE_NAME = :table
          AND k.COLUMN_NAME = :col
    ";
    $st = $pdo->prepare($sql);
    $st->execute([':table' => $table, ':col' => $column]);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

if (!$pdo->inTransaction()) {
    $pdo->beginTransaction();
}

try {
    // 1) job_employee_assignment UNIQUE(job_id, employee_id)
    if (!uniqueOnColumnsExists($pdo, 'job_employee_assignment', ['job_id', 'employee_id'])) {
        out('[jea] Adding UNIQUE (job_id, employee_id) as uq_job_employee …');
        // If a non-unique index exists, MySQL will allow adding unique separately.
        $pdo->exec("ALTER TABLE job_employee_assignment ADD UNIQUE KEY uq_job_employee (job_id, employee_id)");
        out('[jea] UNIQUE added.');
    } else {
        out('[jea] UNIQUE (job_id, employee_id) already present.');
    }

    // 2) job_employee_assignment.job_id → jobs.id WITH ON DELETE CASCADE
    $fksJobId = fkInfo($pdo, 'job_employee_assignment', 'job_id');
    $needsCascade = true;
    $existingFkName = null;

    foreach ($fksJobId as $fk) {
        if ($fk['REFERENCED_TABLE_NAME'] === 'jobs') {
            $existingFkName = $fk['CONSTRAINT_NAME'];
            if (strtoupper((string)$fk['DELETE_RULE']) === 'CASCADE') {
                $needsCascade = false;
            }
            break;
        }
    }

    if ($existingFkName === null) {
        out('[jea] Adding FK job_id → jobs.id (CASCADE) …');
        $pdo->exec("ALTER TABLE job_employee_assignment
            ADD CONSTRAINT fk_jea_job_id FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE ON UPDATE RESTRICT");
        out('[jea] FK added.');
    } elseif ($needsCascade) {
        out("[jea] Recreating FK {$existingFkName} with ON DELETE CASCADE …");
        $pdo->exec("ALTER TABLE job_employee_assignment DROP FOREIGN KEY `$existingFkName`");
        $pdo->exec("ALTER TABLE job_employee_assignment
            ADD CONSTRAINT fk_jea_job_id FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE ON UPDATE RESTRICT");
        out('[jea] FK updated to CASCADE.');
    } else {
        out('[jea] FK to jobs already CASCADE.');
    }

    // 3) job_employee_assignment.employee_id → employees.id (RESTRICT)
    $fksEmpId = fkInfo($pdo, 'job_employee_assignment', 'employee_id');
    $hasEmpFk = false;
    foreach ($fksEmpId as $fk) {
        if ($fk['REFERENCED_TABLE_NAME'] === 'employees') {
            $hasEmpFk = true;
            break;
        }
    }
    if (!$hasEmpFk) {
        out('[jea] Adding FK employee_id → employees.id (RESTRICT) …');
        $pdo->exec("ALTER TABLE job_employee_assignment
            ADD CONSTRAINT fk_jea_employee_id FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE RESTRICT ON UPDATE RESTRICT");
        out('[jea] FK added.');
    } else {
        out('[jea] FK to employees present.');
    }

    // 4) employee_availability UNIQUE(employee_id, day_of_week, start_time, end_time)
    if (!uniqueOnColumnsExists($pdo, 'employee_availability', ['employee_id', 'day_of_week', 'start_time', 'end_time'])) {
        out('[ea] Adding UNIQUE (employee_id, day_of_week, start_time, end_time) as uq_availability_window …');
        $pdo->exec("ALTER TABLE employee_availability
            ADD UNIQUE KEY uq_availability_window (employee_id, day_of_week, start_time, end_time)");
        out('[ea] UNIQUE added.');
    } else {
        out('[ea] UNIQUE availability window already present.');
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }
    out('Done. All constraints ensured.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "[ERROR] " . $e->getMessage() . PHP_EOL);
    exit(1);
}
