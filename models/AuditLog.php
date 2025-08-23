<?php declare(strict_types=1);

final class AuditLog
{
    /**
     * Insert an audit log entry.
     *
     * @param PDO        $pdo     Database connection
     * @param int|null   $userId  User performing the action
     * @param string     $action  Action identifier
     * @param array|string|null $details Additional details encoded as JSON if array
     */
    public static function insert(PDO $pdo, ?int $userId, string $action, array|string|null $details = null): void
    {
        try {
            $det = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details;
            $st = $pdo->prepare('INSERT INTO audit_log (user_id, action, details, created_at) VALUES (:uid,:act,:det,NOW())');
            if ($st !== false) {
                $st->execute([':uid' => $userId, ':act' => $action, ':det' => $det]);
            }
        } catch (Throwable) {
            // swallow logging errors
        }
    }
}
