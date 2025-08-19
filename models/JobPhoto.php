<?php declare(strict_types=1);

/**
 * JobPhoto model provides CRUD helpers for photos associated with jobs.
 */
final class JobPhoto
{
    /**
     * List photos for a given job.
     * @return list<array<string,mixed>>
     */
    public static function listForJob(PDO $pdo, int $jobId): array
    {
        $st = $pdo->prepare(
            'SELECT id, job_id, technician_id, path, label, created_at
             FROM job_photos
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
                'path' => (string)$r['path'],
                'label' => (string)$r['label'],
                'created_at' => (string)$r['created_at'],
            ],
            $rows
        );
    }

    /**
     * Insert new photo metadata. Returns inserted id.
     */
    public static function add(PDO $pdo, int $jobId, int $technicianId, string $path, string $label): int
    {
        $st = $pdo->prepare(
            'INSERT INTO job_photos (job_id, technician_id, path, label) VALUES (:jid, :tid, :p, :l)'
        );
        $st->execute([':jid' => $jobId, ':tid' => $technicianId, ':p' => $path, ':l' => $label]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Fetch a photo by id.
     * @return array<string,mixed>|null
     */
    public static function get(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare(
            'SELECT id, job_id, technician_id, path, label, created_at FROM job_photos WHERE id = :id'
        );
        if ($st === false) {
            return null;
        }
        $st->execute([':id' => $id]);
        /** @var array<string,mixed>|false $row */
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : [
            'id' => (int)$row['id'],
            'job_id' => (int)$row['job_id'],
            'technician_id' => (int)$row['technician_id'],
            'path' => (string)$row['path'],
            'label' => (string)$row['label'],
            'created_at' => (string)$row['created_at'],
        ];
    }

    /**
     * Delete a photo by id. Returns true if a row was deleted.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('DELETE FROM job_photos WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}

