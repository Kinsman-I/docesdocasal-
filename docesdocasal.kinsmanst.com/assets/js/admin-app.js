(() => {
  const body = document.body;
  const opens = document.querySelectorAll('[data-admin-menu-open]');
  const closes = document.querySelectorAll('[data-admin-menu-close]');

  const openMenu = () => body.classList.add('admin-menu-open');
  const closeMenu = () => body.classList.remove('admin-menu-open');

  opens.forEach(el => el.addEventListener('click', openMenu));
  closes.forEach(el => el.addEventListener('click', closeMenu));
  document.addEventListener('keydown', e => { if (e.key === 'Escape') closeMenu(); });

  document.querySelectorAll('.admin-side a').forEach(a => {
    a.addEventListener('click', () => {
      if (window.innerWidth <= 980) closeMenu();
    });
  });

  // Melhora uploads no celular: mostra o nome do arquivo selecionado.
  document.querySelectorAll('input[type="file"]').forEach(input => {
    input.addEventListener('change', () => {
      const file = input.files && input.files[0];
      if (!file) return;
      const parent = input.closest('.upload-box,.upload,.card') || input.parentElement;
      let note = parent && parent.querySelector('.admin-file-note');
      if (!note && parent) {
        note = document.createElement('small');
        note.className = 'admin-file-note';
        parent.appendChild(note);
      }
      if (note) note.textContent = `Selecionado: ${file.name}`;
    });
  });

  // Evita que tabelas largas pareçam quebradas no celular.
  document.querySelectorAll('.table-wrap').forEach(wrap => {
    if (!wrap.querySelector('.admin-scroll-hint') && wrap.scrollWidth > wrap.clientWidth) {
      wrap.dataset.scrollable = '1';
    }
  });
})();
