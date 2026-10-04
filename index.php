<?php
/** Barber House - Front controller for CodeIgniter 3.1.13 */
// Apache/PHP y MySQL deben usar el mismo día local para agenda y contabilidad.
date_default_timezone_set('America/Argentina/Buenos_Aires');
define('ENVIRONMENT', isset($_SERVER['CI_ENV']) ? $_SERVER['CI_ENV'] : 'development');
if (ENVIRONMENT === 'development') { error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT); ini_set('display_errors', 1); }
else { error_reporting(0); ini_set('display_errors', 0); }
$system_path = 'system';
$application_folder = 'application';
$view_folder = '';
if (defined('STDIN')) { chdir(dirname(__FILE__)); }
if (($temp = realpath($system_path)) !== FALSE) { $system_path = $temp.DIRECTORY_SEPARATOR; }
else { $system_path = strtr(rtrim($system_path, '/\\'), '/\\', DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; }
if (!is_dir($system_path)) { http_response_code(503); exit('Falta la carpeta system/ de CodeIgniter 3.'); }
define('SELF', pathinfo(__FILE__, PATHINFO_BASENAME));
define('BASEPATH', $system_path);
define('FCPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
define('SYSDIR', basename(BASEPATH));
if (is_dir($application_folder)) { if (($temp=realpath($application_folder))!==FALSE) $application_folder=$temp; else $application_folder=strtr(rtrim($application_folder,'/\\'),'/\\',DIRECTORY_SEPARATOR); }
elseif (is_dir(BASEPATH.$application_folder.DIRECTORY_SEPARATOR)) { $application_folder=BASEPATH.strtr(trim($application_folder,'/\\'),'/\\',DIRECTORY_SEPARATOR); }
else { header('HTTP/1.1 503 Service Unavailable.', TRUE, 503); exit('La carpeta application no está configurada correctamente.'); }
define('APPPATH', $application_folder.DIRECTORY_SEPARATOR);
if (!isset($view_folder[0]) && is_dir(APPPATH.'views'.DIRECTORY_SEPARATOR)) $view_folder=APPPATH.'views';
if (is_dir($view_folder)) { if (($temp=realpath($view_folder))!==FALSE) $view_folder=$temp.DIRECTORY_SEPARATOR; else $view_folder=strtr(rtrim($view_folder,'/\\'),'/\\',DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR; }
define('VIEWPATH', $view_folder);
require_once BASEPATH.'core/CodeIgniter.php';
