<?php
// /models/JobDataProvider.php
declare(strict_types=1);

class JobDataProvider
{
    /** @return array<int, array<string, mixed>> */
    public static function getAllJobs(PDO $pdo): array
    {
        $sql = "
            SELECT
                j.id AS job_id,
                j.description,
                j.scheduled_date,
                j.scheduled_time,
                COALESCE(j.duration_minutes, 0) AS duration,
                j.status,
                c.id AS customer_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                CONCAT_WS(', ', c.address_line1, c.city) AS short_address
            FROM jobs j
            JOIN customers c ON c.id = j.customer_id
            ORDER BY j.scheduled_date ASC, j.scheduled_time ASC, j.id ASC
        ";
        $stmt = $pdo->query($sql);
        if (!$stmt) {
            return [];
        }
        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * @param int|null $days
     * @param string|null $status
     * @param string|null $search
     * @return array<int, array<string, mixed>>
     */
    public static function getFiltered(
        PDO $pdo,
        ?int $days = null,
        ?string $status = null,
        ?string $search = null
    ): array {
        $where = [];
        $params = [];

        if ($days !== null) {
            $futureDate = (new DateTimeImmutable())->modify("+{$days} days")->format('Y-m-d');
            $where[] = 'j.scheduled_date <= :future_date';
            $params[':future_date'] = $futureDate;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'j.status = :status';
            $params[':status'] = $status;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(j.description LIKE :q OR c.first_name LIKE :q OR c.last_name LIKE :q)';
            $params[':q'] = '%' . $search . '%';
        }

        $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

        $sql = "
            SELECT
                j.id AS job_id,
                j.description,
                j.scheduled_date,
                j.scheduled_time,
                COALESCE(j.duration_minutes, 0) AS duration,
                j.status,
                c.id AS customer_id,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                CONCAT_WS(', ', c.address_line1, c.city) AS short_address
            FROM jobs j
            JOIN customers c ON c.id = j.customer_id
            $whereSql
            GROUP BY
                j.id, j.description, j.scheduled_date, j.scheduled_time, j.duration_minutes,
                j.status, c.id, customer_name, short_address
            ORDER BY j.scheduled_date ASC, j.scheduled_time ASC, j.id ASC
        ";

        $stmt = $pdo->prepare($sql);
        if (!$stmt) {
            return [];
        }
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }
}
