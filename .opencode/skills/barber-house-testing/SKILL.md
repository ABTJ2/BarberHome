---
name: barber-house-testing
description: Usar para validar cambios de Barber House con lint, helpers e integración, protegiendo la base real y evitando recursos de prueba huérfanos.
---

# Validación

## Base

Las pruebas de integración deben usar una base temporal, nunca `barber_house`.

## Orden recomendado

1. `php -l` sobre PHP cambiado.
2. Pruebas de helper.
3. Pruebas de integración para reglas de negocio.
4. Pruebas de autorización si cambian roles.
5. Revisión de SQL en controladores.

## Runner

Debe:

- usar timeout/watchdog;
- poner timeout de conexión HTTP;
- cerrar stdin;
- dirigir stdout/stderr de servidores a archivos;
- matar el árbol de procesos de `php -S` en Windows;
- ejecutar limpieza en `shutdown`;
- cerrar `mysqli`;
- eliminar base temporal;
- limpiar sesiones/logs de test;
- devolver código 0/1.

Si una prueba se cuelga, no repetir indefinidamente. Diagnosticar PID/recurso abierto.

## Reglas especialmente importantes

- cobro vs. cancelación concurrente;
- superposición;
- disponibilidad del peluquero;
- snapshots;
- roles;
- filtros/paginación;
- cobro único/ingreso;
- contabilidad por rango.

No considerar terminado un cambio porque solo pasa `php -l`.
