<?php declare(strict_types=1);

/**
 * JobNote model provides CRUD helpers for notes associated with jobs.
 */
final class JobNote
{
    /**
     * List notes for a given job.
     * @return list<array<string,mixed>>
     */
    public static function listForJob(PDO $pdo, int $jobId): array
    {
        $st = $pdo->prepare(
            'SELECT id, job_id, technician_id, note, is_final, created_at
             FROM job_notes
             WHERE job_id = :jid
             ORDER BY created_at DESC, id DESC'
        );
        if ($st === false) {
            return [];
        }
        $st->execute([':jid' => $jobId]);
        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return array_map(
            static fn(array $r): array => [
                'id' => (int)$r['id'],
                'job_id' => (int)$r['job_id'],
                'technician_id' => (int)$r['technician_id'],
                'note' => (string)$r['note'],
                'is_final' => (bool)$r['is_final'],
                'created_at' => (string)$r['created_at'],
            ],
            $rows
        );
    }

    /**
     * Add a new note for a job. Returns inserted id.
     */
    public static function add(PDO $pdo, int $jobId, int $technicianId, string $note, bool $final = false): int
    {
        $st = $pdo->prepare(
            'INSERT INTO job_notes (job_id, technician_id, note, is_final) VALUES (:jid, :tid, :n, :f)'
        );
        $st->execute([':jid' => $jobId, ':tid' => $technicianId, ':n' => $note, ':f' => $final ? 1 : 0]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Update an existing note's text. Returns true if a row was updated.
     */
    public static function update(PDO $pdo, int $id, string $note): bool
    {
        $st = $pdo->prepare('UPDATE job_notes SET note = :n WHERE id = :id');
        $st->execute([':n' => $note, ':id' => $id]);
        return $st->rowCount() > 0;
    }

    /**
     * Delete a note by id. Returns true if a row was deleted.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('DELETE FROM job_notes WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
