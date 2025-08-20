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
    <script src="/js/toast.js"></script>
    <?= $pageScripts ?? '' ?>

    <script>
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
