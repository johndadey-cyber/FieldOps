<?php
declare(strict_types=1);

/**
 * Run outstanding schema migrations against the test database.
 *
 * This script can be executed directly or included from other scripts.
 * When included, call migrateTestDb($pdo) with an existing PDO connection.
 */

/**
 * Apply migrations in tests/migrations using the supplied PDO connection.
 */
function migrateTestDb(PDO $pdo): void
{
    // Ensure a migrations tracking table exists
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) UNIQUE NOT NULL,
            run_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
    );

    $dir = __DIR__ . '/../tests/migrations';
    if (!is_dir($dir)) {
        echo "⚠️  No migrations directory found at {$dir}.\n";
        return;
    }

    $files = glob($dir . '/*.sql') ?: [];
    sort($files);

    foreach ($files as $file) {
        $name = basename($file);
        $stmt = $pdo->prepare('SELECT 1 FROM migrations WHERE filename = ?');
        $stmt->execute([$name]);
        if ($stmt->fetchColumn()) {
            continue; // already run
        }

        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Failed to read migration {$name}");
        }

        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO migrations (filename) VALUES (?)');
        $stmt->execute([$name]);
        echo "Applied migration: {$name}\n";
    }

    echo "✅ Schema migrations complete.\n";
}

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['argv'][0])) {
    $host = getenv('DB_HOST') ?: '127.0.0.1';
    $port = getenv('DB_PORT') ?: '3306';
    $db   = getenv('DB_NAME') ?: 'fieldops_integration';
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') ?: '1234!@#$';

    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    migrateTestDb($pdo);
}
