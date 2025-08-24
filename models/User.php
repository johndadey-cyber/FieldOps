<?php declare(strict_types=1);

final class User
{
    /**
     * Find a user by username or email (case-insensitive).
     *
     * @return array{id:int|string,username?:string,email?:string,password:string,role:string}|null
     */
    public static function findByIdentifier(PDO $pdo, string $identifier): ?array
    {
        try {
            $st = $pdo->prepare(
                'SELECT id, username, email, password, role FROM users WHERE LOWER(username) = LOWER(:id) OR LOWER(email) = LOWER(:id) LIMIT 1'
            );
            if ($st === false) {
                return null;
            }
            $st->execute([':id' => $identifier]);
            /** @var array<string,mixed>|false $row */
            $row = $st->fetch(PDO::FETCH_ASSOC);
            return $row !== false ? $row : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Update last_login timestamp for a user.
     */
    public static function updateLastLogin(PDO $pdo, int $id): bool
    {
        try {
            $st = $pdo->prepare('UPDATE users SET last_login = NOW() WHERE id = :id');
            if ($st === false) {
                return false;
            }
            return $st->execute([':id' => $id]);
        } catch (Throwable $e) {
            return false;
        }
    }
}
