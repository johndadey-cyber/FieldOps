<?php
declare(strict_types=1);

function createTestPdo(): PDO {
    $dsn = getenv('FIELDOPS_TEST_DSN');
    if ($dsn) {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        if (str_starts_with($dsn, 'sqlite:')) {
            seedSqliteSchema($pdo);
        }
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        getenv('DB_HOST') ?: '127.0.0.1',
        getenv('DB_PORT') ?: '3306',
        getenv('DB_NAME') ?: 'fieldops_integration'
    );
    $user = getenv('FIELDOPS_TEST_USER') ?: getenv('DB_USER') ?: 'root';
    $pass = getenv('FIELDOPS_TEST_PASS') ?: getenv('DB_PASS') ?: '1234!@#$';

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
}

function seedSqliteSchema(PDO $pdo): void {
    $dir = __DIR__ . '/../migrations';
    if (!is_dir($dir)) {
        return;
    }

    $files = glob($dir . '/*.sql');
    sort($files);

    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            continue;
        }

        $sql = preg_replace('/\bUNSIGNED\b/i', '', $sql);
        $sql = preg_replace('/(\w+)\s+INT\s+(?:UNSIGNED\s+)?(?:NOT\s+NULL\s+)?AUTO_INCREMENT\s+PRIMARY\s+KEY/i', '$1 INTEGER PRIMARY KEY AUTOINCREMENT', $sql);
        $sql = preg_replace('/UNIQUE KEY\s+\w+\s*\(([^)]+)\)/i', 'UNIQUE ($1)', $sql);
        $sql = preg_replace('/ON DUPLICATE KEY UPDATE[^;]*/i', '', $sql);
        $sql = preg_replace('/\)\s*ENGINE=InnoDB[^;]*;/i', ');', $sql);

        $statements = array_filter(array_map('trim', explode(';', $sql)));
        foreach ($statements as $statement) {
            if ($statement === '') {
                continue;
            }
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Skip statements that SQLite cannot execute
            }
        }
    }
}
