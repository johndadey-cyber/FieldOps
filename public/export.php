<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';


require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Employee.php';

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/** @var list<array<string,mixed>> $employees */
$employees = Employee::getAll($pdo, true);

$lines   = ["id,first_name,last_name,email,skills"];
foreach ($employees as $e) {
    $skills = Employee::getSkillNames($pdo, (int)$e['id']); // list<string>
    $skillsStr = implode('|', $skills);                     // => string

    $first = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], (string)($e['first_name'] ?? ''));
    $last  = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], (string)($e['last_name'] ?? ''));
    $email = str_replace(['"', "\n", "\r"], ['""', ' ', ' '], (string)($e['email'] ?? ''));

    $lines[] = sprintf(
        '%d,"%s","%s","%s","%s"',
        (int)$e['id'],
        $first,
        $last,
        $email,
        str_replace('"', '""', $skillsStr)
    );
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employees.csv"');
echo implode("\n", $lines);
