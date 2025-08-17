<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
$role = ($_SESSION['role'] ?? '') ?: ($_SESSION['user']['role'] ?? '');
if ($role !== 'dispatcher') { http_response_code(403); echo "Forbidden"; exit; }

$mode       = 'add';
$job        = [];
$jobTypeIds = [];

require __DIR__ . '/job_form.php';
