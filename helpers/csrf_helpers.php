<?php
declare(strict_types=1);

// CSRF helper functions. Ensures a session token exists and can be
// verified against incoming requests. Safe to include multiple times.

$__csrfLogFile     = __DIR__ . '/../logs/csrf_failures.log';
$__csrfLogMaxBytes = 1024 * 1024; // 1MB rotation threshold
$__csrfLastFailure = null;

if (!function_exists('csrf_write_log')) {
    /**
     * Write a CSRF log entry, rotating the log file when it exceeds the size
     * limit. Rotation keeps a single backup with the `.1` suffix.
     *
     * @param array<string,mixed> $details
     */
    function csrf_write_log(array $details): void {
        global $__csrfLogFile, $__csrfLogMaxBytes;
        if (is_file($__csrfLogFile) && filesize($__csrfLogFile) >= $__csrfLogMaxBytes) {
            @rename($__csrfLogFile, $__csrfLogFile . '.1');
        }
        error_log(
            json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL,
            3,
            $__csrfLogFile
        );
    }
}

if (!function_exists('csrf_token')) {
    /**
     * Get the current CSRF token, generating one if needed.
     */
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || $_SESSION['csrf_token'] === '') {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }
        return (string)$_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_verify')) {
    /**
     * Verify the provided CSRF token against the session token.
     */
    function csrf_verify(?string $token): bool {
        global $__csrfLastFailure;
        $logFailure = static function (?string $received) use (&$__csrfLastFailure): void {
            $details = [
                'timestamp'   => date('c'),
                'client_ip'   => $_SERVER['REMOTE_ADDR'] ?? '',
                'session_id'  => session_id(),
                'stored_token'=> $_SESSION['csrf_token'] ?? null,
                'received_token' => $received,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            ];
            $__csrfLastFailure = $details;
            csrf_write_log($details);
        };
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!is_string($token) || $token === '') {
            $logFailure($token);
            return false;
        }
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            $logFailure($token);
            return false;
        }
        if (!hash_equals($_SESSION['csrf_token'], $token)) {
            $logFailure($token);
            return false;
        }
        $__csrfLastFailure = null;
        return true;
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Backwards compatible alias for csrf_verify().
     */
    function verify_csrf_token(?string $token): bool {
        return csrf_verify($token);
    }
}

if (!function_exists('csrf_debug_info')) {
    /**
     * Retrieve details about the last CSRF verification failure.
     *
     * @return array|null
     */
    function csrf_debug_info(): ?array {
        global $__csrfLastFailure;
        return $__csrfLastFailure;
    }
}

if (!function_exists('csrf_sanitize_array')) {
    /**
     * Recursively sanitize an array by redacting password fields.
     *
     * @param mixed $value
     * @return mixed
     */
    function csrf_sanitize_array($value) {
        if (!is_array($value)) {
            return $value;
        }
        $out = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && stripos($k, 'password') !== false) {
                $out[$k] = '[REDACTED]';
                continue;
            }
            $out[$k] = csrf_sanitize_array($v);
        }
        return $out;
    }
}

if (!function_exists('csrf_sanitize_raw')) {
    /**
     * Redact password values from a raw JSON string.
     */
    function csrf_sanitize_raw(string $raw): string {
        return preg_replace('/"password"\s*:\s*".*?"/i', '"password":"[REDACTED]"', $raw);
    }
}

if (!function_exists('csrf_log_failure_payload')) {
    /**
     * Log the failed CSRF payload along with debug information.
     *
     * @param string               $rawBody Raw request body
     * @param array<string,mixed>  $parsed  Parsed request body
     */
    function csrf_log_failure_payload(string $rawBody, array $parsed): void {
        $entry = [
            'payload_raw' => csrf_sanitize_raw($rawBody),
            'payload'     => csrf_sanitize_array($parsed),
            'debug'       => csrf_debug_info(),
        ];
        csrf_write_log($entry);
    }
}

?>
