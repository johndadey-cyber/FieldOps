<?php
declare(strict_types=1);

/**
 * PHPUnit bootstrap
 * - Silences any accidental output during CLI test bootstrap (e.g., stray JSON).
 * - Sets APP_ENV=test by default.
 * - You can enable trace with FIELDOPS_TRACE=1 to see suppressed call stacks in error_log.
 */

// Ensure session files are written to a writable location
$sessionPath = sys_get_temp_dir() . '/fieldops_sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0777, true);
}
ini_set('session.save_path', $sessionPath);

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
 * Discard any buffered bootstrap output unless tracing is enabled.
 */
function fieldopsBootstrapFlush(): void
{
    if (ob_get_level() === 0) {
        return;
    }
    $buf = ob_get_contents();
    if ($buf !== false && $buf !== '' && getenv('FIELDOPS_TRACE')) {
        error_log('[TEST BOOTSTRAP] Suppressed output length=' . strlen($buf));
        // If you want to inspect the first part of the blob:
        error_log('[TEST BOOTSTRAP] Head: ' . substr($buf, 0, 200));
    }
    ob_end_clean();
}

/**
 * If a fatal error occurs during bootstrap, emit whatever we captured so far
 * so debugging information is not lost. Otherwise discard the buffer.
 */
register_shutdown_function(function () {
    if (ob_get_level() === 0) {
        return;
    }

    $error = error_get_last();
    $fatal = $error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true);

    if ($fatal) {
        $buf = ob_get_contents();
        if ($buf !== false && $buf !== '') {
            // Send to STDERR so it shows up alongside the fatal error message
            fwrite(STDERR, $buf);
        }
        ob_end_flush();
    } else {
        fieldopsBootstrapFlush();
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

// Bootstrap is complete; close the buffer so PHPUnit output is visible.
fieldopsBootstrapFlush();

// Nothing else should echo here. Real test code begins when PHPUnit executes the test files.
