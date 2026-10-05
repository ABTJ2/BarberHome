# Requerimientos V1 - Gestión de Turnos y Contabilidad

Fuente: `docs/Lista_de_requerimientos_V1.pdf`.

> **Nota técnica vigente:** el REQ-69 dice CodeIgniter 4 porque pertenece al Análisis V1. Fue reemplazado por la decisión actual de usar **CodeIgniter 3.1.13**. Ver `docs/DECISIONES_TECNICAS.md`.

**Cierre V1:** 71/71 requerimientos completos; 0 parciales, 0 faltantes y 0 errores conocidos. Baseline: 30 pruebas de helpers, 172 verificaciones de integración, 202 verificaciones totales y 0 fallos.

## Requerimientos funcionales

| Nº | Nombre | Detalle |
|---:|---|---|
| 01 | Centralizar los turnos | Concentrar en una única agenda los turnos registrados en el local y evitar depender de anotaciones separadas. |
| 02 | Consultar agenda del día | Consultar rápidamente todos los turnos del día seleccionado. |
| 03 | Visualizar horario del turno | Mostrar claramente la hora asignada a cada turno. |
| 04 | Visualizar peluquero asignado | Indicar qué peluquero está asignado a cada turno. |
| 05 | Consultar espacios disponibles | Identificar horarios disponibles para ofrecer nuevos turnos. |
| 06 | Registrar cliente | Registrar como mínimo nombre, apellido y teléfono. |
| 07 | Guardar observaciones del cliente | Guardar una observación breve con preferencias o aclaraciones. |
| 08 | Buscar cliente por nombre | Localizar por nombre o apellido. |
| 09 | Buscar cliente por teléfono | Localizar por número de teléfono. |
| 10 | Reutilizar cliente registrado | Seleccionar un cliente existente al crear un turno sin volver a cargar sus datos. |
| 11 | Consultar historial del cliente | Consultar turnos anteriores con fecha, peluquero y servicios. |
| 12 | Registrar peluquero | Registrar peluqueros con datos necesarios para identificarlos. |
| 13 | Guardar teléfono del peluquero | Almacenar número de teléfono. |
| 14 | Registrar días de trabajo | Indicar qué días trabaja cada peluquero. |
| 15 | Registrar horarios de trabajo | Indicar horarios de trabajo de cada peluquero. |
| 16 | Gestionar estado del peluquero | Indicar activo/inactivo y evitar nuevos turnos cuando no corresponda. |
| 17 | Asignar peluquero al turno | Seleccionar quién atenderá al cliente. |
| 18 | Registrar servicios por peluquero | Indicar qué servicios puede realizar cada peluquero. |
| 19 | Consultar disponibilidad del peluquero | Consultar disponibilidad según días, horarios y turnos registrados. |
| 20 | Registrar porcentaje del peluquero | Registrar el porcentaje que corresponde al peluquero. |
| 21 | Reasignar turnos de un peluquero | Cambiar a otro peluquero disponible o modificar fecha/hora cuando no pueda atender. |
| 22 | Registrar servicio | Registrar servicios ofrecidos. |
| 23 | Guardar nombre del servicio | Cada servicio debe tener nombre identificable. |
| 24 | Guardar precio del servicio | Registrar precio de cada servicio. |
| 25 | Guardar duración del servicio | Registrar duración aproximada. |
| 26 | Actualizar precio del servicio | El Encargado puede modificar precios cuando cambien los valores del local. |
| 27 | Consultar lista interna de servicios | Disponer de una lista de servicios utilizable al registrar turnos. |
| 28 | Asignar varios servicios | Asociar más de un servicio a un turno. |
| 29 | Calcular duración estimada del turno | Calcular tiempo de atención a partir de las duraciones de los servicios. |
| 30 | Crear turno | Registrar cliente, peluquero, servicio(s), fecha y hora. |
| 31 | Agregar observación al turno | Guardar una nota breve asociada al turno. |
| 32 | Validar horario de trabajo | Verificar días y horarios del peluquero antes de registrar. |
| 33 | Evitar superposición de turnos | Impedir que un mismo peluquero tenga turnos superpuestos. |
| 34 | Modificar fecha del turno | Cambiar fecha sin eliminar y volver a crear. |
| 35 | Modificar hora del turno | Cambiar hora según disponibilidad. |
| 36 | Cambiar peluquero del turno | Modificar el peluquero asignado. |
| 37 | Registrar estado del turno | Identificar el estado de cada turno. |
| 38 | Marcar turno reservado | Identificar como reservado mientras espera atención. |
| 39 | Marcar turno atendido | Indicar que el cliente fue atendido. |
| 40 | Marcar turno cancelado | Indicar que el turno fue cancelado. |
| 41 | Marcar cliente ausente | Indicar que el cliente no se presentó. |
| 42 | Registrar atención sin turno previo | Registrar una atención de llegada espontánea si existe disponibilidad. |
| 43 | Registrar cobro de la atención | Registrar el cobro del trabajo realizado. |
| 44 | Guardar importe real cobrado | Registrar el importe realmente cobrado aunque difiera del precio de lista. |
| 45 | Registrar forma de pago | Indicar la forma de pago, incluyendo al menos efectivo y transferencia. El relevamiento también menciona tarjeta. |
| 46 | Gestionar formas de pago | Permitir incorporar nuevas formas de pago. |
| 47 | Relacionar cobro con la atención | Relacionar cobro con cliente, turno, servicios y peluquero. |
| 48 | Registrar ingreso | Registrar como ingreso el dinero cobrado por servicios efectivamente realizados. |
| 49 | Calcular producción del peluquero | Calcular producción por servicios realizados en un período. |
| 50 | Calcular pago del peluquero | Calcular pago aplicando porcentaje sobre servicios realizados. |
| 51 | Consultar liquidación por período | Consultar producción e importe por peluquero en un período. |
| 52 | Registrar egreso | Registrar gastos del local. |
| 53 | Guardar fecha del egreso | Registrar fecha del gasto. |
| 54 | Guardar concepto del egreso | Registrar motivo/concepto. |
| 55 | Guardar monto del egreso | Registrar importe. |
| 56 | Consultar total de ingresos | Consultar total del período. |
| 57 | Consultar total de egresos | Consultar total del período. |
| 58 | Consultar total a pagar a peluqueros | Mostrar total correspondiente a peluqueros en el período. |
| 59 | Calcular resultado del local | Mostrar lo que queda luego de ingresos, egresos y pagos a peluqueros. |
| 60 | Filtrar información por fechas | Consultar información económica por rango de fechas. |
| 61 | Perfil de recepcionista | Trabajar con clientes, agenda, turnos y cobros cotidianos. |
| 62 | Perfil de encargado | Administrar peluqueros, servicios, precios, porcentajes, egresos y consultas económicas. |
| 63 | Restringir funciones por perfil | Limitar funciones según perfil para evitar cambios no autorizados. |

