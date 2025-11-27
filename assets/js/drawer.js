(function(){
  function toggleDrawer(open) {
    const sidebar = document.querySelector('.sidebar');
    const main = document.querySelector('.main-content');
    const backdrop = document.querySelector('.drawer-backdrop');
    if(!sidebar) return;
    if(open) {
      sidebar.classList.add('open');
      if(main) main.classList.add('shifted');
      if(backdrop) backdrop.classList.add('show');
      document.body.style.overflow = 'hidden';
    } else {
      sidebar.classList.remove('open');
      if(main) main.classList.remove('shifted');
      if(backdrop) backdrop.classList.remove('show');
      document.body.style.overflow = '';
    }
  }

  document.addEventListener('click', function(e){
    if(e.target.matches('.drawer-toggle')){
      const sidebar = document.querySelector('.sidebar');
      toggleDrawer(!sidebar.classList.contains('open'));
    }
    if(e.target.matches('.drawer-backdrop') || e.target.closest('.drawer-close')){
      toggleDrawer(false);
    }
  });

  // Close on ESC
  document.addEventListener('keydown', function(e){
    if(e.key === 'Escape') toggleDrawer(false);
  });
})();
