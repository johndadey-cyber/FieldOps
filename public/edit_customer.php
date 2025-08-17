<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();

$id       = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$customer = [];

if ($id > 0) {
    $st = $pdo->prepare(
        'SELECT id, first_name, last_name, company, notes, email, phone, address_line1, address_line2, city, state, postal_code, country, google_place_id, latitude, longitude FROM customers WHERE id = :id'
    );
    if ($st) {
        $st->execute([':id' => $id]);
        $customer = $st->fetch(PDO::FETCH_ASSOC) ?: [];
        $ph = $customer['phone'] ?? '';
        if (is_string($ph) && preg_match('/^\d{10}$/', $ph)) {
            $customer['phone'] = sprintf('(%s) %s-%s', substr($ph, 0, 3), substr($ph, 3, 3), substr($ph, 6));
        }
    }
}

$mode = 'edit';

require __DIR__ . '/customer_form.php';

