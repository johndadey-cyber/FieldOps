<?php
declare(strict_types=1);

// CSRF helper functions. Ensures a session token exists and can be
// verified against incoming requests. Safe to include multiple times.

$__csrfLogFile = __DIR__ . '/../logs/csrf_failures.log';
$__csrfLastFailure = null;

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
        global $__csrfLogFile, $__csrfLastFailure;
        $logFailure = static function (?string $received) use (&$__csrfLastFailure, $__csrfLogFile): void {
            $details = [
                'timestamp' => date('c'),
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'session_id' => session_id(),
                'stored_token' => $_SESSION['csrf_token'] ?? null,
                'received_token' => $received,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
            ];
            $__csrfLastFailure = $details;
            error_log(json_encode($details) . PHP_EOL, 3, $__csrfLogFile);
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

?>
