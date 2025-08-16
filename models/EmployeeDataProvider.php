<?php
declare(strict_types=1);

// /models/EmployeeDataProvider.php

final class EmployeeDataProvider
{
    /**
     * @param ?list<string> $skills
     * @param ?string $search
     * @return array{rows:array<int, array{
     *   employee_id:int,
     *   first_name:string,
     *   last_name:string,
     *   email:?string,
     *   phone:?string,
     *   skills:string, // CSV of "skill|proficiency"
     *   is_active:int,
     *   status:string
     * }>, total:int}
     */
    public static function getFiltered(
        PDO $pdo,
        ?array $skills = null,
        int $page = 1,
        int $perPage = 25,
        ?string $sort = null,
        ?string $direction = null,
        ?string $search = null
    ): array
    {
        $where = "WHERE 1=1";
        $params = [];

        if ($skills !== null && $skills !== []) {
            $skills = array_values(array_unique(array_filter($skills, static fn($s): bool => $s !== '')));
            if ($skills !== []) {
                $placeholders = [];
                foreach ($skills as $i => $skill) {
                    $ph = ':skill' . $i;
                    $placeholders[] = $ph;
                    $params[$ph] = $skill;
                }
                $count = count($placeholders);
                $where .= " AND e.id IN (
                    SELECT es.employee_id
                    FROM employee_skills es
                    JOIN skills s ON s.id = es.skill_id
                    WHERE s.name IN (" . implode(',', $placeholders) . ")
                    GROUP BY es.employee_id
                    HAVING COUNT(DISTINCT s.name) = $count
                )";
            }
        }

        if ($search !== null && $search !== '') {

            $where .= " AND (p.first_name LIKE :search1 OR p.last_name LIKE :search2 OR p.email LIKE :search3 OR p.phone LIKE :search4)";
            $value = '%' . $search . '%';
            $params[':search1'] = $value;
            $params[':search2'] = $value;
            $params[':search3'] = $value;
            $params[':search4'] = $value;

        }

        $countSql = "SELECT COUNT(*) FROM employees e JOIN people p ON p.id = e.person_id $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);

        $sortable = [
            'employee_id' => 'e.id',
            'last_name' => 'p.last_name',
            'status' => 'e.status',
        ];
        if ($sort !== null && isset($sortable[$sort])) {
            $dir = strtoupper($direction ?? '') === 'DESC' ? 'DESC' : 'ASC';
            $orderBy = $sortable[$sort] . ' ' . $dir;
            if ($sort === 'last_name') {
                $orderBy .= ', p.first_name ' . $dir;
            }
        } else {
            $orderBy = 'p.last_name ASC, p.first_name ASC';
        }

        $sql = "
            SELECT e.id AS employee_id,
                   p.first_name,
                   p.last_name,
                   p.email,
                   p.phone,
                   COALESCE(
                       GROUP_CONCAT(
                           DISTINCT CONCAT(s.name, '|', COALESCE(es.proficiency, ''))
                           ORDER BY s.name SEPARATOR ','
                       ),
                       ''
                   ) AS skills,
                   e.is_active,
                   e.status
            FROM employees e
            JOIN people p ON p.id = e.person_id
            LEFT JOIN employee_skills es ON es.employee_id = e.id
            LEFT JOIN skills s ON s.id = es.skill_id
            $where
            GROUP BY e.id, p.first_name, p.last_name, p.email, p.phone, e.is_active, e.status
            ORDER BY $orderBy
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        /** @var array<int, array{employee_id:int, first_name:string, last_name:string, email:?string, phone:?string, skills:string, is_active:int, status:string}> */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return ['rows' => $rows, 'total' => $total];
    }
}
