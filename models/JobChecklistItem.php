<?php declare(strict_types=1);

/**
 * Helper methods for checklist items associated with jobs.
 */
final class JobChecklistItem
{
    /**

     * Fetch default checklist templates grouped by job type from the database.

     *
     * @return array<int, list<string>>
     */
    public static function defaultTemplates(PDO $pdo): array
    {
        $st = $pdo->prepare(

            'SELECT job_type_id, description
             FROM checklist_templates
             ORDER BY job_type_id, position, id'
        );
        if ($st === false) {
            return [];
        }
        $st->execute();

        /** @var list<array<string,mixed>> $rows */

        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $jt = (int)$r['job_type_id'];
            $out[$jt][] = (string)$r['description'];
        }
        return $out;
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

        $st = $pdo->prepare(
            'SELECT description
             FROM checklist_templates
             WHERE job_type_id = :jt
             ORDER BY position, id'
        );
        if ($st === false) {
            return;
        }
        $st->execute([':jt' => $jobTypeId]);
        /** @var list<string> $templates */
        $templates = $st->fetchAll(PDO::FETCH_COLUMN);

        if ($templates === []) {
            return;
        }
        $ins = $pdo->prepare('INSERT INTO job_checklist_items (job_id, description) VALUES (:jid, :desc)');
        foreach ($templates as $desc) {
            $ins->execute([':jid' => $jobId, ':desc' => $desc]);
        }
    }
}
