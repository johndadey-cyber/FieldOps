<?php
declare(strict_types=1);

// Load dependencies defensively with absolute paths.
require_once __DIR__ . '/JsonResponse.php';
require_once __DIR__ . '/ErrorCodes.php';
require_once __DIR__ . '/../models/AuditLog.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../config/database.php';

// Final fallback: if JsonResponse/ErrorCodes aren't loaded for any reason,
// provide minimal shims so we never fatal during tests.
if (!class_exists('JsonResponse')) {
    final class JsonResponse {
        /** @param array<string,mixed> $data */
        public static function json(array $data, int $status = 200): void {
            if (PHP_SAPI !== 'cli' && !headers_sent()) {
                http_response_code($status);
                header('Content-Type: application/json; charset=utf-8');
            }
            if (!isset($data['code'])) {
                $data['code'] = $status;
            }
            echo json_encode($data, JSON_UNESCAPED_SLASHES);
        }
    }
}
if (!class_exists('ErrorCodes')) {
    final class ErrorCodes {
        public const FORBIDDEN        = 403;
        public const CSRF_INVALID     = 400;
        public const VALIDATION_ERROR = 422;
    }
}

/**
 * Attempt to authenticate a user by identifier and password.
 *
 * @return array{ok:bool,user:array|null,error:string|null}
 */
if (!function_exists('authenticate')) {
    function authenticate(PDO $pdo, string $identifier, string $password): array
    {
        try {
            $user = User::findByIdentifier($pdo, $identifier);
            if ($user === null || !password_verify($password, (string)$user['password'])) {
                try {
                    $uid = $user['id'] ?? null;
                    AuditLog::insert($pdo, $uid ? (int)$uid : null, 'login_failure', [
                        'identifier' => $identifier,
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                    ]);
                } catch (Throwable) {
                    // ignore
                }
                return ['ok' => false, 'user' => null, 'error' => 'Invalid credentials'];
            }

            $id = (int)$user['id'];
            User::updateLastLogin($pdo, $id);
            try {
                AuditLog::insert($pdo, $id, 'login_success', [
                    'identifier' => $identifier,
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]);
            } catch (Throwable) {
                // ignore
            }

            return ['ok' => true, 'user' => $user, 'error' => null];
        } catch (Throwable) {
            return ['ok' => false, 'user' => null, 'error' => 'Server error'];
        }
    }
}

/**
 * Shared RBAC + CSRF helpers for endpoints and tests.
 * Safe to include multiple times across requests and PHPUnit runs.
 */
if (!function_exists('current_role')) {
    function current_role(): string {
        return isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'guest';
    }
}

if (!function_exists('require_auth')) {
    function require_auth(): void {
        if (current_role() === 'guest') {
            try {
                $pdo = getPDO();
                $uid = $_SESSION['user']['id'] ?? null;
                AuditLog::insert($pdo, $uid, 'auth_required', []);
            } catch (Throwable) {
                // ignore audit errors
            }
            \JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
            exit;
        }
    }
}

if (!function_exists('require_role')) {
    function require_role(string $role): void {
        if (current_role() !== $role) {
            try {
                $pdo = getPDO();
                $uid = $_SESSION['user']['id'] ?? null;
                AuditLog::insert($pdo, $uid, 'rbac_denied', ['required' => $role, 'current' => current_role()]);
            } catch (Throwable) {
                // ignore audit errors
            }
            \JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
            exit;
        }
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void {
        $token = $_POST['csrf_token'] ?? '';
        if (!is_string($token) || $token === '' || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            \JsonResponse::json(['ok' => false, 'error' => 'Invalid CSRF token', 'code' => \ErrorCodes::CSRF_INVALID], 400);
            exit;
        }
    }
}

if (!function_exists('require_job_owner')) {
    function require_job_owner(PDO $pdo, int $jobId): void {
        $userId = isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
        $assigned = false;

        $hasTechColumn = false;
        try {
            $pdo->query('SELECT technician_id FROM jobs LIMIT 0');
            $hasTechColumn = true;
        } catch (Throwable) {
            $hasTechColumn = false;
        }

        if ($hasTechColumn) {
            $st = $pdo->prepare('SELECT technician_id FROM jobs WHERE id = :id AND deleted_at IS NULL');
            if ($st !== false) {
                $st->execute([':id' => $jobId]);
                $techId = (int)$st->fetchColumn();
                $assigned = ($techId === $userId && $techId !== 0);
                if (!$assigned) {
                    try {
                        $st = $pdo->prepare('SELECT 1 FROM job_employee_assignment WHERE job_id = :jid AND employee_id = :eid LIMIT 1');
                        if ($st !== false) {
                            $st->execute([':jid' => $jobId, ':eid' => $userId]);
                            $assigned = ($st->fetchColumn() !== false);
                        }
                    } catch (Throwable) {
                        // ignore
                    }
                }
            }
        } else {
            $st = $pdo->prepare('SELECT 1 FROM job_employee_assignment WHERE job_id = :jid AND employee_id = :eid LIMIT 1');
            if ($st !== false) {
                $st->execute([':jid' => $jobId, ':eid' => $userId]);
                $assigned = ($st->fetchColumn() !== false);
            }
        }

        if (!$assigned) {
            \JsonResponse::json(['ok' => false, 'error' => 'Forbidden', 'code' => \ErrorCodes::FORBIDDEN], 403);
            exit;
        }
    }
}
