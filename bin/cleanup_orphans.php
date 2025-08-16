<?php
declare(strict_types=1);

/**
 * bin/cleanup_orphans.php
 * Dev-only utility to remove rows that would block FK creation.
 * - Deletes employee_skills rows whose employee/skill no longer exist
 * - Deletes jobtype_skills rows whose skill no longer exist
 * - Deletes job_employee_assignment rows whose job/employee no longer exist
 *
 * Idempotent. Prints counts removed.
 */

require_once __DIR__ . '/../config/database.php';

/** @var PDO $pdo */
$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function out(string $m): void { echo $m, PHP_EOL; }

$pdo->beginTransaction();

try {
    // 1) employee_skills → employees
    $n1 = $pdo->exec("
        DELETE s
        FROM employee_skills s
        LEFT JOIN employees e ON e.id = s.employee_id
        WHERE e.id IS NULL
    ");
    out("[OK] employee_skills deleted (no employee): " . (int)$n1);

    // 2) employee_skills → skills
    $n2 = $pdo->exec("
        DELETE s
        FROM employee_skills s
        LEFT JOIN skills sk ON sk.id = s.skill_id
        WHERE sk.id IS NULL
    ");
    out("[OK] employee_skills deleted (no skill): " . (int)$n2);

    // 3) job_employee_assignment → jobs
    $n3 = $pdo->exec("
        DELETE a
        FROM job_employee_assignment a
        LEFT JOIN jobs j ON j.id = a.job_id
        WHERE j.id IS NULL
    ");
    out("[OK] job_employee_assignment deleted (no job): " . (int)$n3);

    // 4) job_employee_assignment → employees
    $n4 = $pdo->exec("
        DELETE a
        FROM job_employee_assignment a
        LEFT JOIN employees e ON e.id = a.employee_id
        WHERE e.id IS NULL
    ");
    out("[OK] job_employee_assignment deleted (no employee): " . (int)$n4);

    // 5) jobtype_skills → skills
    $n5 = $pdo->exec("
        DELETE js
        FROM jobtype_skills js
        LEFT JOIN skills s ON s.id = js.skill_id
        WHERE s.id IS NULL
    ");
    out("[OK] jobtype_skills deleted (no skill): " . (int)$n5);

    $pdo->commit();
    out("Done.");
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    fwrite(STDERR, "[ERR] ".$e->getMessage().PHP_EOL);
    exit(1);
}
