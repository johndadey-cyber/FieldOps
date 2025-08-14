<?php
declare(strict_types=1);

// /models/CustomerDataProvider.php

final class CustomerDataProvider
{
    /**
     * @return array<int, array{
     *   id:int,
     *   first_name:string,
     *   last_name:string,
     *   email:?string,
     *   phone:?string,
     *   address_line1:?string,
     *   city:?string,
     *   state:?string
     * }>
     */
    public static function getFiltered(
        PDO $pdo,
        ?string $search = null,
        ?string $city = null,
        ?string $state = null,
        ?string $limit = null,
        ?string $sort = 'id',
        string $order = 'asc'
    ): array {
        $sql = "SELECT id, first_name, last_name, email, phone, address_line1, city, state
                FROM customers
                WHERE 1=1";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= " AND (
                first_name LIKE :q
                OR last_name LIKE :q
                OR email LIKE :q
                OR address_line1 LIKE :q
                OR city LIKE :q
            )";
            $params[':q'] = "%{$search}%";
        }
        if ($city !== null && $city !== '') {
            $sql .= " AND city = :city";
            $params[':city'] = $city;
        }
        if ($state !== null && $state !== '') {
            $sql .= " AND state = :state";
            $params[':state'] = $state;
        }

        // Determine sort column and direction
        $allowedSorts = [
            'id'    => 'id',
            'email' => 'email',
            'phone' => 'phone',
            'city'  => 'city',
            'state' => 'state',
        ];
        $sort = strtolower((string)$sort);
        $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';
        if ($sort === 'name') {
            $sql .= ' ORDER BY last_name ' . $order . ', first_name ' . $order;
        } elseif (isset($allowedSorts[$sort])) {
            $sql .= ' ORDER BY ' . $allowedSorts[$sort] . ' ' . $order;
        } else {
            $sql .= ' ORDER BY id ASC';
        }

        // Sanitize limit from string → int
        $limitInt = null;
        if ($limit !== null && $limit !== '' && ctype_digit($limit)) {
            $limitInt = (int)$limit;
        }
        if ($limitInt !== null && $limitInt > 0) {
            $sql .= " LIMIT {$limitInt}";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        /** @var array<int, array{id:int, first_name:string, last_name:string, email:?string, phone:?string, address_line1:?string, city:?string, state:?string}> */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }
}
