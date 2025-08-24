<?php
// /partials/navbar.php
// Bootstrap 5 navbar with role-based link visibility

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$role    = $_SESSION['role'] ?? 'guest';
$current = ltrim($_SERVER['SCRIPT_NAME'] ?? '', '/');

$items = [];

if ($role === 'admin' || $role === 'dispatcher') {
    $items = [
        'jobs.php' => 'Jobs',
        'employees.php' => 'Employees',
        'customers.php' => 'Customers',
        'assignments.php' => 'Assignments',
        'availability_manager.php' => 'Availability',
    ];
    if ($role === 'admin') {
        $items = ['admin/index.php' => 'Admin'] + $items;
    }
} elseif ($role === 'tech' || $role === 'field_tech') {
    $items = [
        'tech_jobs.php' => "Today's Jobs",
    ];
}

$home = match (true) {
    $role === 'admin' || $role === 'dispatcher' => '/jobs.php',
    $role === 'tech' || $role === 'field_tech' => '/tech_jobs.php',
    default => '/login.php',
};
?>
<nav class="navbar navbar-expand-lg navbar-light bg-light mb-4">
  <div class="container-fluid">
    <a class="navbar-brand" href="<?= $home ?>">FieldOps</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav me-auto mb-2 mb-lg-0">
        <?php foreach ($items as $file => $label): ?>
          <?php $active = $current === $file ? 'active' : ''; ?>
          <li class="nav-item">
            <a class="nav-link <?= $active ?>" href="/<?= $file ?>"><?= htmlspecialchars($label) ?></a>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
</nav>
