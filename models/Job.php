<?php declare(strict_types=1);

final class Job
{
    /**
     * Fetch a job joined to its customer details.
     * @return array<string,mixed>|null
     */
    public static function getJobAndCustomerDetails(PDO $pdo, int $jobId): ?array
    {
        $st = $pdo->prepare(
            "SELECT
                j.id,
                j.customer_id,
                j.description,
                j.status,
                j.started_at,
                j.scheduled_date,
                j.scheduled_time,
                j.duration_minutes,
                c.id         AS customer_id_actual,
                c.first_name AS customer_first_name,
                c.last_name  AS customer_last_name,
                c.email      AS customer_email,
                c.phone      AS customer_phone,
                c.address_line1,
                c.address_line2,
                c.city,
                c.state,
                c.postal_code,
                c.country
            FROM jobs j
            JOIN customers c ON c.id = j.customer_id
            WHERE j.id = :id
            LIMIT 1"
        );
        if ($st === false) {
            return null;
        }
        $st->execute([':id' => $jobId]);
        /** @var array<string,mixed>|false $row */
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'customer_id' => (int)$row['customer_id'],
            'job_type_id' => null,
            'job_type' => null,
            'description' => $row['description'],
            'status' => $row['status'],
            'started_at' => $row['started_at'],
            'scheduled_date' => $row['scheduled_date'],
            'scheduled_time' => $row['scheduled_time'],
            'duration_minutes' => $row['duration_minutes'] !== null ? (int)$row['duration_minutes'] : null,
            'customer' => [
                'id' => (int)$row['customer_id_actual'],
                'first_name' => $row['customer_first_name'],
                'last_name' => $row['customer_last_name'],
                'email' => $row['customer_email'],
                'phone' => $row['customer_phone'],
                'address_line1' => $row['address_line1'],
                'address_line2' => $row['address_line2'],
                'city' => $row['city'],
                'state' => $row['state'],
                'postal_code' => $row['postal_code'],
                'country' => $row['country'],
            ],
        ];
    }

    /**
     * Allowed statuses for UI/forms.
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        // Keep in sync with assignment_process.php status flip rules and DB ENUM.
        return ['draft','scheduled','assigned','in_progress','completed','closed','cancelled'];
    }

    /**
     * Delete a job by id. Returns affected rows (0/1).
     * Note: relies on FK ON DELETE CASCADE for related rows.
     */
    public static function delete(PDO $pdo, int $jobId): int
    {
        $driver = (string)$pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        $limitClause = in_array($driver, ['mysql', 'mariadb'], true) ? ' LIMIT 1' : '';
        $st = $pdo->prepare('DELETE FROM jobs WHERE id = :id' . $limitClause);
        if ($st === false) {
            return 0;
        }
        $st->execute([':id' => $jobId]);
        return $st->rowCount();
    }
    /**
     * Fetch skills linked to a job (id + name).
     *
     * @return list<array{id:int,name:string}>
     */
    public static function getSkillsForJob(PDO $pdo, int $jobId): array
    {
        try {
            $st = $pdo->prepare("
                SELECT s.id, s.name
                FROM job_skill js
                JOIN skills s ON s.id = js.skill_id
                WHERE js.job_id = :job_id
                ORDER BY s.name, s.id
            ");
            if ($st === false) {
                return [];
            }
            $st->execute([':job_id' => $jobId]);
            /** @var list<array{id:int|string,name:string}> $rows */
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            return array_map(
                static fn(array $r): array => ['id' => (int)$r['id'], 'name' => (string)$r['name']],
                $rows
            );
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Mark a job as started. Sets status to in_progress, records start time and location.
     * Returns true if the row was updated.
     */
    public static function start(PDO $pdo, int $jobId, ?float $lat, ?float $lng): bool
    {
        $st = $pdo->prepare(
            'UPDATE jobs
             SET status = "in_progress", started_at = NOW(), location_lat = :lat, location_lng = :lng, updated_at = NOW()
             WHERE id = :id AND status = "assigned" AND started_at IS NULL'
        );
        if ($st === false) {
            return false;
        }
        $st->execute([':lat' => $lat, ':lng' => $lng, ':id' => $jobId]);
        return $st->rowCount() > 0;
    }

    /**
     * Mark a job as completed. Sets status to completed, records completion time and location.
     * Returns true if the row was updated.
     */
    public static function complete(PDO $pdo, int $jobId, ?float $lat, ?float $lng): bool
    {
        $noteSt  = $pdo->prepare('SELECT COUNT(*) FROM job_notes WHERE job_id = :id AND is_final = 1');
        $photoSt = $pdo->prepare('SELECT COUNT(*) FROM job_photos WHERE job_id = :id');
        $sigSt   = $pdo->prepare('SELECT COUNT(*) FROM job_completion WHERE job_id = :id AND signature_path <> ""');
        if ($noteSt === false || $photoSt === false || $sigSt === false) {
            return false;
        }
        $noteSt->execute([':id' => $jobId]);
        $photoSt->execute([':id' => $jobId]);
        $sigSt->execute([':id' => $jobId]);
        if ((int)$noteSt->fetchColumn() <= 0 || (int)$photoSt->fetchColumn() <= 0 || (int)$sigSt->fetchColumn() <= 0) {
            return false;
        }

        $st = $pdo->prepare(
            'UPDATE jobs
             SET status = "completed", completed_at = NOW(), location_lat = :lat, location_lng = :lng, updated_at = NOW()
             WHERE id = :id AND status = "in_progress" AND completed_at IS NULL'
        );
        if ($st === false) {
            return false;
        }
        $st->execute([':lat' => $lat, ':lng' => $lng, ':id' => $jobId]);
        return $st->rowCount() > 0;
    }
}
