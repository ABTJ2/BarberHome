# Barber House — contexto permanente

## Versión estable V1

- 71/71 requerimientos completos; 0 parciales, 0 faltantes y 0 errores conocidos.
- Baseline del cierre: 30 pruebas de helpers + 172 verificaciones de integración = 202 verificaciones, 0 fallos.
- `php -l` y `node --check` limpios; SQL de la aplicación solamente en Models.
- Sin cambios estructurales pendientes en la base de datos.

Estos números describen el cierre de V1: después de cualquier cambio se debe comprobar el estado real, no asumir que continúan vigentes.

## Stack y fuentes

- PHP, CodeIgniter **3.1.13**, MySQL/MariaDB, Apache y XAMPP; MVC clásico y uso local sin Internet.
- Antes de cambiar comportamiento, consultar `docs/REQUERIMIENTOS_V1.md` y `docs/DECISIONES_TECNICAS.md`. Para dudas de alcance, consultar `docs/CONTEXTO_PROYECTO.md` y la documentación original de `docs/`.
- El REQ-69 menciona CodeIgniter 4 por ser histórico; la decisión vigente es **CodeIgniter 3.1.13**. No migrar a CodeIgniter 4 ni cambiar a SQLite.
- No agregar funcionalidades fuera del alcance de V1 sin pedido explícito.

## Implementación

- Mantener código académico simple y legible: nombres descriptivos, flujo secuencial, una instrucción por línea y comentarios breves para reglas no obvias.
- Controllers: sesión, roles, validación, coordinación de modelos y respuestas. No escribir SQL en controladores.
- Models: consultas, persistencia, transacciones, bloqueos y reglas relacionadas con datos.
- Views: presentación y formularios; sin consultas SQL ni reglas críticas delegadas solo a JavaScript. Escapar datos de usuario.
- Verificar permisos en el backend: Recepcionista opera clientes, turnos y cobros cotidianos; Encargado administra además peluqueros, servicios, usuarios, egresos y contabilidad.
- Conservar snapshots de precio, duración y comisión; un turno cobrado no puede quedar cancelado ni ausente. Evitar superposiciones y respetar horarios del peluquero, incluso ante operaciones concurrentes.
- Paginar listados largos de a 25 sin perder filtros; no paginar la agenda diaria.
- Mantener la interfaz existente salvo error real.

## Datos, pruebas y Git

- No modificar la estructura de la base innecesariamente. `database/barber_house.sql` recrea la base: nunca importarlo automáticamente sobre `barber_house` durante pruebas o mantenimiento.
- Las pruebas usan `barber_house_test`, cierran sus servidores y eliminan la base temporal. Ejecutar lint y pruebas pertinentes; comprobar que no queden procesos, sesiones o logs de prueba.
- No descartar trabajo ajeno ni hacer commit, push o tag sin pedido explícito.
