<?php
declare(strict_types=1);

if (!function_exists('json_out')) {
    /** @param array<string,mixed> $payload */
    function json_out(array $payload, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }
}
