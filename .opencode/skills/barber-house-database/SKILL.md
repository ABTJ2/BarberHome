---
name: barber-house-database
description: Usar cuando una tarea de Barber House pueda requerir cambios de MySQL, tablas, índices, relaciones o scripts SQL. Prioriza no cambiar el esquema y proteger la base real e históricos.
---

# Política de base de datos de Barber House V1

Motor: MySQL/MariaDB de XAMPP.

## Antes de cambiar esquema

1. Leer `AGENTS.md`.
2. Leer `docs/DECISIONES_TECNICAS.md`.
3. Revisar `database/barber_house.sql`.
4. Revisar `docs/ESQUEMA_BD.md`.
5. Confirmar si el requisito puede resolverse con la estructura actual.

**Preferencia: no modificar el esquema.**

## Base real

Nunca ejecutar automáticamente el SQL de instalación limpia sobre `barber_house`.

Ese archivo puede recrear/eliminar la base.

Para pruebas usar `barber_house_test` u otra base temporal.

## Si el cambio es inevitable

- explicar el motivo;
- preservar históricos;
- usar `DECIMAL` para dinero;
- usar FK/índices apropiados;
- crear script incremental;
- actualizar el SQL de instalación limpia;
- no aplicar a la base real sin aprobación.

## Históricos obligatorios

No romper:

- snapshots de precio;
- snapshots de duración;
- porcentaje de comisión histórico;
- monto de comisión histórico.

## Cierre

Indicar explícitamente si:

- no hubo cambio de base de datos; o
- qué script debe ejecutarse y por qué.
