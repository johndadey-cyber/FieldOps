<?php
declare(strict_types=1);

// /models/EmployeeDataProvider.php

final class EmployeeDataProvider
{
    /**
     * @return array{rows:array<int, array{
     *   employee_id:int,
     *   first_name:string,
     *   last_name:string,
     *   skills:string,
     *   is_active:int
     * }>, total:int}
     */
    public static function getFiltered(PDO $pdo, ?string $skill = null, int $page = 1, int $perPage = 25): array
    {
        $where = "WHERE e.is_active = 1";
        $params = [];

        if ($skill !== null && $skill !== '') {
            $where .= " AND EXISTS (
                SELECT 1
                FROM employee_skills es
                JOIN job_types jt ON jt.id = es.job_type_id
                WHERE es.employee_id = e.id
                  AND jt.name = :skill
            )";
            $params[':skill'] = $skill;
        }

        $countSql = "SELECT COUNT(*) FROM employees e $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $sql = "
            SELECT e.id AS employee_id,
                   p.first_name,
                   p.last_name,
                   COALESCE(GROUP_CONCAT(DISTINCT jt.name ORDER BY jt.name SEPARATOR ', '), '') AS skills,
                   e.is_active
            FROM employees e
            JOIN people p ON p.id = e.person_id
            LEFT JOIN employee_skills es ON es.employee_id = e.id
            LEFT JOIN job_types jt ON jt.id = es.job_type_id
            $where
            GROUP BY e.id, p.first_name, p.last_name, e.is_active
            ORDER BY p.last_name, p.first_name
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array{employee_id:int, first_name:string, last_name:string, skills:string, is_active:int}> */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'total' => $total];
    }
}
