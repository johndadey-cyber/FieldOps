<?php
declare(strict_types=1);
require __DIR__ . '/../config/database.php';
try {
  $pdo = getPDO();
  $row = $pdo->query('SELECT 1 AS ok')->fetch(PDO::FETCH_ASSOC);
  echo "[DB OK] " . json_encode($row) . PHP_EOL;
} catch (Throwable $e) {
  echo "[DB FAIL] " . $e->getMessage() . PHP_EOL;
}
