<?php
declare(strict_types=1);

// Load dependencies defensively with absolute paths.
require_once __DIR__ . '/JsonResponse.php';
require_once __DIR__ . '/ErrorCodes.php';
require_once __DIR__ . '/../models/AuditLog.php';
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
 * Shared RBAC + CSRF helpers for endpoints and tests.
 * Safe to include multiple times across requests and PHPUnit runs.
 */
if (!function_exists('current_role')) {
    function current_role(): string {
        return isset($_SESSION['role']) && is_string($_SESSION['role']) ? $_SESSION['role'] : 'guest';
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
