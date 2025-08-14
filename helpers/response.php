<?php
declare(strict_types=1);

/**
 * Central JSON response helpers with unified error codes.
 * Back-compat: errors also include the legacy top-level "error" (string).
 */

if (!function_exists('json_success')) {
    /**
     * @param array<string,mixed> $data
     * @return never
     */
    function json_success(array $data = [], int $status = 200): never {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($status);
        }
        echo json_encode(array_merge(['ok' => true], $data), JSON_UNESCAPED_UNICODE);
        exit;
    }
}

if (!function_exists('json_error')) {
    /**
     * @param string $code     Machine-readable error code, e.g. "RBAC_DENIED"
     * @param string $message  Human-readable message
     * @return never
     */
    function json_error(string $code, string $message, int $status = 400): never {
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code($status);
        }
        echo json_encode([
            'ok'     => false,
            'code'   => $code,        // new unified code
            'message'=> $message,     // new human-friendly message
            'error'  => $message,     // legacy alias to avoid breaking older tests/clients
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
