<?php
declare(strict_types=1);

/**
 * getPDO()
 * - Reads config from config/local.env.php (may return an array OR define variables),
 * - Applies environment variables as final overrides,
 * - Returns a singleton PDO (MySQL) with safe defaults.
 *
 * NOTE: Do NOT auto-append "_test". Set DB_NAME explicitly in local.env.php or env.
 */
if (!function_exists('getPDO')) {
    function getPDO(): PDO
    {
        static $pdo = null;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        // Defaults (MAMP-friendly)
        $cfg = [
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '8889',
            // Default to the integration DB when running in test env
            'DB_NAME' => getenv('APP_ENV') === 'test' ? 'fieldops_integration' : 'fieldops',
            'DB_USER' => 'root',
            'DB_PASS' => 'root',
            'APP_ENV' => getenv('APP_ENV') ?: 'dev',
        ];

        // Optional local env file
        $localEnv = __DIR__ . '/local.env.php';
        if (is_file($localEnv)) {
            $ret = require $localEnv;

            if (is_array($ret)) {
                // Merge array keys
                foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','APP_ENV'] as $k) {
                    if (array_key_exists($k, $ret) && $ret[$k] !== '' && $ret[$k] !== null) {
                        $cfg[$k] = (string)$ret[$k];
                    }
                }
            } else {
                // Legacy style: variables defined in the included file
                foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','APP_ENV'] as $k) {
                    if (isset(${$k}) && ${$k} !== '') {
                        $cfg[$k] = (string)${$k};
                    }
                }
            }
        }

        // Env var overrides (final say)
        foreach (['DB_HOST','DB_PORT','DB_NAME','DB_USER','DB_PASS','APP_ENV'] as $k) {
            $v = getenv($k);
            if ($v !== false && $v !== '') {
                $cfg[$k] = $v;
            }
        }

        // Build DSN (no implicit _test logic)
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $cfg['DB_HOST'],
            $cfg['DB_PORT'],
            $cfg['DB_NAME']
        );

        try {
            $pdo = new PDO($dsn, $cfg['DB_USER'], $cfg['DB_PASS'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
            return $pdo;
        } catch (PDOException $e) {
            // Helpful but not secret-spilling
            throw new PDOException('DB connection failed: ' . $e->getMessage(), (int)$e->getCode());
        }
    }
}
