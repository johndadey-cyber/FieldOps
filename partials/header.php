<?php
// /partials/header.php
if (!defined('HEADER_INCLUDED')) {
    define('HEADER_INCLUDED', true);
}
$title = $title ?? 'FieldOps';
$headExtra = $headExtra ?? '';
$bodyAttrs = isset($bodyAttrs) && $bodyAttrs !== '' ? ' ' . $bodyAttrs : '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($title) ?></title>

  <!-- Bootstrap 5 CSS -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
        crossorigin="anonymous">

  <!-- Optional: app styles -->
  <link rel="stylesheet" href="/css/app.css">
  <link rel="icon" type="image/png" href="/favicon.png">
  <?= $headExtra ?>
</head>
<body class="bg-light"<?= $bodyAttrs ?>>
  <?php require __DIR__ . '/navbar.php'; ?>
  <div class="container-fluid py-4">
    <header class="mb-4">
      <h1 class="h3"><?= htmlspecialchars($title) ?></h1>
    </header>
