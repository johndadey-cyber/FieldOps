<?php

declare(strict_types=1);

// /public/api/jobs.php
require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../../config/database.php';
require __DIR__ . '/../../models/JobType.php';

header('Content-Type: application/json');

try {
    $pdo = getPDO();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $today = date('Y-m-d');
    $start = $_GET['start'] ?? $today;
    $end   = $_GET['end']   ?? date('Y-m-d', strtotime('+7 days'));
    $showPast = isset($_GET['show_past']) && $_GET['show_past'] === '1';
    if (!$showPast && $start < $today) {
        $start = $today;
    }

    $statusParam = $_GET['status'] ?? '';
    $statusList = array_filter(array_map('trim', explode(',', $statusParam)));
    $mappedStatuses = [];
    foreach ($statusList as $s) {
        $key = str_replace(' ', '_', strtolower($s));
        if ($key === 'scheduled') {
            $mappedStatuses[] = 'scheduled';
            $mappedStatuses[] = 'assigned';
        } else {
            $mappedStatuses[] = $key;
        }
    }
    if (!$mappedStatuses) {
        $mappedStatuses = ['scheduled', 'assigned', 'in_progress'];
    }

    $jobTypeParam = $_GET['job_type'] ?? '';
    $jobTypeIds = array_values(array_filter(array_map('intval', explode(',', $jobTypeParam))));
    $search = trim($_GET['search'] ?? '');

    $where = ['j.scheduled_date BETWEEN :start AND :end'];
    $args = [':start' => $start, ':end' => $end];

    if ($mappedStatuses) {
        $ph = [];
        foreach ($mappedStatuses as $i => $st) {
            $key = ':st' . $i;
            $ph[] = $key;
            $args[$key] = $st;
        }
        $where[] = 'j.status IN (' . implode(',', $ph) . ')';
    }

    if ($jobTypeIds) {
        $ph = [];
        foreach ($jobTypeIds as $i => $jt) {
            $key = ':jt' . $i;
            $ph[] = $key;
            $args[$key] = $jt;
        }
        $where[] = 'EXISTS (SELECT 1 FROM job_job_types jj WHERE jj.job_id = j.id AND jj.job_type_id IN (' . implode(',', $ph) . '))';
    }

    if ($search !== '') {
        $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $args[':q1'] = "%{$needle}%";
        $args[':q2'] = "%{$needle}%";
        $args[':q3'] = "%{$needle}%";
        $args[':q4'] = "%{$needle}%";
        $where[] = "(c.first_name LIKE :q1 ESCAPE '\\' OR c.last_name LIKE :q2 ESCAPE '\\' OR c.address_line1 LIKE :q3 ESCAPE '\\' OR c.city LIKE :q4 ESCAPE '\\')";
    }

    $sql = "SELECT j.id, j.scheduled_date, j.scheduled_time, j.status, COALESCE(j.duration_minutes,60) AS duration_minutes,
                   c.id AS customer_id, c.first_name, c.last_name, c.address_line1, c.city
            FROM jobs j
            JOIN customers c ON c.id = j.customer_id
            " . ($where ? ('WHERE ' . implode(' AND ', $where)) : '') . "
            ORDER BY j.scheduled_date ASC, j.scheduled_time ASC, j.id ASC
            LIMIT 500";

    $st = $pdo->prepare($sql);
    $st->execute($args);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $jobs = [];
    foreach ($rows as $r) {
        $jobId = (int)$r['id'];

        // Required job skills for this job derived from its job type(s)
        $skills = JobType::getRequiredSkillsForJob($pdo, $jobId);
        $skills = array_map(fn($n) => ['name' => $n], $skills);

        // Use distinct placeholders for each occurrence when native prepares are enabled
        // to avoid "Invalid parameter number" errors with drivers that don't allow
        // reusing the same named parameter multiple times.
        $stEmp = $pdo->prepare("SELECT e.id, p.first_name, p.last_name FROM (
                   SELECT employee_id FROM job_employee WHERE job_id = :jid1
                   UNION
                   SELECT employee_id FROM job_employee_assignment WHERE job_id = :jid2
               ) je
               JOIN employees e ON e.id = je.employee_id
               JOIN people p ON p.id = e.person_id
               ORDER BY p.last_name, p.first_name");
        $stEmp->execute([':jid1' => $jobId, ':jid2' => $jobId]);
        $emps = [];
        while ($e = $stEmp->fetch(PDO::FETCH_ASSOC)) {
            $empId = (int)$e['id'];
            $hasConflict = false;
            if (!empty($r['scheduled_time'])) {
                $durSeconds = ((int)$r['duration_minutes']) * 60;
                $confSql = "SELECT 1
                            FROM job_employee_assignment a
                            JOIN jobs j2 ON j2.id = a.job_id
                            WHERE a.employee_id = :eid AND a.job_id <> :job_id
                              AND j2.scheduled_date = :d1
                              AND j2.scheduled_time IS NOT NULL
                              AND j2.duration_minutes IS NOT NULL
                              AND TIMESTAMP(j2.scheduled_date, j2.scheduled_time) < TIMESTAMP(:d2, ADDTIME(:t1, SEC_TO_TIME(:dur)))
                              AND TIMESTAMP(:d3, :t2) < TIMESTAMP(j2.scheduled_date, ADDTIME(j2.scheduled_time, SEC_TO_TIME(j2.duration_minutes*60)))
                              LIMIT 1";
                $confSt = $pdo->prepare($confSql);
                $confSt->execute([
                    ':eid' => $empId,
                    ':job_id' => $jobId,
                    ':d1' => $r['scheduled_date'],
                    ':d2' => $r['scheduled_date'],
                    ':d3' => $r['scheduled_date'],
                    ':t1' => $r['scheduled_time'],
                    ':t2' => $r['scheduled_time'],
                    ':dur' => $durSeconds,
                ]);
                $hasConflict = (bool)$confSt->fetchColumn();
            }
            $emps[] = [
                'id' => $empId,
                'first_name' => (string)$e['first_name'],
                'last_name'  => (string)$e['last_name'],
                'has_conflict' => $hasConflict,
            ];
        }

        $jobs[] = [
            'job_id' => $jobId,
            'scheduled_date' => (string)$r['scheduled_date'],
            'scheduled_time' => $r['scheduled_time'] !== null ? (string)$r['scheduled_time'] : null,
            'customer' => [
                'id' => (int)$r['customer_id'],
                'first_name' => (string)$r['first_name'],
                'last_name' => (string)$r['last_name'],
                'address_line1' => (string)$r['address_line1'],
                'city' => (string)$r['city'],
            ],
            'job_skills' => $skills,
            // 'job_types' => $types, // deprecated
            'assigned_employees' => $emps,
            'status' => (string)$r['status'],
        ];
    }

    echo json_encode($jobs);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
