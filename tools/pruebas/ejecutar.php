<?php
/**
 * Punto de entrada de las pruebas automáticas de BarberHome.
 *
 * Cómo se usa (desde la carpeta del proyecto):
 *
 *     php tools/pruebas/ejecutar.php
 *
 * Opciones:
 *
 *     --solo=helpers        corre solamente las pruebas del helper.
 *     --solo=integracion    corre solamente las pruebas de integración.
 *     --mantener            deja creada la base barber_house_test al terminar.
 *     --ayuda               muestra esta ayuda.
 *
 * El script devuelve 0 si todo pasó y 1 si alguna verificación falló, así que
 * también se puede usar para comprobar un cambio automáticamente.
 */

if (PHP_SAPI !== 'cli') {
    exit('Este archivo solo se ejecuta desde la línea de comandos: php tools/pruebas/ejecutar.php');
}

require_once __DIR__ . '/pruebas.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/integracion.php';

// Aunque el script termine de golpe, no puede quedar ningún servidor de
// pruebas encendido.
register_shutdown_function('apagar_servidores');

/**
 * Muestra el texto de ayuda.
 */
function mostrar_ayuda()
{
    echo "Pruebas de BarberHome\n\n";
    echo "  php tools/pruebas/ejecutar.php                    corre todas\n";
    echo "  php tools/pruebas/ejecutar.php --solo=arranque    prueba corta del servidor\n";
    echo "  php tools/pruebas/ejecutar.php --solo=helpers      solo el helper\n";
    echo "  php tools/pruebas/ejecutar.php --solo=integracion  solo la aplicación\n";
    echo "  php tools/pruebas/ejecutar.php --mantener          deja barber_house_test\n";
    echo "  php tools/pruebas/ejecutar.php --ayuda             esta ayuda\n\n";
    echo "Hace falta tener MySQL (XAMPP) encendido. La base barber_house no se toca.\n";
}

// Se leen las opciones de la línea de comandos.
$opciones = array('solo' => 'todo', 'mantener' => FALSE, 'ayuda' => FALSE);

foreach (array_slice($argv, 1) as $argumento) {
    if ($argumento === '--mantener') {
        $opciones['mantener'] = TRUE;
    } elseif ($argumento === '--ayuda' || $argumento === '-h' || $argumento === '--help') {
        $opciones['ayuda'] = TRUE;
    } elseif (strpos($argumento, '--solo=') === 0) {
        $opciones['solo'] = substr($argumento, 7);
    } else {
        echo "Opción desconocida: $argumento\n\n";
        mostrar_ayuda();
        exit(1);
    }
}

if ($opciones['ayuda']) {
    mostrar_ayuda();
    exit(0);
}

if (!in_array($opciones['solo'], array('todo', 'arranque', 'helpers', 'integracion'), TRUE)) {
    echo "El valor de --solo tiene que ser arranque, helpers o integracion.\n\n";
    mostrar_ayuda();
    exit(1);
}

$todoBien = TRUE;

try {
    if ($opciones['solo'] === 'arranque') {
        // Prueba corta: sirve para comprobar que el runner anda y no deja
        // servidores vivos, sin tocar la base de datos.
        $todoBien = ejecutar_prueba_de_arranque(new Pruebas()) && $todoBien;
    } else {
        if ($opciones['solo'] === 'todo' || $opciones['solo'] === 'helpers') {
            // Las pruebas del helper no necesitan base de datos.
            $todoBien = ejecutar_pruebas_helpers(new Pruebas()) && $todoBien;
        }

        if ($opciones['solo'] === 'todo' || $opciones['solo'] === 'integracion') {
            $todoBien = ejecutar_pruebas_integracion(new Pruebas(), $opciones) && $todoBien;
        }
    }
} catch (Throwable $error) {
    echo "\nNo se pudieron correr las pruebas: " . $error->getMessage() . "\n";
    exit(1);
}

apagar_servidores();
echo "\n";
exit($todoBien ? 0 : 1);
