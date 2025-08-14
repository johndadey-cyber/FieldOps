<?php
declare(strict_types=1);

/**
 * HTTP helper for integration tests.
 */
final class Http
{
    private static function cookieFile(): string
    {
        return sys_get_temp_dir() . '/fo_c.txt';
    }

    public static function baseUrl(): string
    {
        return rtrim(getenv('FIELDOPS_BASE_URL') ?: 'http://127.0.0.1:8010', '/');
    }

    /** Fetch CSRF token from our dedicated endpoint and store session cookie */
    public static function fetchCsrfTokenFrom(string $unused = ''): string
    {
        $url = self::baseUrl() . '/test_csrf.php';
        $cookie = self::cookieFile();

        $cmd = sprintf('curl -s -c %s %s', escapeshellarg($cookie), escapeshellarg($url));
        $out = (string)shell_exec($cmd);
        $json = json_decode($out, true);

        if (!is_array($json) || empty($json['token'])) {
            throw new \RuntimeException("CSRF token endpoint failed. Raw: " . $out);
        }
        return $json['token'];
    }

    /** GET raw string (for HTML checks) */
    public static function get(string $path): string
    {
        $cookie = self::cookieFile();
        $url = self::baseUrl() . '/' . ltrim($path, '/');
        $cmd = sprintf('curl -s -b %s -c %s %s',
            escapeshellarg($cookie),
            escapeshellarg($cookie),
            escapeshellarg($url)
        );
        return (string)shell_exec($cmd);
    }

    /** POST application/x-www-form-urlencoded and decode JSON */
    public static function postFormJson(string $path, array $formData): array
    {
        $cookie = self::cookieFile();
        $url = self::baseUrl() . '/' . ltrim($path, '/');

        // Use -d to send form-encoded; let curl encode
        $parts = [];
        foreach ($formData as $k => $v) {
            $parts[] = '-d ' . escapeshellarg($k . '=' . (string)$v);
        }

        $cmd = sprintf(
            'curl -s -b %s -c %s -X POST %s %s',
            escapeshellarg($cookie),
            escapeshellarg($cookie),
            implode(' ', $parts),
            escapeshellarg($url)
        );

        $out = (string)shell_exec($cmd);
        $json = json_decode($out, true);

        if (!is_array($json)) {
            throw new \RuntimeException("Expected JSON from {$path}. Raw:\n" . $out);
        }
        return $json;
    }
}
