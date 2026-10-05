# Decisiones técnicas vigentes - Barber House

Este documento resuelve decisiones técnicas actuales. Si un documento anterior contradice una decisión de esta lista, **esta lista prevalece para la implementación**.

## DT-01 - Framework

**Decisión vigente:** CodeIgniter 3.1.13.

El requisito 69 del Análisis V1 menciona CodeIgniter 4. Esa referencia quedó obsoleta.

- No migrar a CI4.
- Mantener convenciones de CI3.
- Mantener MVC clásico.

## DT-02 - Base de datos

**Decisión vigente:** MySQL/MariaDB con XAMPP.

SQLite fue descartado porque:

- el proyecto ya está construido para MySQL;
- XAMPP ya incluye MariaDB/MySQL;
- el DER y SQL están preparados para relaciones, FK, índices y transacciones;
- cambiar de motor agregaría trabajo sin beneficio académico o funcional.

## DT-03 - Cambios de esquema

Evitar cambios estructurales salvo necesidad real.

`database/barber_house.sql` es la instalación limpia completa.

Nunca ejecutar automáticamente ese SQL sobre datos reales si contiene `DROP DATABASE` / recreación de la base.

Si se necesita una evolución de esquema:

- script incremental;
- actualización del SQL limpio;
- explicación de impacto;
- autorización antes de aplicarlo a la base real.

## DT-04 - Históricos

Conservar snapshots.

### Turnos / servicios

Guardar el precio y duración aplicados en el momento del turno.

Un cambio posterior de servicio no modifica datos históricos.

### Cobros / comisión

Conservar porcentaje y monto de comisión aplicados al momento del cobro.

Un cambio posterior del porcentaje del peluquero no recalcula cobros históricos.

## DT-05 - Ingresos

Los ingresos derivan de cobros confirmados.

No crear una segunda carga manual duplicada del mismo ingreso.

## DT-06 - Concurrencia de cobro/estado

Invariante:

> Si existe un cobro válido asociado a un turno, el turno no puede quedar cancelado, ausente o eliminado de forma incompatible.

Las operaciones involucradas deben conservar esta regla aun con peticiones simultáneas.

## DT-07 - Cambios de disponibilidad del peluquero

Cambiar:

- días;
- horarios;
- servicios disponibles

no puede invalidar silenciosamente turnos futuros activos.

Si existen conflictos, rechazar el cambio completo mediante transacción/rollback y explicar qué impide guardar.

## DT-08 - Paginación

Convención actual: 25 por página.

Usar en listados históricos que pueden crecer.

Conservar filtros en los links de paginación.

La agenda diaria no necesita paginarse.

## DT-09 - Código didáctico

El código debe ser legible por estudiantes.

- Una instrucción por línea.
- Variables descriptivas.
- `if`, `for`, `foreach` visibles.
- Comentarios útiles.
- No introducir patrones empresariales innecesarios.
- SQL solo en Models.
- No mover reglas de negocio importantes a JS.

## DT-10 - Entornos

Development:

- errores visibles;
- logging útil.

Production:

- errores detallados no visibles al usuario;
- logging limitado;
- misma arquitectura.

No introducir Docker ni infraestructura adicional solo por separar entornos.

La clave incluida corresponde al entorno local/académico y no debe reutilizarse como secreto de una instalación pública o de producción real.

## DT-11 - Tests

Las pruebas de integración usan una BD temporal como `barber_house_test`.

La base real `barber_house` no se toca durante integración.

Los servidores PHP de prueba deben cerrarse siempre.

El runner debe usar timeout/watchdog y limpieza ante errores.

## DT-12 - Git

Sin commit ni push automático.

Solo hacerlo cuando el usuario lo pida expresamente.
