<?php
// /partials/footer.php
if (!defined('FOOTER_INCLUDED')) {
    define('FOOTER_INCLUDED', true);
}
?>

    </div> <!-- /.container-fluid -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
            crossorigin="anonymous"></script>

    <!-- Global Toast Container -->
    <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1080">
        <div id="globalToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="toast-header">
                <strong class="me-auto">FieldOps</strong>
                <small>Now</small>
                <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
            <div class="toast-body">Saved.</div>
        </div>
    </div>

    <script>
    // Toast helper
    window.showToast = function (msg, variant) {
        try {
            var toastEl = document.getElementById('globalToast');
            if (!toastEl) { alert(msg); return; }
            var body = toastEl.querySelector('.toast-body');
            var header = toastEl.querySelector('.toast-header');
            body.textContent = msg;
            header.classList.remove('bg-success','bg-danger','bg-warning','bg-info','text-white');
            if (variant && ['success','danger','warning','info'].includes(variant)) {
                header.classList.add('bg-' + variant, 'text-white');
            }
            var toast = bootstrap.Toast.getOrCreateInstance(toastEl);
            toast.show();
        } catch (e) {
            console.error('showToast error:', e);
            alert(msg);
        }
    };

    // No-op shims so modal calls never error if page-specific refresh isn't defined
    if (typeof window.refreshJobsTable !== 'function') {
        window.refreshJobsTable = function(){ console.info('refreshJobsTable() called — no implementation on this page.'); };
    }
    if (typeof window.refreshAssignmentsTable !== 'function') {
        window.refreshAssignmentsTable = function(){ console.info('refreshAssignmentsTable() called — no implementation on this page.'); };
    }
    </script>

  </body>
</html>
