---
name: barber-house-feature
description: Usar cuando se deba implementar o corregir una funcionalidad solicitada de Barber House, manteniendo trazabilidad, MVC simple, históricos, roles y pruebas.
---

# Flujo para funcionalidades

1. Leer `AGENTS.md`.
2. Buscar el/los REQ afectados en `docs/REQUERIMIENTOS_V1.md`.
3. Revisar `docs/DECISIONES_TECNICAS.md`.
4. Localizar Controller, Model, View y pruebas existentes.
5. Confirmar si el comportamiento ya existe total o parcialmente.
6. Hacer el cambio mínimo correcto.

## MVC

- Controller: petición, validación, permisos, coordinación.
- Model: persistencia/consultas/reglas ligadas a datos.
- View: presentación.
- No SQL en Controller/View.
- No regla crítica solamente en JavaScript.

## Legibilidad

Preferir código fácil de explicar:

- una instrucción por línea;
- nombres descriptivos;
- `if`, `for`, `foreach`;
- comentarios antes de reglas no obvias;
- funciones manejables.

## Trazabilidad

Para reglas críticas, usar comentario `REQ-XX` cuando ayude.

## Al finalizar

- `php -l`;
- pruebas relevantes;
- integración si cambia una regla de negocio;
- ambos roles si cambia autorización;
- informar archivos modificados y requerimientos cubiertos.
