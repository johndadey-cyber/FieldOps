<?php
declare(strict_types=1);

final class EndpointHarness
{
    /**
     * Run a public endpoint file and return decoded JSON.
     *
     * @param string $script Absolute path to public/*.php
     * @param array  $data   Payload (POST or GET)
     * @param array  $session Session vars (e.g., ['role'=>'dispatcher'])
     * @param string $method 'POST'|'GET'
     * @param array  $opts   ['inject_csrf' => bool(true)]
     * @return array Decoded JSON (or ['raw'=>string] if decode fails)
     */
    public static function run(
        string $script,
        array $data = [],
        array $session = [],
        string $method = 'POST',
        array $opts = []
    ): array {
        $injectCsrf = $opts['inject_csrf'] ?? true;

        // Reset globals
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = $method;

        // Fresh session
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        $_SESSION = [];
        session_id('t_' . bin2hex(random_bytes(3)));
        session_start();

        // Seed session vars
        foreach ($session as $k => $v) {
            $_SESSION[$k] = $v;
        }

        // Ensure CSRF token exists in the session
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        // Apply payload (+ optional CSRF injection)
        if ($method === 'POST') {
            $_POST = $data;
            if ($injectCsrf && !isset($_POST['csrf_token'])) {
                $_POST['csrf_token'] = $_SESSION['csrf_token'];
            }
        } else {
            $_GET = $data;
            if ($injectCsrf && !isset($_GET['csrf_token'])) {
                $_GET['csrf_token'] = $_SESSION['csrf_token'];
            }
        }

        // Allow endpoint execution ONLY for intentional test calls
        $GLOBALS['__FIELDOPS_TEST_CALL__'] = true;
        if (!defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')) {
            define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
        }

        ob_start();
        require $script;
        $out = (string)ob_get_clean();

        // Clean up sentinel to avoid cross-test leakage
        unset($GLOBALS['__FIELDOPS_TEST_CALL__']);

        $decoded = json_decode($out, true);
        return is_array($decoded) ? $decoded : ['raw' => $out];
    }
}
