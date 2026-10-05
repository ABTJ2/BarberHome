# Matriz de trazabilidad - Barber House

Objetivo: ayudar a agentes y desarrolladores a ubicar rápidamente dónde debe vivir cada requerimiento.

Esta matriz es una guía de navegación, no reemplaza la auditoría del código real. Estado de cierre V1: 71/71 requerimientos completos; 0 parciales, 0 faltantes y 0 errores conocidos.

| Requerimientos | Área | Componentes principales esperados |
|---|---|---|
| REQ-01 a REQ-05 | Agenda | `Appointments.php`, `Appointment_model.php`, vistas de appointments |
| REQ-06 a REQ-11 | Clientes | `Clients.php`, `Client_model.php`, vistas de clients |
| REQ-12 a REQ-21 | Peluqueros | `Barbers.php`, `Barber_model.php`, vistas de barbers |
| REQ-22 a REQ-29 | Servicios | `Services.php`, `Service_model.php`, relación appointment_services / barber_services |
| REQ-30 a REQ-42 | Turnos | `Appointments.php`, `Appointment_model.php`, vistas de appointments |
| REQ-43 a REQ-48 | Cobros / ingresos | `Payments.php`, `Payment_model.php`, vistas de payments |
| REQ-49 a REQ-60 | Contabilidad | `Accounting.php`, `Payment_model.php`, `Expense_model.php`, vistas de accounting |
| REQ-61 a REQ-63 | Roles/permisos | `Auth.php`, `Users.php`, `MY_Controller.php`, `User_model.php`, navegación |
| REQ-64 a REQ-71 | No funcionales | configuración CI3, XAMPP, MySQL, MVC, interfaz |

## Cómo mantenerla

Cuando se implemente o cambie una regla relevante:

1. leer la definición exacta en `docs/REQUERIMIENTOS_V1.md`;
2. identificar Controller + Model + View + test afectado;
3. mantener la regla en la capa correcta;
4. agregar/actualizar prueba cuando sea una regla de negocio;
5. si el requerimiento queda implementado de forma distinta, actualizar esta matriz con archivos concretos.

## Convención de comentarios

Para reglas críticas se permite:

```php
// REQ-33: Evitar superposición de turnos.
```

No es necesario etiquetar CRUD obvios ni llenar el código de comentarios.
