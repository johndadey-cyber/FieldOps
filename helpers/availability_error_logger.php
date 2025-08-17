<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Employee.php';

/**
 * Log availability save failures for troubleshooting.
 *
 * @param PDO $pdo
 * @param int $employeeId
 * @param array<string,mixed> $payload
 */
function availability_log_error(PDO $pdo, int $employeeId, array $payload, Throwable $e): void {
    $name = 'unknown';
    try {
        $emp = Employee::getById($pdo, $employeeId);
        if ($emp) {
            $name = trim(((string)($emp['first_name'] ?? '')) . ' ' . ((string)($emp['last_name'] ?? '')));
        }
    } catch (Throwable $ex) {
        // ignore lookup failures
    }

    $msg = sprintf(
        "[%s] eid=%d name=%s msg=%s payload=%s\n",
        date('c'),
        $employeeId,
        $name,
        $e->getMessage(),
        json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    );
    error_log($msg, 3, __DIR__ . '/../logs/availability_error.log');
}
