<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Job.php';
require_once __DIR__ . '/../../models/JobNote.php';
require_once __DIR__ . '/../../models/JobPhoto.php';

header('Content-Type: application/json');

$pdo = getPDO();
$id  = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$job    = $id > 0 ? Job::getJobAndCustomerDetails($pdo, $id) : null;
$notes  = $id > 0 ? JobNote::listForJob($pdo, $id) : [];
$photos = $id > 0 ? JobPhoto::listForJob($pdo, $id) : [];

echo json_encode(['ok' => (bool)$job, 'job' => $job, 'notes' => $notes, 'photos' => $photos], JSON_UNESCAPED_SLASHES);
