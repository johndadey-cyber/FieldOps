#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Guard Scan — Ensures all public/*.php entry points include _cli_guard.php
 * Skips underscore-prefixed files (internal includes).
 *
 * Usage:
 *   php scripts/guard_scan.php          # check only
 *   php scripts/guard_scan.php --fix    # auto-fix missing guards
 *   php scripts/guard_scan.php --debug  # debug output
 */

$fix   = in_array('--fix', $argv, true);
$debug = in_array('--debug', $argv, true);

$root      = dirname(__DIR__) ?: getcwd();
$publicDir = $root . '/public';

if (!is_dir($publicDir)) {
    fwrite(STDERR, "Public directory not found: {$publicDir}\n");
    exit(1);
}

$allFiles = glob($publicDir . '/*.php') ?: [];
// Filter out underscore-prefixed files like _cli_guard.php, _csrf.php, _auth.php
$files = array_values(array_filter($allFiles, function (string $f): bool {
    $b = basename($f);
    return $b !== '_cli_guard.php' && ($b === '' ? false : $b[0] !== '_');
}));

$issues = [];

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        fwrite(STDERR, "Failed to read {$file}\n");
        continue;
    }

    $hasStrict = preg_match('/^\s*<\?php\s+declare\(strict_types=1\);/m', $contents) === 1;
    $hasGuard  = strpos($contents, "require __DIR__ . '/_cli_guard.php';") !== false;

    if ($debug) {
        echo "[DEBUG] {$file}: strict=" . ($hasStrict ? 'yes' : 'no') . ", guard=" . ($hasGuard ? 'yes' : 'no') . "\n";
    }

    if (!$hasGuard) {
        $issues[] = $file;

        if ($fix && $hasStrict) {
            $pattern = '/^(<\?php\s+declare\(strict_types=1\);\s*)/m';
            $replacement = "$1require __DIR__ . '/_cli_guard.php';\n\n";
            $newContents = preg_replace($pattern, $replacement, $contents, 1);

            if ($newContents !== null) {
                file_put_contents($file, $newContents);
                if ($debug) {
                    echo "[FIXED] Added guard to {$file}\n";
                }
            } else {
                fwrite(STDERR, "Failed to add guard to {$file}\n");
            }
        }
    }
}

if (!empty($issues) && !$fix) {
    echo "Guard check found issues in:\n";
    foreach ($issues as $file) {
        echo " - {$file}\n";
    }
    exit(1);
}

if (empty($issues)) {
    echo "Guard check OK\n";
    exit(0);
}
