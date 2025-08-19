<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap
 * - Silences any accidental output during CLI test bootstrap (e.g., stray JSON).
 * - Sets APP_ENV=test by default.
 * - You can enable trace with FIELDOPS_TRACE=1 to see suppressed call stacks in error_log.
 */

if (getenv('APP_ENV') === false) {
    // Ensure config/database.php sees APP_ENV=test
    putenv('APP_ENV=test');
    $_ENV['APP_ENV'] = 'test';
}
if (!defined('APP_ENV')) {
    define('APP_ENV', 'test');
}

// Start a top-level output buffer to absorb any accidental echoes before tests run.
ob_start();

/**
 * Optional: log what we suppressed when FIELDOPS_TRACE=1 is set.
 * We do not print to STDOUT (that would corrupt PHPUnit output);
 * we only log to STDERR via error_log for debugging.
 */
register_shutdown_function(function () {
    $buf = ob_get_contents();
    if ($buf !== false && $buf !== '') {
        if (getenv('FIELDOPS_TRACE')) {
            error_log('[TEST BOOTSTRAP] Suppressed output length=' . strlen($buf));
            // If you want to inspect the first part of the blob:
            error_log('[TEST BOOTSTRAP] Head: ' . substr($buf, 0, 200));
        }
        // Discard the buffer content
        ob_end_clean();
    } else {
        // Nothing to discard; just end the buffer cleanly
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
    }
});

// If you load any framework/bootstrap code, require it below.
// Example: composer autoload (adjust path if different)
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}

// Ensure the integration test database schema is up to date.
$migrateScript = __DIR__ . '/../scripts/migrate_test_db.php';
$testPdo       = __DIR__ . '/support/TestPdo.php';
if (file_exists($migrateScript) && file_exists($testPdo)) {
    require_once $testPdo;
    require_once $migrateScript;
    try {
        // Suppress any output from migrations; top-level buffer handles echoes.
        $pdo = createTestPdo();
        migrateTestDb($pdo);
    } catch (Throwable $e) {
        // Log to STDERR so failures don't pollute STDOUT.
        error_log('[TEST BOOTSTRAP] Migration failed: ' . $e->getMessage());
    }
}

// If your tests rely on a test env file, load it here
$localEnv = __DIR__ . '/../config/local.env.php';
if (file_exists($localEnv)) {
    // Let your database.php read it; we just ensure it exists
    // and APP_ENV is set to 'test'
}

// Nothing else should echo here. Real test code begins when PHPUnit executes the test files.
