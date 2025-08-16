<?php
// /public/customer_process.php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Customer.php';
$pdo = getPDO();

function redirectWithFlash(string $to, string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $msg];
    header("Location: {$to}");
    exit;
}

$action = $_POST['action'] ?? '';
if (!in_array($action, ['create','update'], true)) {
    redirectWithFlash('customers.php', 'danger', 'Invalid action.');
}

if (!isset($_SESSION['csrf_token'], $_POST['csrf_token']) || $_SESSION['csrf_token'] !== $_POST['csrf_token']) {
    redirectWithFlash('customers.php', 'danger', 'Security token invalid. Please try again.');
}

$id           = (int)($_POST['id'] ?? 0);
$first_name   = trim((string)($_POST['first_name'] ?? ''));
$last_name    = trim((string)($_POST['last_name'] ?? ''));
$email        = trim((string)($_POST['email'] ?? ''));
$phone        = trim((string)($_POST['phone'] ?? ''));
$address1     = trim((string)($_POST['address_line1'] ?? ''));
$address2     = trim((string)($_POST['address_line2'] ?? ''));
$city         = trim((string)($_POST['city'] ?? ''));
$state        = trim((string)($_POST['state'] ?? ''));
$postal       = trim((string)($_POST['postal_code'] ?? ''));
$country      = trim((string)($_POST['country'] ?? ''));
$placeId      = trim((string)($_POST['google_place_id'] ?? ''));
$lat          = $_POST['latitude']  !== '' ? (float)$_POST['latitude']  : null;
$lon          = $_POST['longitude'] !== '' ? (float)$_POST['longitude'] : null;

$errors = [];
if ($first_name === '') $errors[] = 'First name is required.';
if ($last_name === '')  $errors[] = 'Last name is required.';
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Email is invalid.';

if ($errors) {
    redirectWithFlash(($action === 'create' ? 'customer_form.php' : "edit_customer.php?id={$id}"), 'danger', implode(' ', $errors));
}

$customer = new Customer($pdo);

$data = [
    'first_name'      => $first_name,
    'last_name'       => $last_name,
    'email'           => $email,
    'phone'           => $phone,
    'address_line1'   => $address1,
    'address_line2'   => $address2,
    'city'            => $city,
    'state'           => $state,
    'postal_code'     => $postal,
    'country'         => $country,
    'google_place_id' => $placeId,
    'latitude'        => $lat,
    'longitude'       => $lon,
];

try {
    if ($action === 'create') {
        $newId = $customer->create($data);
        if ($newId === false) {
            throw new RuntimeException('create failed');
        }
        redirectWithFlash('customers.php', 'success', 'Customer created.');
    } else {
        if ($id <= 0) {
            redirectWithFlash('customers.php', 'danger', 'Missing customer ID.');
        }
        $ok = $customer->update($id, $data);
        if (!$ok) {
            throw new RuntimeException('update failed');
        }
        redirectWithFlash('customers.php', 'success', 'Customer updated.');
    }
} catch (Throwable $e) {
    error_log('[customer_process] '.$e->getMessage());
    redirectWithFlash('customers.php', 'danger', 'Customer save failed. Please try again.');
}
