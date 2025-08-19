<?php

declare(strict_types=1);

// /public/api/jobs.php
require __DIR__ . '/../_cli_guard.php';
require __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Job.php';

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
        $mappedStatuses[] = str_replace(' ', '_', strtolower($s));
    }
    if (!$mappedStatuses) {
        $mappedStatuses = ['scheduled', 'assigned', 'in_progress', 'completed'];
    }

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


    if ($search !== '') {
        $needle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $like = "%{$needle}%";
        $args[':q1'] = $like;
        $args[':q2'] = $like;
        $args[':q3'] = $like;
        $args[':q4'] = $like;
        $where[] = '(c.first_name LIKE :q1 OR c.last_name LIKE :q2 OR c.address_line1 LIKE :q3 OR c.city LIKE :q4)';
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

        // Required job skills for this job
        $skillRows = Job::getSkillsForJob($pdo, $jobId);
        $skills = array_map(static fn($r) => ['name' => $r['name']], $skillRows);

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
            'duration_minutes' => (int)$r['duration_minutes'],
            'customer' => [
                'id' => (int)$r['customer_id'],
                'first_name' => (string)$r['first_name'],
                'last_name' => (string)$r['last_name'],
                'address_line1' => (string)$r['address_line1'],
                'city' => (string)$r['city'],
            ],
            // Include required job skills; provide both legacy and current keys
            // so older clients expecting `skills` still receive data.
            'skills' => $skills,
            'job_skills' => $skills,
            'assigned_employees' => $emps,
            'status' => (string)$r['status'],
        ];
    }

    echo json_encode($jobs);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
