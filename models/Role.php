<?php declare(strict_types=1);

final class Role
{
    /**
     * Return all roles.
     *
     * @return list<array<string,mixed>>
     */
    public static function all(PDO $pdo): array
    {
        $st = $pdo->prepare("
            SELECT id, name
            FROM roles
            ORDER BY name, id
        ");
        if ($st === false) {
            return [];
        }

        $st->execute();

        /** @var list<array<string,mixed>> $rows */
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        return $rows;
    }
}
