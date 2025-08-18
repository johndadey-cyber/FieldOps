(function(){
  function getContainer(){
    var c = document.getElementById('fieldops-toast-container');
    if(!c){
      c = document.createElement('div');
      c.id = 'fieldops-toast-container';
      c.className = 'toast-container position-fixed top-0 end-0 p-3';
      c.style.zIndex = '1080';
      c.setAttribute('aria-live','polite');
      c.setAttribute('aria-atomic','true');
      document.body.appendChild(c);
    }
    return c;
  }
  window.FieldOpsToast = window.FieldOpsToast || {
    /**
     * Show a Bootstrap toast.
     * @param {string} message
     * @param {'success'|'danger'|'warning'|'info'} [variant='success']
     * @param {number} [delay=4000]
     */
    show: function(message, variant, delay){
      variant = variant || 'success';
      delay = typeof delay === 'number' ? delay : 4000;
      try {
        var container = getContainer();
        var el = document.createElement('div');
        el.className = 'toast align-items-center text-bg-' + variant + ' border-0';
        el.setAttribute('role','alert');
        el.setAttribute('aria-live','assertive');
        el.setAttribute('aria-atomic','true');
        el.setAttribute('tabindex','-1');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"></div>'+
                       '<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        el.querySelector('.toast-body').textContent = message;
        container.appendChild(el);
        var previous = document.activeElement;
        var toast = new bootstrap.Toast(el, {delay: delay, autohide: true});
        el.addEventListener('shown.bs.toast', function(){
          try { el.focus(); } catch(_){}
        });
        el.addEventListener('hidden.bs.toast', function(){
          el.remove();
          if(previous && typeof previous.focus === 'function'){
            previous.focus();
          }
        });
        toast.show();
      } catch(e) {
        try { alert(message); } catch(_) {}
      }
    }
  };
})();