## Requerimientos no funcionales

| Nº | Nombre | Detalle |
|---:|---|---|
| 64 | Acceso mediante navegador | Utilizar el sistema mediante navegador desde la computadora del local. |
| 65 | Funcionamiento local | Ejecutar localmente dentro del establecimiento. |
| 66 | Funcionamiento sin Internet | Mantener disponibles funciones principales sin conexión a Internet. |
| 67 | Interfaz clara para uso diario | Presentar de forma clara y rápida agenda, clientes, turnos y operaciones habituales. |
| 68 | Arquitectura MVC | Respetar Modelo - Vista - Controlador. |
| 69 | Framework CodeIgniter 4 | **REQUERIMIENTO HISTÓRICO REEMPLAZADO.** Implementación vigente: CodeIgniter 3.1.13. |
| 70 | Persistencia en MySQL | Almacenar información en MySQL/MariaDB. |
| 71 | Ejecución con XAMPP | Utilizar Apache y MySQL/MariaDB mediante XAMPP. |

## Detalles del relevamiento que complementan la lista

Estos puntos están respaldados por el relevamiento y deben considerarse al interpretar los requerimientos:

- Acceso con usuario y contraseña.
- Forma de pago esperada: efectivo, transferencia o tarjeta.
- Egresos con categoría simple (servicios, limpieza, mantenimiento u otros).
- El Encargado puede corregir movimientos; el usuario operativo no.
- El cobro debe generar el ingreso automáticamente para evitar doble carga.

## Fuera de alcance

No son requerimientos de V1:

- reservas públicas por Internet;
- pagos online;
- stock;
- gestión de productos para venta;
- proveedores.
