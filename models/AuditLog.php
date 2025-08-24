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
     * @param string|null $now   Timestamp to use for created_at
     */
    public static function insert(PDO $pdo, ?int $userId, string $action, array|string|null $details = null, ?string $now = null): void
    {
        try {
            $det = is_array($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : $details;
            $now = $now ?? date('Y-m-d H:i:s');
            $st = $pdo->prepare('INSERT INTO audit_log (user_id, action, details, created_at) VALUES (:uid,:act,:det,:now)');
            if ($st !== false) {
                $st->execute([':uid' => $userId, ':act' => $action, ':det' => $det, ':now' => $now]);
            }
        } catch (Throwable) {
            // swallow logging errors
        }
    }
}
