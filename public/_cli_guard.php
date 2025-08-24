<?php
declare(strict_types=1);

/**
 * _cli_guard.php
 *
 * Prevents accidental execution of public endpoints during CLI (e.g., PHPUnit)
 * unless explicitly allowed by the test harness.
 *
 * Test harness contract:
 *   define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
 *   $GLOBALS['__FIELDOPS_TEST_CALL__'] = true;
 *
 * Notes:
 * - Do NOT include this file from itself or other underscore partials.
 * - Place `require __DIR__ . '/_cli_guard.php';` at the very top of every public entry
 *   (immediately after `declare(strict_types=1);`), before session_start() or output.
 * - This file must never echo/print; it should be silent except optional error_log tracing.
 */

if (PHP_SAPI === 'cli') {
    $allowed = defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')
        && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']);

    if (!$allowed) {
        if (getenv('FIELDOPS_TRACE')) {
            $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
            $includedBy = $bt[1]['file'] ?? 'unknown';
            error_log('[GUARD BLOCKED] _cli_guard.php required by ' . $includedBy);
            foreach (get_included_files() as $f) {
                if (preg_match('#/public/[^/]+\.php$#', $f)) {
                    error_log('[INCLUDED public] ' . $f);
                }
            }
        }
        return;
    }
}

// Initialize session handling for web requests
require_once __DIR__ . '/../helpers/session.php';
