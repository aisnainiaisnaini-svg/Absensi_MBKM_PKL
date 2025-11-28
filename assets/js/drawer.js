(function(){
  // Menangani backdrop untuk offcanvas
  document.addEventListener('click', function(e){
    if(e.target.matches('.drawer-backdrop')) {
      // Tutup offcanvas saat backdrop diklik
      var offcanvasElement = document.querySelector('.offcanvas.show');
      if(offcanvasElement) {
        var offcanvas = bootstrap.Offcanvas.getInstance(offcanvasElement);
        if(offcanvas) {
          offcanvas.hide();
        }
      }
    }
  });

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

  // Tampilkan backdrop saat offcanvas ditampilkan
  document.addEventListener('show.bs.offcanvas', function () {
    var backdrop = document.querySelector('.drawer-backdrop');
    if(backdrop) {
      backdrop.classList.add('show');
    }
  });

  // Sembunyikan backdrop saat offcanvas disembunyikan
  document.addEventListener('hidden.bs.offcanvas', function () {
    var backdrop = document.querySelector('.drawer-backdrop');
    if(backdrop) {
      backdrop.classList.remove('show');
    }
  });
})();
