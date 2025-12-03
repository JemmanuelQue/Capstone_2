// Common JS for accounting pages
(function(){
  function updateDateTime(){
    var now = new Date();
    var dateEl = document.getElementById('current-date');
    var timeEl = document.getElementById('current-time');
    if(dateEl && timeEl){
      dateEl.textContent = now.toLocaleDateString('en-US', {weekday:'long', year:'numeric', month:'long', day:'numeric'});
      timeEl.textContent = now.toLocaleTimeString('en-US', {hour:'2-digit', minute:'2-digit'});
    }
  }
  document.addEventListener('DOMContentLoaded', function(){
    updateDateTime();
    setInterval(updateDateTime, 1000);

    var toggle = document.getElementById('toggleSidebar');
    if(toggle){
      toggle.addEventListener('click', function(){
        var sb = document.getElementById('sidebar');
        if(sb){ sb.classList.toggle('collapsed'); }
      });
    }

    if (window.bootstrap) {
      var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
      tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
      });
    }
  });
})();
