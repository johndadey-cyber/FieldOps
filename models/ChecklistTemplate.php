<?php declare(strict_types=1);

final class ChecklistTemplate
{
    /**
     * Fetch all templates grouped by job type.
     *
     * @return array<int, list<array{id:int,job_type_id:int,description:string,position:?int}>>
     */
    public static function allByJobType(PDO $pdo): array
    {
        $st = $pdo->prepare('SELECT id, job_type_id, description, position FROM checklist_templates ORDER BY job_type_id, position, id');
        if ($st === false) {
            return [];
        }
        $st->execute();
        /** @var list<array<string, mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        $out = [];
        foreach ($rows as $r) {
            $jt = (int)$r['job_type_id'];
            $out[$jt][] = [
                'id' => (int)$r['id'],
                'job_type_id' => $jt,
                'description' => (string)$r['description'],
                'position' => isset($r['position']) ? (int)$r['position'] : null,
            ];
        }
        return $out;
    }

    /**
     * Find a template by id.
     *
     * @return array{id:int,job_type_id:int,description:string,position:?int}|null
     */
    public static function find(PDO $pdo, int $id): ?array
    {
        $st = $pdo->prepare('SELECT id, job_type_id, description, position FROM checklist_templates WHERE id = :id');
        if ($st === false) {
            return null;
        }
        $st->execute([':id' => $id]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'job_type_id' => (int)$row['job_type_id'],
            'description' => (string)$row['description'],
            'position' => $row['position'] !== null ? (int)$row['position'] : null,
        ];
    }

    /**
     * Create a new template. Returns inserted id.
     */
    public static function create(PDO $pdo, int $jobTypeId, string $description, ?int $position): int
    {
        $st = $pdo->prepare('INSERT INTO checklist_templates (job_type_id, description, position) VALUES (:jt, :d, :p)');
        $st->execute([':jt' => $jobTypeId, ':d' => $description, ':p' => $position]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Update an existing template.
     */
    public static function update(PDO $pdo, int $id, int $jobTypeId, string $description, ?int $position): bool
    {
        $st = $pdo->prepare('UPDATE checklist_templates SET job_type_id = :jt, description = :d, position = :p WHERE id = :id');
        $st->execute([':jt' => $jobTypeId, ':d' => $description, ':p' => $position, ':id' => $id]);
        return $st->rowCount() > 0;
    }

    /**
     * Delete a template by id.
     */
    public static function delete(PDO $pdo, int $id): bool
    {
        $st = $pdo->prepare('DELETE FROM checklist_templates WHERE id = :id');
        $st->execute([':id' => $id]);
        return $st->rowCount() > 0;
    }
}
