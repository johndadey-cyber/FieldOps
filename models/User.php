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

    /**
     * Validate a password against policy requirements.
     *
     * @return array{0:bool,1:string} [isValid, errorMessage]
     */
    public static function validatePassword(string $password): array
    {
        if (strlen($password) < 8) {
            return [false, 'Password must be at least 8 characters long'];
        }
        if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
            return [false, 'Password must contain at least one letter and one number'];
        }
        /** @var array<int,string> $blacklist */
        $blacklist = require __DIR__ . '/../config/password_blacklist.php';
        if (in_array(strtolower($password), $blacklist, true)) {
            return [false, 'Password is too common'];
        }
        return [true, ''];
    }

    /**
     * Hash a password using bcrypt or Argon2 (if available).
     */
    public static function hashPassword(string $password): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        return password_hash($password, $algo);
    }

    /**
     * Create a new user with a hashed password.
     *
     * @return array{ok:bool,error?:string,id?:int}
     */
    public static function create(
        PDO $pdo,
        string $username,
        string $email,
        string $password,
        string $role = 'user'
    ): array {
        [$valid, $message] = self::validatePassword($password);
        if (!$valid) {
            return ['ok' => false, 'error' => $message];
        }

        try {
            $st = $pdo->prepare(
                'SELECT id FROM users WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email) LIMIT 1'
            );
            if ($st === false) {
                return ['ok' => false, 'error' => 'Server error'];
            }
            $st->execute([':username' => $username, ':email' => $email]);
            if ($st->fetch(PDO::FETCH_ASSOC) !== false) {
                return ['ok' => false, 'error' => 'Username or email already exists'];
            }
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Server error'];
        }

        $hash = self::hashPassword($password);
        try {
            $st = $pdo->prepare(
                'INSERT INTO users (username, email, password, role) VALUES (:username, :email, :password, :role)'
            );
            if ($st === false) {
                return ['ok' => false, 'error' => 'Server error'];
            }
            $st->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => $hash,
                ':role' => $role,
            ]);
            return ['ok' => true, 'id' => (int)$pdo->lastInsertId()];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Server error'];
        }
    }

    /**
     * Update the password for an existing user.
     *
     * @return array{ok:bool,error?:string}
     */
    public static function updatePassword(PDO $pdo, int $id, string $password): array
    {
        [$valid, $message] = self::validatePassword($password);
        if (!$valid) {
            return ['ok' => false, 'error' => $message];
        }
        $hash = self::hashPassword($password);
        try {
            $st = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
            if ($st === false) {
                return ['ok' => false, 'error' => 'Server error'];
            }
            $st->execute([':password' => $hash, ':id' => $id]);
            return ['ok' => true];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'Server error'];
        }
    }
}
