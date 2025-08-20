<?php declare(strict_types=1);

/**
 * Helper methods for checklist items associated with jobs.
 */
final class JobChecklistItem
{
    /**
     * Default checklist templates keyed by job type id.
     * @var array<int, list<string>>
     */
    private const DEFAULT_TEMPLATES = [
        // Basic installation job
        1 => [
            'Review work order',
            'Confirm materials on site',
            'Perform installation',
            'Test and verify operation',
        ],
        // Routine maintenance job
        2 => [
            'Inspect equipment condition',
            'Perform routine maintenance',
            'Update service log',
        ],
    ];

    /**
     * Expose default templates for job types.
     *
     * @return array<int, list<string>>
     */
    public static function defaultTemplates(): array
    {
        return self::DEFAULT_TEMPLATES;
    }

    /**
     * Fetch checklist items for a job.
     * @return list<array{id:int,job_id:int,description:string,is_completed:bool,completed_at:?string}>
     */
    public static function listForJob(PDO $pdo, int $jobId): array
    {
        $st = $pdo->prepare(
            'SELECT id, job_id, description, is_completed, completed_at
             FROM job_checklist_items
             WHERE job_id = :jid
             ORDER BY id'
        );
        if ($st === false) {
            return [];
        }
        $st->execute([':jid' => $jobId]);
        /** @var list<array<string, mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(
            static fn(array $r): array => [
                'id' => (int)$r['id'],
                'job_id' => (int)$r['job_id'],
                'description' => (string)$r['description'],
                'is_completed' => (bool)$r['is_completed'],
                'completed_at' => $r['completed_at'] !== null ? (string)$r['completed_at'] : null,
            ],
            $rows
        );
    }

    /**
     * Toggle completion state of an item. Returns new state or null if not found.
     */
    public static function toggle(PDO $pdo, int $id): ?bool
    {
        $st = $pdo->prepare('SELECT is_completed FROM job_checklist_items WHERE id = :id');
        if ($st === false) {
            return null;
        }
        $st->execute([':id' => $id]);
        $cur = $st->fetchColumn();
        if ($cur === false) {
            return null;
        }
        $new = ((int)$cur) === 1 ? 0 : 1;
        $upd = $pdo->prepare('UPDATE job_checklist_items SET is_completed = :c, completed_at = :ts WHERE id = :id');
        $upd->execute([
            ':c' => $new,
            ':ts' => $new === 1 ? date('Y-m-d H:i:s') : null,
            ':id' => $id,
        ]);
        if ($upd->rowCount() === 0) {
            return null;
        }
        return $new === 1;
    }

    /**
     * Seed default checklist rows for a job based on job type id.
     * @param int $jobTypeId The job type identifier.
     */
    public static function seedDefaults(PDO $pdo, int $jobId, int $jobTypeId): void
    {
        $templates = self::DEFAULT_TEMPLATES[$jobTypeId] ?? [];
        if ($templates === []) {
            return;
        }
        $st = $pdo->prepare('INSERT INTO job_checklist_items (job_id, description) VALUES (:jid, :desc)');
        foreach ($templates as $desc) {
            $st->execute([':jid' => $jobId, ':desc' => $desc]);
        }
    }
}
