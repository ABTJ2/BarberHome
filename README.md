# Barber House V1

Aplicación PHP con CodeIgniter 3.1.13 y MySQL/MariaDB. Incluye el framework en `system/` y funciona localmente con XAMPP, sin Composer ni conexión a Internet.

## Instalación local

1. Colocar la carpeta del proyecto bajo `xampp/htdocs/` (por ejemplo, `C:\xampp\htdocs\BarberHome`).
2. Iniciar Apache y MySQL/MariaDB desde el panel de XAMPP.
3. Desde phpMyAdmin (`http://localhost/phpmyadmin/`), abrir **Importar**, seleccionar `database/barber_house.sql` y ejecutar la importación. El archivo crea la base `barber_house` y carga los datos de demostración; **recrea la base si ya existe**.
4. Si el usuario o la contraseña de MySQL difieren de `root` / contraseña vacía, ajustar `application/config/database.php`.
5. Abrir `http://localhost/BarberHome/` para el ejemplo anterior. En la ubicación actual `xampp/htdocs/programacion3/proyectos/BarberHome`, la URL es `http://localhost/programacion3/proyectos/BarberHome/`.

Usuarios de demostración: `encargado` / `123456` y `recepcion` / `123456`.

Estructura MVC: `application/controllers/` recibe las solicitudes, `application/models/` concentra el acceso a datos y `application/views/` genera las pantallas; `assets/` contiene CSS, JavaScript e imágenes.

Si ya tenías una base instalada, importar **solo** `database/upgrade_crud.sql` una vez para agregar el CRUD sin borrar tus datos.

## Desarrollo y producción

El entorno se define en `index.php`, en la variable `$entorno`:

| Entorno | Errores en pantalla | Log en `application/logs/` |
| --- | --- | --- |
| `development` | Sí, con detalle | Todo |
| `production` | No | Solo errores |

- Para publicar el sistema: cambiar `$entorno = 'development'` por `$entorno = 'production'`. No hay que tocar nada más.
- Con `production` los visitantes ven la pantalla de error genérica de `application/views/errors/html/`, sin rutas ni consultas SQL.
- El runner de pruebas (`php tools\pruebas\ejecutar.php`) levanta sus propios servidores con `CI_ENV=test` y usa la base `barber_house_test`, así que nunca toca la base real.

## Pruebas

```
php tools\pruebas\ejecutar.php          # helpers + integración
php tools\pruebas\ejecutar.php --ayuda  # todas las opciones
```

Más detalle en `tools/pruebas/README.md`.
