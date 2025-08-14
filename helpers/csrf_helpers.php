<?php
declare(strict_types=1);

// CSRF helper functions. Ensures a session token exists and can be
// verified against incoming requests. Safe to include multiple times.

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
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        if (!is_string($token) || $token === '') {
            return false;
        }
        if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
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

?>
