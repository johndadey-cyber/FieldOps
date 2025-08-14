<?php
declare(strict_types=1);
/**
 * Legacy endpoint — retired.
 * Use public/assignment_process.php instead.
 */
http_response_code(410);
header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'ok' => false,
    'error' => 'This endpoint is deprecated. Use assignment_process.php (action=assign|unassign|list).',
    'endpoint' => 'assignment_process.php'
], JSON_UNESCAPED_SLASHES);
