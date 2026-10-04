# Barber House V1

1. Copiar esta carpeta a `C:\xampp\htdocs\BARBER-HOUSE`.
2. Iniciar Apache y MySQL desde XAMPP.
3. Importar `database/barber_house.sql` desde phpMyAdmin (recrea la base `barber_house`).
4. Si MySQL tiene otra contraseña, actualizar `application/config/database.php`.
5. Abrir `http://localhost/BARBER-HOUSE/`.

Usuarios de prueba: `encargado` / `123456` y `recepcion` / `123456`.
El proyecto incluye CodeIgniter 3 y funciona sin conexión a Internet.

Si ya tenías una base instalada, importar **solo** `database/upgrade_crud.sql` una vez para agregar el CRUD sin borrar tus datos.
