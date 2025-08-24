<?php
declare(strict_types=1);

// Session bootstrap for FieldOps
// Sets secure cookie parameters and enforces inactivity/absolute timeouts.

$appEnv = getenv('APP_ENV') ?: 'dev';
$sessionSecure = getenv('SESSION_SECURE');
$secure = ($sessionSecure !== false)
    ? filter_var($sessionSecure, FILTER_VALIDATE_BOOLEAN)
    : ($appEnv !== 'dev');

$cookieParams = [
    'httponly' => true,
    'secure' => $secure,
    'samesite' => 'Strict',
];

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params($cookieParams);
    session_start();
}

$now = time();
$inactiveLimit = 30 * 60; // 30 minutes
$absoluteLimit = 12 * 60 * 60; // 12 hours

$created = $_SESSION['created_at'] ?? $now;
$last = $_SESSION['last_activity'] ?? $now;

if (($now - $last) > $inactiveLimit || ($now - $created) > $absoluteLimit) {
    session_unset();
    session_destroy();

    if (PHP_SAPI !== 'cli') {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (str_starts_with($uri, '/api/')) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Session expired']);
        } else {
            header('Location: /login.php');
        }
    }
    exit;
}

$_SESSION['last_activity'] = $now;
if (!isset($_SESSION['created_at'])) {
    $_SESSION['created_at'] = $now;
}
