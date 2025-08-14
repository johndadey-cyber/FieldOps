<?php
declare(strict_types=1);

namespace Helpers;

/**
 * Centralized JSON responses.
 *
 * IMPORTANT:
 * - In CLI (e.g., PHPUnit), we suppress JSON output unless the test harness
 *   explicitly allows endpoint execution. This prevents stray blobs corrupting
 *   PHPUnit output when a public/partial is accidentally included.
 * - The harness should set:
 *       define('FIELDOPS_ALLOW_ENDPOINT_EXECUTION', true);
 *       $GLOBALS['__FIELDOPS_TEST_CALL__'] = true;
 */
class JsonResponse
{
    /** Should JSON be emitted in this runtime context? */
    private static function cliAllowed(): bool
    {
        if (PHP_SAPI !== 'cli') {
            return true; // web / FPM / apache module: always emit
        }
        return defined('FIELDOPS_ALLOW_ENDPOINT_EXECUTION')
            && !empty($GLOBALS['__FIELDOPS_TEST_CALL__']);
    }

    /**
     * Low-level JSON sender.
     *
     * @param mixed $data
     * @param int   $statusCode
     * @param array $headers
     */
    public static function send($data, int $statusCode = 200, array $headers = []): void
    {
        // --- CLI suppression: silence accidental emits during PHPUnit ---
        if (!self::cliAllowed()) {
            // Optional trace to help locate offenders (set FIELDOPS_TRACE=1)
            if (getenv('FIELDOPS_TRACE')) {
                $payloadStr = is_string($data) ? $data : json_encode($data);
                error_log('[JSON SUPPRESSED IN CLI] status=' . $statusCode .
                    ' payload=' . substr((string)$payloadStr, 0, 200));
                foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $i => $f) {
                    $file = $f['file'] ?? 'unknown';
                    $line = $f['line'] ?? '?';
                    $fn   = $f['function'] ?? '';
                    error_log("  #$i {$file}:{$line} {$fn}");
                }
            }
            return; // swallow output in CLI unless harness allows
        }
        // --- /CLI suppression ---

        // Normal web behavior:
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $h => $v) {
            header($h . ': ' . $v);
        }

        // Optional targeted trace for Forbidden emits
        if (getenv('FIELDOPS_TRACE') && is_array($data) && ($data['error'] ?? null) === 'Forbidden') {
            error_log('[FORBIDDEN EMIT] helpers/JsonResponse.php');
            foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $i => $f) {
                $file = $f['file'] ?? 'unknown';
                $line = $f['line'] ?? '?';
                $fn   = $f['function'] ?? '';
                error_log("  #$i {$file}:{$line} {$fn}");
            }
        }

        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Convenience: success wrapper */
    public static function success($data, int $statusCode = 200, array $headers = []): void
    {
        self::send(['ok' => true, 'data' => $data], $statusCode, $headers);
    }

    /** Convenience: error wrapper */
    public static function error(string $error, int $statusCode = 400, ?int $code = null, array $headers = []): void
    {
        $resp = ['ok' => false, 'error' => $error];
        if ($code !== null) {
            $resp['code'] = $code;
        }
        self::send($resp, $statusCode, $headers);
    }
}
