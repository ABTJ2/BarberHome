---
name: barber-house-audit
description: Usar para auditar Barber House contra los 71 requerimientos formales y el relevamiento, clasificando cada requisito antes de proponer cambios.
---

# Auditoría funcional

No modificar código durante la primera fase.

1. Leer `AGENTS.md`.
2. Leer los 71 puntos de `docs/REQUERIMIENTOS_V1.md`.
3. Leer `docs/CONTEXTO_PROYECTO.md`.
4. Inspeccionar Controller + Model + View + tests.
5. No considerar implementado algo solo porque existe una tabla o método.
6. Verificar que sea utilizable desde UI y protegido en backend.

## Resultado

Crear tabla:

| Nº | Requerimiento | Estado | Evidencia | Falta |
|---|---|---|---|---|

Estados:

- COMPLETO
- PARCIAL
- FALTA
- ERROR

Evidencia debe indicar archivos/rutas concretas.

Después:

- agrupar PARCIAL/FALTA/ERROR;
- priorizar;
- proponer el menor conjunto de cambios;
- indicar si alguno requiere BD.

No ampliar alcance.
