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

  // Solo presenta opciones; el servidor comprueba nuevamente el turno al guardar.
  const appointmentForm = document.querySelector('[data-availability-url]');
  if (appointmentForm) {
    const message = appointmentForm.querySelector('[data-availability-message]');
    const slots = appointmentForm.querySelector('[data-availability-slots]');
    const time = appointmentForm.querySelector('[data-appointment-time]');
    let requestNumber = 0;

    function showAvailability() {
      const barber = appointmentForm.querySelector('[name="barber_id"]').value;
      const date = appointmentForm.querySelector('[name="date"]').value;
      const services = Array.from(appointmentForm.querySelectorAll('[data-service-check]:checked'));
      const current = ++requestNumber;
      slots.replaceChildren();

      if (!barber || !date || !services.length) {
        message.textContent = 'Seleccioná fecha, peluquero y servicios para consultar.';
        return;
      }

      message.textContent = 'Consultando horarios…';
      const url = new URL(appointmentForm.dataset.availabilityUrl, window.location.href);
      url.searchParams.set('barber_id', barber);
      url.searchParams.set('date', date);
      if (appointmentForm.dataset.appointmentId) {
        url.searchParams.set('appointment_id', appointmentForm.dataset.appointmentId);
      }
      services.forEach(function (service) {
        url.searchParams.append('service_ids[]', service.value);
      });

      fetch(url.toString(), { credentials: 'same-origin' })
        .then(function (response) {
          if (!response.ok) throw new Error('No se pudo consultar la disponibilidad.');
          return response.json();
        })
        .then(function (result) {
          if (current !== requestNumber) return;
          message.textContent = result.error || (result.slots.length
            ? 'Elegí un horario libre (' + result.minutes + ' min de atención).'
            : 'No hay horarios libres para estos servicios.');
          result.slots.forEach(function (hour) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn';
            button.textContent = hour;
            button.addEventListener('click', function () {
              time.value = hour;
              time.focus();
              slots.querySelectorAll('button').forEach(function (option) {
                option.classList.toggle('primary', option === button);
              });
            });
            slots.appendChild(button);
          });
        })
        .catch(function (error) {
          if (current === requestNumber) message.textContent = error.message;
        });
    }

    appointmentForm.querySelectorAll('[data-availability-input]').forEach(function (input) {
      input.addEventListener('change', showAvailability);
    });
    showAvailability();
  }

  document.querySelectorAll('[data-confirm]').forEach(function (button) {
    button.addEventListener('click', function (event) {
      if (!confirm(button.dataset.confirm || '¿Confirmar acción?')) event.preventDefault();
    });
  });
})();
