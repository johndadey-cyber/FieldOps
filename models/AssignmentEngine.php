<?php
/**
 * AssignmentEngine — eligibility + helpers
 * Drop-in ready for weekday names OR numbers in employee_availability.day_of_week
 * Requires: config/database.php (get_pdo()), helpers (auth, JsonResponse), PHP 8+
 */
class AssignmentEngine
{
    /** @var PDO */
    private PDO $pdo;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function jobSummary(int $jobId): array
    {
        $sql = "SELECT j.id, j.customer_id, j.description, j.scheduled_date, j.scheduled_time, j.duration_minutes, j.status
                  , c.first_name AS customer_first_name, c.last_name AS customer_last_name
                  , c.latitude AS cust_lat, c.longitude AS cust_lng
                FROM jobs j
                JOIN customers c ON c.id = j.customer_id
                WHERE j.id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $jobId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException("Job not found");
        return $row;
    }

    public function eligibleEmployeesForJob(int $jobId, string $date, string $time): array
    {
        $job = $this->jobSummary($jobId);
        $duration = (int)($job['duration_minutes'] ?? 120);
        $startTs = strtotime($date . ' ' . $time);
        $endTs   = $startTs + ($duration * 60);

        // Required job types
        $reqTypesStmt = $this->pdo->prepare("SELECT job_type_id FROM job_job_types WHERE job_id = :jid");
        $reqTypesStmt->execute([':jid' => $jobId]);
        $reqTypes = array_map('intval', array_column($reqTypesStmt->fetchAll(PDO::FETCH_ASSOC), 'job_type_id'));

        // Candidates: active techs
        $candSql = "SELECT e.id AS employee_id, p.first_name, p.last_name, p.latitude AS emp_lat, p.longitude AS emp_lng
                    FROM employees e
                    LEFT JOIN people p ON p.id = e.person_id
                    WHERE e.is_active = 1 AND (e.role_id = 1 OR e.role_id IS NULL)";
        $cand = $this->pdo->query($candSql)->fetchAll(PDO::FETCH_ASSOC);

        $qualified = []; $notQualified = [];

        $dowNum  = (int)date('w', $startTs);      // 0..6
        $dowName = date('l',  $startTs);          // 'Friday'
        $endTimeStr = date('H:i:s', $endTs);

        foreach ($cand as $row) {
            $eid = (int)$row['employee_id'];
            $reasons = [];

            // Skill check: employee must have ALL required types
            if (!empty($reqTypes)) {
                $q = $this->pdo->prepare(
                    "SELECT COUNT(DISTINCT job_type_id) FROM employee_skills
                     WHERE employee_id = :eid AND job_type_id IN (" . implode(',', array_map('intval', $reqTypes)) . ")"
                );
                $q->execute([':eid' => $eid]);
                $cnt = (int)($q->fetchColumn() ?: 0);
                if ($cnt < count($reqTypes)) $reasons[] = 'missing_skills';
            }

            // Availability: support either weekday NAME or NUMBER stored as text
            $availQ = $this->pdo->prepare(
                "SELECT 1
                   FROM employee_availability
                  WHERE employee_id = :eid
                    AND (day_of_week = :dow_name OR day_of_week = :dow_num)
                    AND start_time <= :t
                    AND end_time   >= :t2
                  LIMIT 1"
            );
            $availQ->execute([
                ':eid'      => $eid,
                ':dow_name' => $dowName,                // e.g., 'Friday'
                ':dow_num'  => (string)$dowNum,         // e.g., '5'
                ':t'        => $time,
                ':t2'       => $endTimeStr
            ]);
            if (!$availQ->fetch()) $reasons[] = 'not_available';

            // Time conflicts with other jobs that day
            $confQ = $this->pdo->prepare(
                "SELECT 1
                   FROM job_employee_assignment a
                   JOIN jobs j ON j.id = a.job_id
                  WHERE a.employee_id = :eid
                    AND j.scheduled_date = :d
                    AND j.scheduled_time IS NOT NULL
                    AND j.duration_minutes IS NOT NULL
                    AND (TIMESTAMP(:d, :t) < TIMESTAMP(j.scheduled_date, ADDTIME(j.scheduled_time, SEC_TO_TIME(j.duration_minutes*60))))
                    AND (TIMESTAMP(j.scheduled_date, j.scheduled_time) < TIMESTAMP(:d, :t2))
                  LIMIT 1"
            );
            $confQ->execute([':eid'=>$eid, ':d'=>$date, ':t'=>$time, ':t2'=>$endTimeStr]);
            if ($confQ->fetch()) $reasons[] = 'time_conflict';

            // Distance (null-safe)
            $distanceKm = null;
            if (!empty($job['cust_lat']) && !empty($job['cust_lng']) && !empty($row['emp_lat']) && !empty($row['emp_lng'])) {
                $distanceKm = $this->haversineKm((float)$job['cust_lat'], (float)$job['cust_lng'], (float)$row['emp_lat'], (float)$row['emp_lng']);
            }

            $entry = [
                'employee_id' => $eid,
                'name'        => trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')),
                'distanceKm'  => $distanceKm,
                'reasons'     => $reasons,
            ];
            if (empty($reasons)) $qualified[] = $entry; else $notQualified[] = $entry;
        }

        // Rank: by distance asc (NULLs last), then name
        usort($qualified, function($a,$b){
            if ($a['distanceKm'] === null && $b['distanceKm'] === null) return strcmp($a['name'], $b['name']);
            if ($a['distanceKm'] === null) return 1;
            if ($b['distanceKm'] === null) return -1;
            if ($a['distanceKm'] == $b['distanceKm']) return strcmp($a['name'], $b['name']);
            return ($a['distanceKm'] < $b['distanceKm']) ? -1 : 1;
        });
        usort($notQualified, function($a,$b){
            if (count($a['reasons']) == count($b['reasons'])) return strcmp($a['name'],$b['name']);
            return (count($a['reasons']) < count($b['reasons'])) ? -1 : 1;
        });

        return ['job' => $job, 'qualified' => $qualified, 'notQualified' => $notQualified];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat/2)**2 + cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)**2;
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return $R * $c;
    }
}
