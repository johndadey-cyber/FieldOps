<?php
declare(strict_types=1);

function createTestPdo(): PDO {
    $dsn = getenv('FIELDOPS_TEST_DSN') ?: sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '8889',
        getenv('DB_NAME') ?: 'fieldops_integration'
    );
    $user = getenv('FIELDOPS_TEST_USER') ?: getenv('DB_USER') ?: 'root';
    $pass = getenv('FIELDOPS_TEST_PASS') ?: getenv('DB_PASS') ?: 'root';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}
