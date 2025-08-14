<?php
declare(strict_types=1);

/**
 * _csrf.php
 *
 * Provides CSRF helpers/enforcement for public endpoints.
 * IMPORTANT: During CLI (PHPUnit), this file must NOT emit output unless the harness allows it.
 */

// If running from CLI and not explicitly allowed by the harness, do nothing.
// This avoids JSON output (Forbidden/CSRF) during test bootstrap.
if (PHP_SAPI === 'cli') {
    $allowed = defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')
        && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']);
    if (!$allowed) {
        return;
    }
}

// Normal web/request path continues here.
// Load your real CSRF helpers (adjust path if needed).
require_once __DIR__ . '/../helpers/ErrorCodes.php';
require_once __DIR__ . '/../helpers/JsonResponse.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';
require_once __DIR__ . '/../helpers/csrf_helpers.php'; // <— if you have one

// As with _auth.php, keep this file side-effect free.
// If you previously validated and emitted JSON on include, move that into a function,
// and call it explicitly inside endpoints, e.g.:
//
//   verify_csrf_or_fail();  // called from the endpoint’s request handler
