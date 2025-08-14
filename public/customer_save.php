<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/_csrf.php';

function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $jsonQ  = isset($_GET['json']) && $_GET['json'] === '1';
    return $jsonQ || stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest';
}
/** @param array<string,mixed> $payload */
function json_out(array $payload, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}
function redirect_back(string $fallback = '/'): void {
    $to = $_SERVER['HTTP_REFERER'] ?? $fallback;
    header('Location: ' . $to);
    exit;
}

$pdo = getPDO();

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') { json_out(['ok'=>false,'error'=>'Method not allowed'], 405); }

$token = (string)($_POST['csrf_token'] ?? '');
if (!csrf_verify($token)) { json_out(['ok'=>false,'error'=>'Invalid CSRF token'], 422); }

$id      = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$first   = trim((string)($_POST['first_name'] ?? ''));
$last    = trim((string)($_POST['last_name'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$addr1   = trim((string)($_POST['address_line1'] ?? ''));
$addr2   = trim((string)($_POST['address_line2'] ?? ''));
$city    = trim((string)($_POST['city'] ?? ''));
$state   = trim((string)($_POST['state'] ?? ''));
$postal  = trim((string)($_POST['postal_code'] ?? ''));
$placeId = trim((string)($_POST['google_place_id'] ?? ''));
$lat     = ($_POST['latitude']  ?? '') !== '' ? (float)$_POST['latitude']  : null;
$lon     = ($_POST['longitude'] ?? '') !== '' ? (float)$_POST['longitude'] : null;

$errors = [];
if ($first === '') $errors[] = 'First name is required';
if ($last === '')  $errors[] = 'Last name is required';

if ($errors) { wants_json() ? json_out(['ok'=>false,'errors'=>$errors], 422) : redirect_back(); }

try {
    if ($id > 0) {
        $up = $pdo->prepare("
            UPDATE customers
               SET first_name=:fn,last_name=:ln,email=:em,phone=:ph,
                   address_line1=:a1,address_line2=:a2,city=:city,state=:st,postal_code=:pc,
                   google_place_id=:pid, latitude=:lat, longitude=:lon
             WHERE id=:id
        ");
        $up->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId, ':lat'=>$lat, ':lon'=>$lon, ':id'=>$id
        ]);
        wants_json() ? json_out(['ok'=>true,'id'=>$id]) : redirect_back();
    } else {
        $ins = $pdo->prepare("
            INSERT INTO customers (first_name,last_name,email,phone,address_line1,address_line2,city,state,postal_code,google_place_id,latitude,longitude)
            VALUES (:fn,:ln,:em,:ph,:a1,:a2,:city,:st,:pc,:pid,:lat,:lon)
        ");
        $ins->execute([
            ':fn'=>$first, ':ln'=>$last, ':em'=>$email, ':ph'=>$phone,
            ':a1'=>$addr1, ':a2'=>$addr2, ':city'=>$city, ':st'=>$state, ':pc'=>$postal,
            ':pid'=>$placeId, ':lat'=>$lat, ':lon'=>$lon
        ]);
        $newId = (int)$pdo->lastInsertId();
        wants_json() ? json_out(['ok'=>true,'id'=>$newId]) : redirect_back();
    }
} catch (Throwable $e) {
    error_log('[customer_save] ' . $e->getMessage());
    wants_json() ? json_out(['ok'=>false,'error'=>'Save failed'], 500) : redirect_back();
}
