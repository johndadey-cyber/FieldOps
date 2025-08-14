<?php
declare(strict_types=1);

use Models\Auth; // if you autoload; otherwise we’ll just seed $_SESSION

if (getenv('APP_ENV') !== 'test') { http_response_code(404); exit; }
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

$role = $_GET['role'] ?? '';
$role = in_array($role, ['dispatcher','field_tech'], true) ? $role : 'dispatcher';
$id   = isset($_GET['id']) && is_numeric($_GET['id']) ? (int)$_GET['id'] : null;

// Seed the exact structure your models/Auth.php expects:
$_SESSION['user'] = ['role' => $role];
if ($id !== null) {
    // By convention, Auth::user()['id'] is the employee id for field techs.
    $_SESSION['user']['id'] = $id;
}

header('Content-Type: application/json');
echo json_encode($_SESSION['user']);
