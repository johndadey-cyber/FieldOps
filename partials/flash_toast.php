<?php
declare(strict_types=1);

// Guard against double-includes
if (!defined('FIELDOPS_FLASH_TOAST_INCLUDED')) {
    define('FIELDOPS_FLASH_TOAST_INCLUDED', true);

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Determine message + style from session/URL
    $toastMsg = null;
    $toastVariant = 'success'; // success|danger|info|warning

    // 1) Explicit success flash
    if (!empty($_SESSION['flash']['success'])) {
        $toastMsg = (string)$_SESSION['flash']['success'];
        unset($_SESSION['flash']['success']);
    }

    // 2) Validation/global errors
    if ($toastMsg === null && !empty($_SESSION['flash']['errors']['_global'] ?? '')) {
        $toastMsg = (string)$_SESSION['flash']['errors']['_global'];
        $toastVariant = 'danger';
        unset($_SESSION['flash']['errors']['_global']);
    }

    // 3) URL hints — customers & assignments
    if ($toastMsg === null) {
        if (isset($_GET['created'], $_GET['id']) && $_GET['created'] === '1' && ctype_digit((string)$_GET['id'])) {
            $toastMsg = 'Customer created (ID #' . (int)$_GET['id'] . ').';
            $toastVariant = 'success';
        } elseif (isset($_GET['assignment_saved']) && $_GET['assignment_saved'] === '1') {
            $toastMsg = 'Assignment saved.';
            $toastVariant = 'success';
        } elseif (isset($_GET['assignment_unassigned']) && $_GET['assignment_unassigned'] === '1') {
            $toastMsg = 'Unassigned.';
            $toastVariant = 'success';
        } elseif (!empty($_GET['success'])) {
            // Generic message via ?success=Your%20message
            $toastMsg = (string)$_GET['success'];
            $toastVariant = 'success';
        }
    }

    ?>
    <script src="/js/toast.js"></script>
    <script>
    (function () {
      <?php if ($toastMsg !== null): ?>
        window.addEventListener('DOMContentLoaded', function () {
          if (window.FieldOpsToast) {
            FieldOpsToast.show(<?= json_encode($toastMsg) ?>, <?= json_encode($toastVariant) ?>);
          } else {
            alert(<?= json_encode($toastMsg) ?>);
          }
          try {
            var url = new URL(window.location.href);
            ['created','id','assignment_saved','assignment_unassigned','success'].forEach(function(k){ url.searchParams.delete(k); });
            window.history.replaceState({}, document.title, url.toString());
          } catch (e) {}
        });
      <?php endif; ?>
    })();
    </script>
    <?php
}
