// /public/js/employees.js
(() => {
  document.addEventListener('DOMContentLoaded', function() {
    const searchForm = document.getElementById('search-form');
    const searchInput = document.getElementById('employee-search');
    if (searchForm && searchInput) {
      searchForm.addEventListener('submit', function(e) {
        e.preventDefault();
        const params = new URLSearchParams(window.location.search);
        const val = searchInput.value.trim();
        if (val) { params.set('search', val); } else { params.delete('search'); }
        window.location = '?' + params.toString();
      });
    }

    const skillFilter = $('#skill-filter');
    skillFilter.select2({ width: '100%' });
    skillFilter.on('change', function() {
      const params = new URLSearchParams(window.location.search);
      params.delete('skills');
      params.delete('skills[]');
      const vals = skillFilter.val() || [];
      vals.forEach(v => params.append('skills[]', v));
      window.location = '?' + params.toString();
    });

    $('#select-all').on('change', function() {
      const checked = this.checked;
      $('.emp-check').prop('checked', checked);
    });

    $('#bulk-apply').on('click', function() {
      const action = $('#bulk-action').val();
      const ids = $('.emp-check:checked').map((_, el) => el.value).get();
      if (!action || ids.length === 0) { return; }
      $.post('employee_bulk_update.php', { action: action, ids: ids, csrf_token: window.CSRF_TOKEN }, function(res) {
        if (res.ok) { location.reload(); } else {
          const msg = res.error || 'Error';
          if (window.FieldOpsToast) { FieldOpsToast.show(msg, 'danger'); } else { alert(msg); }
        }
      }, 'json');
    });

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(el => new bootstrap.Tooltip(el));
  });
})();
