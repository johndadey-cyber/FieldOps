<?php
// phpstan-bootstrap.php
declare(strict_types=1);

// Make PHPStan aware of your app symbols.
require_once __DIR__ . '/config/database.php';

// Load all models so static methods like Job::getAll() are known.
foreach (glob(__DIR__ . '/models/*.php') as $f) {
    require_once $f;
}

// If you have common helpers (e.g., s(), sticky(), MAPS_API_KEY), require them here:
// @example: require_once __DIR__ . '/config/constants.php';
// @example: require_once __DIR__ . '/partials/helpers.php';

