<?php
// /public/api/assignments/assign.php
declare(strict_types=1);

header('Content-Type: application/json');

try {
    // --- DB bootstrap (robust relative path) ---
    $DB_PATHS = [
        __DIR__ . '/../../../config/database.php',
        __DIR__ . '/../../config/database.php',
        dirname(__DIR__, 3) . '/config/database.php',
    ];
    $dbPath = null;
    foreach ($DB_PATHS as $p) {
        if (is_file($p)) { $dbPath = $p; break; }
    }
    if (!$dbPath) {
        throw new RuntimeException('config/database.php not found (searched: ' . implode(', ', $DB_PATHS) . ')');
    }
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
        $st->execute([':t' => $name]);
        return $cache[$name] = ((int)$st->fetchColumn() > 0);
    };

    $useJE  = $tableExists($pdo, 'job_employee');
    $useJEA = $tableExists($pdo, 'job_employee_assignment');

    if (!$useJE && !$useJEA) {
        throw new RuntimeException('No assignment table found (job_employee or job_employee_assignment)');
    }

    // Load job window
    $job = null;
    {
        $st = $pdo->prepare("SELECT id, description, scheduled_date, scheduled_time, duration_minutes FROM jobs WHERE id = :id");
        $st->execute([':id' => $jobId]);
        $job = $st->fetch(PDO::FETCH_ASSOC);
        if (!$job) throw new RuntimeException("Job not found: $jobId");
    }

    $date = (string)($job['scheduled_date'] ?? '');
    $time = (string)($job['scheduled_time'] ?? '00:00:00');
    $dur  = (int)($job['duration_minutes'] ?? 60);
    if ($dur <= 0) $dur = 60;

    // Build window in MySQL terms
    // We'll evaluate availability using (day_of_week, start_time, end_time)
    $sqlDow = "DAYOFWEEK(:date)"; // MySQL: 1=Sun .. 7=Sat
    // UI/seed sometimes stores names (Tuesday). We'll allow both int and names.
    $dowMap = [
        1 => 'Sunday', 2 => 'Monday', 3 => 'Tuesday', 4 => 'Wednesday',
        5 => 'Thursday', 6 => 'Friday', 7 => 'Saturday'
    ];

    // --- Light validation: availability full coverage + (optional) conflict overlap ---
    // NOTE: This mirrors the UI’s minimum need: return 409 so the confirm dialog can appear.
    $issues = []; // [ ['employeeId'=>X, 'issues'=>['unavailable_for_job_window','time_conflict',...]] ]

    foreach ($employeeIds as $eid) {
        $empIssues = [];

        // FULL availability: employee_availability must have a row on that DOW that fully covers the job start/end.
        // Accept either numeric DOW (2) or name ('Tuesday')
        $st = $pdo->prepare("
      SELECT 1
      FROM employee_availability ea
      WHERE ea.employee_id = :eid
        AND (
          ea.day_of_week = $sqlDow
          OR ea.day_of_week = :dowName
          OR ea.day_of_week = :dowNameShort
        )
        AND ea.start_time <= :jobStart
        AND ea.end_time   >= :jobEnd
      LIMIT 1
    ");
        // Compute DOW name strings in PHP (so we’re consistent)
        $dowNum = (int)date('N', strtotime($date)); // 1=Mon..7=Sun
        $dowNumMySQL = (int)date('w', strtotime($date)) + 1; // 1=Sun..7=Sat
        // Normalize to canonical names
        $dowName = $dowMap[$dowNumMySQL] ?? date('l', strtotime($date)); // 'Tuesday'
        $dowNameShort = substr($dowName, 0, 3); // 'Tue' (defensive)
        $st->execute([
            ':eid'         => $eid,
            ':date'        => $date,
            ':dowName'     => $dowName,
            ':dowNameShort'=> $dowNameShort,
            ':jobStart'    => $time,
            ':jobEnd'      => date('H:i:s', strtotime("$time +$dur minutes")),
        ]);
        $hasFull = (bool)$st->fetchColumn();
        if (!$hasFull) {
            $empIssues[] = 'unavailable_for_job_window';
        }

        // OPTIONAL: basic time conflict against other assigned jobs that day (same tables we insert into)
        // Overlap: existing.start < newEnd && newStart < existing.end
        // We only have start_time/duration on jobs table, so conflict if another job for same employee overlaps window.
        $conflict = false;
        // choose a union of the two assignment tables we might use
        $confQ = "
      SELECT j2.id, j2.scheduled_time AS st, COALESCE(j2.duration_minutes, 60) AS dur
      FROM jobs j2
      JOIN (
        SELECT job_id, employee_id FROM job_employee WHERE employee_id = :eid
        UNION
        SELECT job_id, employee_id FROM job_employee_assignment WHERE employee_id = :eid
      ) x ON x.job_id = j2.id
      WHERE j2.scheduled_date = :date
        AND j2.id <> :jobId
    ";
        $stc = $pdo->prepare($confQ);
        $stc->execute([':eid' => $eid, ':date' => $date, ':jobId' => $jobId]);
        $newStart = strtotime("$date $time");
        $newEnd   = $newStart + $dur * 60;
        while ($row = $stc->fetch(PDO::FETCH_ASSOC)) {
            $s2 = strtotime($date . ' ' . ($row['st'] ?? '00:00:00'));
            $e2 = $s2 + (int)$row['dur'] * 60;
            if ($s2 < $newEnd && $newStart < $e2) { $conflict = true; break; }
        }
        if ($conflict) $empIssues[] = 'time_conflict';

        if (!empty($empIssues)) {
            $issues[] = ['employeeId' => $eid, 'issues' => $empIssues];
        }
    }

    // If any issues and not forcing, return 409 so the UI can confirm
    if (!$force && !empty($issues)) {
        http_response_code(409);
        echo json_encode([
            'ok'      => false,
            'code'    => 409,
            'message' => 'One or more selections have issues.',
            'details' => $issues,
        ]);
        exit;
    }

    // --- Insert + update status ---
    $pdo->beginTransaction();

    if ($useJE) {
        $ins = $pdo->prepare("INSERT IGNORE INTO job_employee (job_id, employee_id) VALUES (:j,:e)");
        foreach ($employeeIds as $eid) { $ins->execute([':j' => $jobId, ':e' => $eid]); }
    }

    if ($useJEA) {
        $ins2 = $pdo->prepare("INSERT IGNORE INTO job_employee_assignment (job_id, employee_id) VALUES (:j,:e)");
        foreach ($employeeIds as $eid) { $ins2->execute([':j' => $jobId, ':e' => $eid]); }
    }

    // Flip status to 'assigned' if any rows now exist
    $pdo->exec("
    UPDATE jobs j
    SET j.status = 'assigned'
    WHERE j.id = {$jobId}
      AND EXISTS (
        SELECT 1 FROM job_employee je WHERE je.job_id = j.id
        UNION
        SELECT 1 FROM job_employee_assignment jea WHERE jea.job_id = j.id
      )
  ");

    $pdo->commit();

    echo json_encode([
        'ok'            => true,
        'jobId'         => $jobId,
        'assignedCount' => count($employeeIds),
        'force'         => (bool)$force,
    ]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode([
        'ok'    => false,
        'code'  => 500,
        'error' => 'INTERNAL',
        'detail'=> $e->getMessage(),
    ]);
}

