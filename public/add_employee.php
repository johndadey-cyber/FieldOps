<?php
declare(strict_types=1);
require __DIR__ . '/_cli_guard.php';

$mode     = 'add';
$employee = [];
$skillIds = []; // no preselected skills on add

require __DIR__ . '/employee_form.php';

