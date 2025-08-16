<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

require_once __DIR__ . '/../config/database.php';

$pdo = getPDO();

$id        = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$employee  = null;
$skillIds  = [];

if ($id > 0) {
    $st = $pdo->prepare(
        'SELECT e.id, e.person_id, e.employment_type, e.hire_date, e.status, e.role_id,
                p.first_name, p.last_name, p.email, p.phone,
                p.address_line1, p.address_line2, p.city, p.state, p.postal_code,
                p.google_place_id, p.latitude, p.longitude
         FROM employees e
         JOIN people p ON p.id = e.person_id
         WHERE e.id = :id'
    );
    if ($st) {
        $st->execute([':id' => $id]);
        $employee = $st->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($employee) {
            $st2 = $pdo->prepare('SELECT skill_id FROM employee_skills WHERE employee_id = :id');
            if ($st2) {
                $st2->execute([':id' => $id]);
                $skillIds = array_map('strval', $st2->fetchAll(PDO::FETCH_COLUMN));
            }
            $ph = $employee['phone'] ?? '';
            if (is_string($ph) && preg_match('/^\d{10}$/', $ph)) {
                $employee['phone'] = sprintf('(%s) %s-%s', substr($ph, 0, 3), substr($ph, 3, 3), substr($ph, 6));
            }
        }
    }
}

$mode = 'edit';

require __DIR__ . '/employee_form.php';

