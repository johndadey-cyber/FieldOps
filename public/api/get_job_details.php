<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Job.php';

header('Content-Type: application/json');

$pdo = getPDO();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$row = $id > 0 ? Job::getJobAndCustomerDetails($pdo, $id) : null;

echo json_encode(['ok' => (bool)$row, 'job' => $row], JSON_UNESCAPED_SLASHES);
