<?php declare(strict_types=1);

final class JobTypeSkill
{
    /**
     * List skill IDs for a given job type.
     *
     * @return list<int>
     */
    public static function listForJobType(PDO $pdo, int $jobTypeId): array
    {
        try {
            $st = $pdo->prepare('SELECT skill_id FROM jobtype_skills WHERE job_type_id = :jt ORDER BY skill_id');
            if ($st === false) {
                return [];
            }
            $st->execute([':jt' => $jobTypeId]);
            /** @var list<array{skill_id:int|string}> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return array_map(static fn(array $r): int => (int)$r['skill_id'], $rows);
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Attach a skill to a job type.
     */
    public static function attach(PDO $pdo, int $jobTypeId, int $skillId): bool
    {
        try {
            $st = $pdo->prepare('INSERT INTO jobtype_skills (job_type_id, skill_id) VALUES (:jt, :sid)');
            if ($st === false) {
                return false;
            }
            return $st->execute([':jt' => $jobTypeId, ':sid' => $skillId]);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Detach a skill from a job type.
     */
    public static function detach(PDO $pdo, int $jobTypeId, int $skillId): bool
    {
        try {
            $st = $pdo->prepare('DELETE FROM jobtype_skills WHERE job_type_id = :jt AND skill_id = :sid');
            if ($st === false) {
                return false;
            }
            $st->execute([':jt' => $jobTypeId, ':sid' => $skillId]);
            return $st->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
