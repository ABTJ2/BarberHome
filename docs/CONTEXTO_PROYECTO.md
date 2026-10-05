# Contexto del proyecto - Barber House

## Proyecto académico

**Gestión de Turnos y Contabilidad** es un proyecto de Práctica Profesionalizante 3 del ISFT Normal Superior Caucete.

- Curso: 2° 1ª
- Año: 2026
- Integrantes: Carlos Balmaceda, Jesús Funes y Nahuel Vera
- Nombre de interfaz: Barber House

## Trazabilidad del proceso

El proyecto sigue esta secuencia:

**Presentación → Relevamiento → Análisis → Diseño/Maquetado → DER → Implementación → Testing**

Barber House V1 completó la implementación y el cierre técnico: 71/71 requerimientos y 202 verificaciones sin fallos.

La implementación debe conservar trazabilidad con las etapas anteriores. No agregar funciones que no surjan del relevamiento/requerimientos salvo que sean una decisión técnica necesaria para cumplirlos.

## Problema detectado en el relevamiento

La peluquería organizaba turnos en cuaderno y WhatsApp. Esto generaba:

- horarios superpuestos;
- dificultad para saber disponibilidad;
- dificultad para encontrar reservas;
- repetición de datos de clientes;
- poca claridad sobre ingresos y egresos.

Las tres prioridades expresadas fueron:

1. ver los turnos del día sin confusiones;
2. encontrar o cargar un cliente rápidamente;
3. saber cuánto dinero entró y cuánto salió.

## Necesidades principales del negocio

### Clientes

- Guardar nombre, apellido y celular.
- Observación opcional.
- Buscar por nombre/apellido o teléfono.
- Reutilizar cliente registrado.
- Consultar historial.

### Agenda y turnos

- Agenda única.
- Ver día, hora y peluquero.
- Consultar disponibilidad.
- Registrar turnos.
- Registrar atención sin turno previo.
- Editar fecha/hora/peluquero.
- Estados simples: reservado/pendiente, atendido, cancelado, ausente.
- Evitar superposición real por intervalo.
- Respetar disponibilidad del peluquero.
- Nota corta de turno.

### Peluqueros

- Identificación y teléfono.
- Días y horarios de trabajo.
- Activo/inactivo.
- Servicios que puede realizar.
- Porcentaje de comisión.
- Reasignación de turnos.

### Servicios

- Nombre.
- Precio.
- Duración aproximada.
- Posibilidad de varios servicios en un turno.
- Precio actualizado para turnos futuros sin alterar históricos.

### Cobros

El relevamiento indica:

- el ingreso se registra cuando se atiende y cobra;
- registrar forma de pago;
- efectivo, transferencia y tarjeta son formas esperadas;
- el importe real puede diferir del precio teórico;
- no duplicar el ingreso manualmente.

### Egresos y contabilidad

El relevamiento agrega detalles que no siempre aparecen como requerimiento numerado independiente:

- egreso con fecha, concepto, monto y categoría simple;
- categorías ejemplo: servicios, limpieza, mantenimiento, otros;
- resumen de ingresos, egresos y diferencia;
- detalle de movimientos;
- filtro por rango de fechas;
- el Encargado puede corregir movimientos;
- el usuario operativo común no debe poder realizar correcciones administrativas.

### Acceso

El relevamiento exige acceso mediante usuario y contraseña, aunque esta frase no aparece como un requerimiento numerado independiente en la lista formal.

## Roles

### Recepcionista

Operación cotidiana:

- clientes;
- agenda;
- turnos;
- cobros.

### Encargado

Operación + administración:

- peluqueros;
- servicios;
- precios;
- porcentajes;
- egresos;
- reportes/contabilidad;
- usuarios.

## Funcionamiento esperado

- Aplicación web local.
- Uso mediante navegador.
- XAMPP.
- MySQL/MariaDB.
- Debe seguir funcionando sin Internet.
- Interfaz rápida y clara.

## Fuera de alcance de V1

- reservas públicas online;
- pagos online;
- stock;
- venta de productos;
- proveedores.

No ampliar alcance por iniciativa del agente.

## Fuentes del repositorio

Las fuentes originales deberían permanecer en `docs/`:

- `Relevamiento.pdf`
- `Lista_de_requerimientos_V1.pdf`
- `Plan_de_trabajo_V2.pdf`
- `DER_Barber_House.png`
- `ESQUEMA_BD.md`

Este archivo resume contexto, pero no reemplaza los documentos originales.
