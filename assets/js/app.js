(function () {
  const page = document.documentElement;
  const body = document.body;

  // El tema elegido se conserva entre páginas y al cerrar el navegador.
  function changeTheme(theme) {
    page.setAttribute('data-theme', theme);
    localStorage.setItem('bh-theme', theme);

    document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
      icon.textContent = theme === 'dark' ? '☀' : '☾';
    });
  }

  changeTheme(localStorage.getItem('bh-theme') || 'dark');

  // Los eventos van en los botones, no en <html> (que contiene toda la página).
  document.querySelectorAll('[data-theme-toggle]').forEach(function (button) {
    button.addEventListener('click', function () {
      const nextTheme = page.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      changeTheme(nextTheme);
    });
  });

  // El menú se contrae en escritorio y aparece/desaparece en pantallas pequeñas.
  if (localStorage.getItem('bh-side') === 'small') body.classList.add('collapsed');
  document.querySelectorAll('[data-side]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (window.innerWidth <= 760) {
        body.classList.toggle('mobile');
      } else {
        body.classList.toggle('collapsed');
        localStorage.setItem('bh-side', body.classList.contains('collapsed') ? 'small' : 'full');
      }
    });
  });

  // Este cálculo anticipa el total; el servidor vuelve a calcular y validar al guardar.
  function calculateServices() {
    let total = 0;
    let minutes = 0;
    document.querySelectorAll('[data-service-check]:checked').forEach(function (checkbox) {
      total += Number(checkbox.dataset.price || 0);
      minutes += Number(checkbox.dataset.minutes || 0);
    });

    const totalLabel = document.querySelector('[data-service-total]');
    const minutesLabel = document.querySelector('[data-service-minutes]');
    if (totalLabel) totalLabel.textContent = '$' + total.toLocaleString('es-AR');
    if (minutesLabel) minutesLabel.textContent = minutes + ' min';
  }

  document.querySelectorAll('[data-service-check]').forEach(function (checkbox) {
    checkbox.addEventListener('change', calculateServices);
  });
  calculateServices();

  document.querySelectorAll('[data-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!confirm(button.dataset.confirm || '¿Confirmar acción?')) event.preventDefault();
    });
  });
})();
