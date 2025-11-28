(function(){
  // Close on ESC - hanya jika offcanvas terbuka
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') {
      var offcanvasElement = document.querySelector('.offcanvas.show');
      if(offcanvasElement) {
        var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement);
        if(offcanvas) {
          offcanvas.hide();
        }
      }
    }
  });
})();
