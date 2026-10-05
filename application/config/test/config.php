<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/*
 | Ajustes de configuración que se aplican SOLO en las pruebas automáticas
 | (CI_ENV=test, ver tools/pruebas/router.php).
 |
 | Este archivo se suma a application/config/config.php: se cambian únicamente
 | los valores que se quieren modificar para las pruebas.
 |
 | La idea es que las pruebas no dejen archivos dentro del proyecto: los logs
 | y los archivos de sesión se escriben en la carpeta temporal del sistema.
*/

$temporal = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'barberhouse_pruebas' . DIRECTORY_SEPARATOR;

$config['log_path'] = $temporal;
$config['sess_save_path'] = $temporal;
