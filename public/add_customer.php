<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

session_start();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../helpers/JsonResponse.php';
require_once __DIR__ . '/../helpers/ErrorCodes.php';
require_once __DIR__ . '/../helpers/auth_helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    JsonResponse::json(['ok' => false, 'error' => 'Method Not Allowed'], 405); exit;
}

require_role('dispatcher');
require_csrf();

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Inputs
$firstName     = trim((string)($_POST['first_name']     ?? ''));
$lastName      = trim((string)($_POST['last_name']      ?? ''));
$email         = trim((string)($_POST['email']          ?? ''));
$phone         = trim((string)($_POST['phone']          ?? ''));

$address1      = trim((string)($_POST['address_line1']  ?? ''));
$address2      = trim((string)($_POST['address_line2']  ?? ''));
$city          = trim((string)($_POST['city']           ?? ''));
$state         = trim((string)($_POST['state']          ?? ''));
$postalCode    = trim((string)($_POST['postal_code']    ?? ''));
$country       = trim((string)($_POST['country']        ?? ''));

$googlePlaceId = trim((string)($_POST['google_place_id'] ?? ''));
$latitudeIn    = $_POST['latitude']  ?? null;
$longitudeIn   = $_POST['longitude'] ?? null;

$latitude  = (is_numeric($latitudeIn))  ? (float)$latitudeIn  : null;
$longitude = (is_numeric($longitudeIn)) ? (float)$longitudeIn : null;

// Dual-mode detection (AJAX vs classic)
$accept   = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
$xhr      = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
$flagAjax = isset($_POST['__ajax']) || isset($_GET['__ajax'])
         || (($_POST['__return'] ?? '') === 'json') || (($_GET['__return'] ?? '') === 'json')
         || (stripos($accept, 'application/json') !== false)
         || (strcasecmp($xhr, 'XMLHttpRequest') === 0);

$errors = [];
if ($firstName === '') { $errors['first_name'] = 'first_name is required.'; }
if ($lastName === '')  { $errors['last_name']  = 'last_name is required.'; }
if ($phone === '')     { $errors['phone']      = 'phone is required.'; }
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'email must be valid.';
}

if (!empty($errors)) {
    if ($flagAjax) {
        JsonResponse::json(['ok' => false, 'errors' => $errors, 'code' => ErrorCodes::VALIDATION_ERROR], 422);
        exit;
    }
    $_SESSION['flash'] = ['errors' => $errors, 'old' => $_POST];
    header('Location: /customer_form.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO customers
            (first_name, last_name, email, phone,
             address_line1, address_line2, city, state, postal_code, country,
             latitude, longitude, google_place_id, created_at)
        VALUES
            (:first_name, :last_name, :email, :phone,
             :address_line1, :address_line2, :city, :state, :postal_code, :country,
             :latitude, :longitude, :google_place_id, NOW())
    ");
    $stmt->execute([
        ':first_name'      => $firstName,
        ':last_name'       => $lastName,
        ':email'           => ($email !== '' ? $email : null),
        ':phone'           => $phone,
        ':address_line1'   => ($address1 !== '' ? $address1 : null),
        ':address_line2'   => ($address2 !== '' ? $address2 : null),
        ':city'            => ($city !== '' ? $city : null),
        ':state'           => ($state !== '' ? $state : null),
        ':postal_code'     => ($postalCode !== '' ? $postalCode : null),
        ':country'         => ($country !== '' ? $country : null),
        ':latitude'        => $latitude,
        ':longitude'       => $longitude,
        ':google_place_id' => ($googlePlaceId !== '' ? $googlePlaceId : null),
    ]);

    $id = (int)$pdo->lastInsertId();

    if ($flagAjax) {
        JsonResponse::json(['ok' => true, 'id' => $id], 200);
        exit;
    }

    $_SESSION['flash']['success'] = 'Customer created successfully.';
    header('Location: /customers.php?created=1&id=' . $id);
    exit;

} catch (PDOException $e) {
    $payload = [
        'ok' => false,
        'error' => 'Server error',
        'code' => ErrorCodes::SERVER_ERROR,
        'detail' => (getenv('APP_ENV') === 'test') ? $e->getMessage() : null,
    ];
    if ($e->getCode() === '23000') {
        $payload = [
            'ok' => false,
            'error' => 'Integrity constraint violation (e.g., duplicate).',
            'code' => ErrorCodes::VALIDATION_ERROR,
            'detail' => (getenv('APP_ENV') === 'test') ? $e->getMessage() : null,
        ];
    }

    if ($flagAjax) {
        JsonResponse::json($payload, (int)$payload['code']);
        exit;
    }

    $_SESSION['flash'] = ['errors' => ['_global' => $payload['error']], 'old' => $_POST];
    header('Location: /customer_form.php');
    exit;
}
