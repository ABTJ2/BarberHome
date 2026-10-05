<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 | Base de datos de las pruebas automáticas.
 |
 | CodeIgniter carga application/config/test/database.php (y no el archivo
 | normal) cuando la aplicación se sirve con CI_ENV=test, que es lo que hace
 | tools/pruebas/router.php.
 |
 | Se incluyen primero los datos de conexión del archivo normal para que las
 | credenciales estén escritas en un solo lugar, y después se cambia
 | solamente el nombre de la base: las pruebas trabajan sobre barber_house_test.
 |
 | ATENCIÓN: la base de la aplicación, barber_house, nunca se modifica desde
 | este entorno.
*/

include APPPATH . 'config/database.php';

$db['default']['database'] = 'barber_house_test';
