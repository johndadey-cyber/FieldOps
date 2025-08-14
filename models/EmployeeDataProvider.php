<?php
declare(strict_types=1);

// /models/EmployeeDataProvider.php

final class EmployeeDataProvider
{
    /**
     * @return array<int, array{
     *   employee_id:int,
     *   first_name:string,
     *   last_name:string,
     *   skills:string,
     *   is_active:int
     * }>
     */
    public static function getFiltered(PDO $pdo, ?string $skill = null): array
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
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array{employee_id:int, first_name:string, last_name:string, skills:string, is_active:int}> */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }
}
