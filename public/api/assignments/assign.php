
<?php
// /public/api/assignments/assign.php
declare(strict_types=1);

header('Content-Type: application/json');

try {
  // --- DB bootstrap (robust relative path) ---

-  $dbPath = realpath(__DIR__ . '/../../config/database.php');
-  if (!$dbPath || !is_file($dbPath)) {
-    throw new RuntimeException("config/database.php not found (looked at: " . (__DIR__ . '/../../config/database.php') . ")");
+  $DB_PATHS = [
+    __DIR__ . '/../../../config/database.php',
+    __DIR__ . '/../../config/database.php',
+    dirname(__DIR__, 3) . '/config/database.php',
+  ];
+  $dbPath = null;
+  foreach ($DB_PATHS as $p) {
+    if (is_file($p)) { $dbPath = $p; break; }
+  }
+  if (!$dbPath) {
+    throw new RuntimeException('config/database.php not found (searched: ' . implode(', ', $DB_PATHS) . ')');

  $ROOT   = dirname(__DIR__, 3); // repo root
  $dbPath = realpath($ROOT . '/config/database.php');
  if (!$dbPath || !is_file($dbPath)) {
    throw new RuntimeException("config/database.php not found (looked at: {$ROOT}/config/database.php)");

  require $dbPath;
  // Explicit semicolon to prevent parse errors when deploying
  $pdo = getPDO();
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // --- Parse JSON body ---
  $raw = file_get_contents('php://input') ?: '';
  $in  = json_decode($raw, true);
  if (!is_array($in)) throw new InvalidArgumentException('Invalid JSON');

  $jobId       = isset($in['jobId']) ? (int)$in['jobId'] : 0;
  $employeeIds = array_values(array_unique(array_map('intval', (array)($in['employeeIds'] ?? []))));
  $force       = !empty($in['force']);

  if ($jobId <= 0)               throw new InvalidArgumentException('Missing jobId');
  if (count($employeeIds) === 0) throw new InvalidArgumentException('No employeeIds');

  // --- Helpers ---
  $tableExists = function(PDO $pdo, string $name): bool {
    static $cache = [];
    if (array_key_exists($name, $cache)) return $cache[$name];
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t");
    $st->execute([':t'=>$name]);
    return $cache[$name] = ((int)$st->fetchColumn() > 0);
  };

EOF
)
