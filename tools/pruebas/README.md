# Pruebas automáticas de BarberHome

Estas pruebas comprueban el sistema desde afuera, manejando la aplicación como
lo haría una persona: entran por formulario, cobran un turno, lo anulan, dan de
baja un cliente, etc. Después verifican qué quedó guardado en la base y qué se
muestra en pantalla.

No hace falta PHPUnit, Composer ni ningún otro framework: alcanza con el PHP de
XAMPP.

## Cómo se corren

Desde la carpeta del proyecto (`C:\xampp\htdocs\programacion3\proyectos\BarberHome`):

```
php tools/pruebas/ejecutar.php
```

Eso corre las dos tandas y al final dice cuántas verificaciones pasaron.

Opciones:

| Opción | Qué hace |
|---|---|
| `--solo=arranque` | Prueba rápida: levanta un servidor, hace una petición, lo apaga y comprueba que no queda vivo. No usa la base de datos. |
| `--solo=helpers` | Corre solamente las pruebas de `app_helper.php`. No necesita base de datos. |
| `--solo=integracion` | Corre solamente las pruebas de la aplicación. |
| `--mantener` | Deja creada la base `barber_house_test` al terminar, para poder mirarla con phpMyAdmin. |
| `--ayuda` | Muestra la lista de opciones. |

El script devuelve `0` si todo pasó y `1` si algo falló, así que también sirve
para comprobar un cambio sin tener que leer toda la salida.

### Si algo queda colgado

Las pruebas tienen un límite de tiempo de 10 minutos (`LIMITE_SEGUNDOS`, en
`pruebas.php`): si se pasa, se cortan. Los servidores que se levantan se apagan
siempre, aunque el script termine con un error.

Para ver si quedó algún servidor vivo:

```
tasklist /FI "IMAGENAME eq php.exe" /FI "WINDOWTITLE eq *router.php*"
```

Si apareciera alguno (solo debería pasar si se cierra la consola a la fuerza),
se termina con:

```
taskkill /F /T /PID <numero>
```

### Requisitos

- MySQL (o MariaDB) encendido. No hace falta Apache: las pruebas levantan su
  propio servidor PHP.
- Las credenciales son las de `application/config/database.php`. No hay que
  escribir nada en ningún archivo para correr las pruebas.

## Qué hay en cada archivo

| Archivo | Para qué sirve |
|---|---|
| `ejecutar.php` | Punto de entrada: lee las opciones y corre las pruebas. |
| `helpers.php` | Pruebas de las funciones de `application/helpers/app_helper.php`. |
| `integracion.php` | Pruebas de la aplicación (pantallas, permisos, turnos, cobros, etc.). |
| `pruebas.php` | Herramientas compartidas: verificaciones, conexión a la base, cliente HTTP y servidor. |
| `router.php` | Router del servidor de pruebas: sirve los archivos estáticos y pasa el resto a `index.php`. |

## La base de datos: `barber_house` no se toca nunca

Las pruebas trabajan siempre sobre una base aparte, `barber_house_test`:

1. Leen `database/barber_house.sql`, le cambian el nombre de la base por
   `barber_house_test` y además anulan la línea `DROP DATABASE`.
2. Antes de ejecutarlo, comprueban que en ese SQL no quede ninguna sentencia
   (`DROP`, `CREATE` o `USE`) sobre `barber_house`.
3. Si el nombre de la base de la aplicación fuera `barber_house_test`, las
   pruebas se abortan.
4. Al terminar borran `barber_house_test` (salvo que se use `--mantener`).

La aplicación se sirve con `CI_ENV=test`, que hace que CodeIgniter use
`application/config/test/`:

- `database.php` incluye los datos de conexión del archivo normal y cambia
  solamente el nombre de la base. Las credenciales quedan escritas en un solo
  lugar.
- `config.php` manda los archivos de log a la carpeta temporal del sistema, para
  que las pruebas no dejen archivos dentro del proyecto.

## Agregar una verificación nueva

En `integracion.php`, se agrega dentro de la función de la sección que corresponda
(el caso normal es `probar_recorrido_completo`):

```php
$e->pruebas->igual(
    'attended',
    $e->db->valor('SELECT status FROM appointments WHERE id = 2'),
    'El turno 2 tiene que quedar atendido'
);
```

Métodos disponibles en `$e->pruebas`:

| Método | Para qué sirve |
|---|---|
| `verificar($condicion, $descripcion, $detalle)` | Comprueba una condición. |
| `igual($esperado, $obtenido, $descripcion)` | Compara dos valores. |
| `contiene($texto, $html, $descripcion)` | Busca un texto dentro de la respuesta. |
| `no_contiene($texto, $html, $descripcion)` | Comprueba que un texto NO aparezca. |

Y para llamar a la aplicación, `$e->encargado` es una sesión de navegador
(siempre como Encargado), `$e->recepcion` es la del Recepcionista:

```php
$r = $e->encargado->post('/clientes/guardar', array(
    'bh_csrf' => $e->encargado->csrf('/clientes/nuevo'),
    'first_name' => 'Ana',
    'last_name' => 'Nuevo',
    'phone' => '264 555 0000',
));
```
