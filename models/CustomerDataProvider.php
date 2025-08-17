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
     *   company:?string,
     *   notes:?string,
     *   address_line1:?string,
     *   address_line2:?string,
     *   city:?string,
     *   state:?string,
     *   postal_code:?string,
     *   country:?string
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
        $sql = "SELECT id, first_name, last_name, company, notes, email, phone, address_line1, address_line2, city, state, postal_code, country
                FROM customers
                WHERE 1=1";
        $params = [];

        if ($search !== null && $search !== '') {
            $sql .= ' AND (';
            $searchFields = [
                'first_name',
                'last_name',
                'email',
                'company',
                'notes',
                'address_line1',
                'address_line2',
                'city',
                'state',
                'postal_code',
                'country',
            ];
            $like = "%{$search}%";
            foreach ($searchFields as $i => $field) {
                $param = ":q{$i}";
                if ($i > 0) {
                    $sql .= ' OR';
                }
                $sql .= " {$field} LIKE {$param}";
                $params[$param] = $like;
            }
            $sql .= ')';
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
            'id'          => 'id',
            'email'       => 'email',
            'phone'       => 'phone',
            'company'     => 'company',
            'city'        => 'city',
            'state'       => 'state',
            'postal_code' => 'postal_code',
            'country'     => 'country',
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

        /** @var array<int, array{id:int, first_name:string, last_name:string, company:?string, notes:?string, email:?string, phone:?string, address_line1:?string, address_line2:?string, city:?string, state:?string, postal_code:?string, country:?string}> */
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $rows;
    }
}

