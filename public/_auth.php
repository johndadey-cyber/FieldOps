<?php
declare(strict_types=1);

/**
 * _auth.php
 *
 * Provides auth helpers / enforcement for public endpoints.
 * IMPORTANT: During CLI (PHPUnit), this file must NOT emit output unless the test harness allows it.
 */

// If running from CLI and not explicitly allowed by the harness, do nothing.
// This prevents stray Forbidden JSON from polluting PHPUnit output.
if (PHP_SAPI === 'cli') {
    $allowed = defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')
        && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']);
    if (!$allowed) {
        return;
    }
}

// Normal web/request path continues here.
// Load your real auth helpers (adjust path if your helpers live elsewhere).
require_once __DIR__ . '/../helpers/auth_helpers.php';

// If this file previously performed immediate checks (e.g., require_role(...))
// and echoed/returned JSON on failure, move that logic into a function in
// helpers/auth_helpers.php and call it from endpoints instead, e.g.:
//
//   require_role('dispatcher');  // called from each endpoint
//
// That keeps includes side-effect free and prevents early output in CLI.
