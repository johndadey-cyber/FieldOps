<?php
// This file is loaded by PHPStan only. It is not used at runtime.

// Minimal shim to satisfy PHPStan when it analyzes files that reference global helpers.

/** Return a PDO connection (actual implementation lives in config/database.php) */
function getPDO(): PDO { throw new \LogicException('Stub only'); }
