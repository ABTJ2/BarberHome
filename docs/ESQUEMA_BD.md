# Esquema de base de datos usado por la implementación

- `roles` → perfiles Recepcionista / Encargado.
- `users` → usuarios y contraseñas cifradas.
- `clients` → clientes.
- `barbers` → peluqueros y porcentaje.
- `barber_schedules` → días y horarios de trabajo.
- `services` → catálogo de servicios.
- `barber_services` → servicios que puede realizar cada peluquero.
- `appointments` → turnos y estados.
- `appointment_services` → varios servicios por turno, con precio/duración históricos.
- `payment_methods` → formas de pago ampliables.
- `payments` → cobros e ingreso automático, con porcentaje histórico.
- `expense_categories` → categorías de egresos.
- `expenses` → gastos del local.

No se usa una tabla `ingresos` separada: cada fila de `payments` es el ingreso originado por una atención cobrada, evitando duplicar el mismo movimiento.
