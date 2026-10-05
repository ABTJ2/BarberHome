<?php
/**
 * Router del servidor de pruebas.
 *
 * Se le pasa a PHP así:
 *
 *     php -S 127.0.0.1:8765 tools/pruebas/router.php
 *
 * El servidor embebido de PHP llama a este archivo por cada petición. Aquí se
 * resuelve lo siguiente:
 *
 *   1. Los archivos que existen de verdad (css, js, imágenes) los sirve el
 *      servidor sin pasar por CodeIgniter.
 *   2. El resto se entrega a index.php, que es el punto de entrada de la
 *      aplicación.
 *   3. Se indica CI_ENV=test para que CodeIgniter use la configuración de
 *      application/config/test/, que apunta a la base barber_house_test.
 *      La base barber_house nunca se usa en las pruebas.
 */

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server') {
    exit('Este archivo solo se usa como router del servidor de pruebas.');
}

$raiz = rtrim($_SERVER['DOCUMENT_ROOT'], '/\\');
$ruta = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$archivo = $raiz . $ruta;

// 1. Archivos reales: los devuelve el propio servidor.
if ($ruta !== '/' && is_file($archivo)) {
    return FALSE;
}

// 2. Todo lo demás pasa por el front controller de CodeIgniter.
$_SERVER['CI_ENV'] = 'test';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

require $raiz . '/index.php';
