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

    // Always expose a JS helper so AJAX flows can show toasts, too.
    ?>
    <script>
    (function () {
      window.FieldOpsToast = window.FieldOpsToast || {
        /**
         * Show a Bootstrap toast (fallbacks to alert if Bootstrap JS missing).
         * @param {string} message
         * @param {'success'|'danger'|'info'|'warning'} [variant='success']
         */
        show: function (message, variant) {
          variant = variant || 'success';
          var wrap = document.createElement('div');
          wrap.className = 'position-fixed bottom-0 end-0 p-3';
          wrap.style.zIndex = '1080';

          var textBg = (variant === 'danger') ? 'text-bg-danger'
                    : (variant === 'warning') ? 'text-bg-warning'
                    : (variant === 'info') ? 'text-bg-info'
                    : 'text-bg-success';

          wrap.innerHTML =
            '<div class="toast align-items-center ' + textBg + ' border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="4500">' +
              '<div class="d-flex">' +
                '<div class="toast-body"></div>' +
                '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>' +
              '</div>' +
            '</div>';

          document.body.appendChild(wrap);
          var toastEl = wrap.querySelector('.toast');
          var body = wrap.querySelector('.toast-body');
          body.textContent = message || 'Done';

          function doShow() {
            try {
              var t = new bootstrap.Toast(toastEl);
              t.show();
              toastEl.addEventListener('hidden.bs.toast', function() {
                wrap.remove();
              });
            } catch (e) {
              alert(message || 'Done'); // Fallback
              wrap.remove();
            }
          }

          if (!(window.bootstrap && window.bootstrap.Toast)) {
            var s = document.createElement('script');
            s.src = 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js';
            s.async = true;
            s.onload = doShow;
            document.head.appendChild(s);
          } else {
            doShow();
          }
        }
      };

      // If server/URL provided a message, show it once and then clean the URL.
      <?php if ($toastMsg !== null): ?>
        (function(){
          window.FieldOpsToast.show(<?= json_encode($toastMsg) ?>, <?= json_encode($toastVariant) ?>);
          try {
            var url = new URL(window.location.href);
            ['created','id','assignment_saved','assignment_unassigned','success'].forEach(function(k){ url.searchParams.delete(k); });
            window.history.replaceState({}, document.title, url.toString());
          } catch (e) {}
        })();
      <?php endif; ?>
    })();
    </script>
    <?php
}
