<?php declare(strict_types=1);

final class JobType
{
    /**
     * Return all job types.
     *
     * @return list<array<string,mixed>>
     */
    public static function all(PDO $pdo): array
    {
        $st = $pdo->prepare("
            SELECT id, name, description
            FROM job_types
            ORDER BY name, id
        ");
        if ($st === false) {
            return [];
        }

        $st->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }

    /**
     * Link a job type to a job. Returns rows changed (0/1+).
     * Works even if the mapping table doesn't exist (safe no-op => 0).
     */
    public static function assignToJob(PDO $pdo, int $jobId, int $typeId): int
    {
        try {
            $st = $pdo->prepare("
                INSERT IGNORE INTO job_jobtype (job_id, job_type_id)
                VALUES (:job_id, :type_id)
            ");
            if ($st === false) {
                return 0;
            }
            $st->execute([':job_id' => $jobId, ':type_id' => $typeId]);
            return $st->rowCount();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /**
     * Return required skill names for the job's type(s).
     * If schema is absent, returns [].
     *
     * @return list<string>
     */
    public static function getRequiredSkillsForJob(PDO $pdo, int $jobId): array
    {
        try {
            $st = $pdo->prepare("
                SELECT DISTINCT s.name
                FROM job_jobtype jj
                JOIN job_types jt ON jt.id = jj.job_type_id
                JOIN jobtype_skills jts ON jts.job_type_id = jt.id
                JOIN skills s ON s.id = jts.skill_id
                WHERE jj.job_id = :job_id
                ORDER BY s.name
            ");
            if ($st === false) {
                return [];
            }
            $st->execute([':job_id' => $jobId]);
            /** @var list<array{name:string}> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $r): string => (string)$r['name'], $rows);
        } catch (Throwable $e) {
            return [];
        }
    }
}
