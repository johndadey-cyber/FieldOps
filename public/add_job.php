<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') { http_response_code(403); echo "Forbidden"; exit; }

$mode        = 'add';
$job         = [];
$jobSkillIds = [];

/**
 * Log an error message to the job error log.
 */
function log_error(string $msg): void {
    error_log(
        date('[Y-m-d H:i:s] ') . $msg . PHP_EOL,
        3,
        __DIR__ . '/../logs/job_errors.log'
    );
}

// Capture fatal errors that bypass normal exception handling.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        log_error('Fatal error: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
    }
});

try {
    ob_start();
    require __DIR__ . '/job_form.php';
    $output = ob_get_clean();
    $autoScript = <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function () {
    var jobTypeSelect = document.getElementById('job_type_ids');
    if (jobTypeSelect && jobTypeSelect.selectedOptions.length) {
        if (typeof jobTypeSelect.onchange === 'function') {
            jobTypeSelect.onchange();
        } else {
            jobTypeSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }
});
</script>
HTML;
    echo str_replace('</body>', $autoScript . "\n</body>", $output);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    log_error(
        'Exception: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        . PHP_EOL . $e->getTraceAsString()
    );
    http_response_code(500);
    echo 'Server error';
}
