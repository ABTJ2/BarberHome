<?php
/**
 * Pruebas de las funciones de application/helpers/app_helper.php.
 *
 * Son funciones puras: no necesitan base de datos ni servidor web, así que se
 * prueban directamente, sin pasar por la aplicación.
 *
 * Al incluirlo fuera de CodeIgniter hay que definir las dos funciones del
 * framework que usa (html_escape y base_url) y la constante BASEPATH.
 */

require_once __DIR__ . '/pruebas.php';

/**
 * Prepara el entorno mínimo para poder usar el helper fuera de CodeIgniter.
 */
function cargar_app_helper()
{
    if (!defined('BASEPATH')) {
        define('BASEPATH', dirname(__DIR__, 2) . '/system/');
    }

    if (!defined('FCPATH')) {
        define('FCPATH', dirname(__DIR__, 2) . '/');
    }

    if (!function_exists('html_escape')) {
        /**
         * Copia de system/core/Common.php para poder probar h().
         */
        function html_escape($valor, $doble = TRUE)
        {
            if (empty($valor)) {
                return $valor;
            }

            return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8', $doble);
        }
    }

    if (!function_exists('base_url')) {
        function base_url($ruta = '')
        {
            return 'http://localhost/' . $ruta;
        }
    }

    require_once dirname(__DIR__, 2) . '/application/helpers/app_helper.php';
}

/**
 * Valida las fechas.
 */
function probar_validacion_de_fechas(Pruebas $pruebas)
{
    $pruebas->seccion('Helper: validación de fechas');

    $pruebas->verificar(valid_day('2026-03-14'), 'Una fecha real se acepta (2026-03-14)');
    $pruebas->verificar(!valid_day('2026-02-31'), 'El 31 de febrero se rechaza');
    $pruebas->verificar(!valid_day('2026-13-01'), 'El mes 13 se rechaza');
    $pruebas->verificar(!valid_day('14/03/2026'), 'Una fecha con barras se rechaza');
    $pruebas->verificar(!valid_day(array()), 'Un array no es una fecha');
}

/**
 * Valida las horas.
 */
function probar_validacion_de_horas(Pruebas $pruebas)
{
    $pruebas->seccion('Helper: validación de horas');

    $pruebas->verificar(valid_clock('09:00'), 'Las 09:00 son válidas');
    $pruebas->verificar(valid_clock('23:59'), 'Las 23:59 son válidas');
    $pruebas->verificar(!valid_clock('24:00'), 'Las 24:00 se rechazan');
    $pruebas->verificar(!valid_clock('9:00'), 'Una hora sin el cero inicial se rechaza');
    $pruebas->verificar(!valid_clock('09:60'), 'Los minutos 60 se rechazan');
}

/**
 * Valida los importes.
 */
function probar_validacion_de_importes(Pruebas $pruebas)
{
    $pruebas->seccion('Helper: validación de importes');

    $pruebas->verificar(valid_money('12000'), 'Un importe entero se acepta');
    $pruebas->verificar(valid_money('12000.55'), 'Un importe con centavos se acepta');
    $pruebas->verificar(valid_money('0'), 'El cero se acepta si no se pide que sea mayor a cero');
    $pruebas->verificar(!valid_money('0', TRUE), 'El cero se rechaza si tiene que ser mayor a cero');
    $pruebas->verificar(!valid_money('12000,55'), 'La coma decimal se rechaza (se espera punto)');
    $pruebas->verificar(!valid_money('1.234'), 'El punto de miles se rechaza');
    $pruebas->verificar(!valid_money('100.999'), 'Tres decimales se rechazan');
    $pruebas->verificar(!valid_money('12000.5e3'), 'Notación científica se rechaza');
}

/**
 * Valida el porcentaje de comisión.
 */
function probar_validacion_de_porcentajes(Pruebas $pruebas)
{
    $pruebas->seccion('Helper: validación de porcentajes');

    $pruebas->verificar(valid_percent('45'), 'El 45% se acepta');
    $pruebas->verificar(valid_percent('100'), 'El 100% se acepta');
    $pruebas->verificar(valid_percent('45.50'), 'El 45,50% se acepta');
    $pruebas->verificar(!valid_percent('100.01'), 'Un porcentaje mayor a 100 se rechaza');
    $pruebas->verificar(!valid_percent('101'), 'El 101% se rechaza');
}

/**
 * Verifica los textos que se muestran en pantalla.
 */
function probar_textos_de_pantalla(Pruebas $pruebas)
{
    $pruebas->seccion('Helper: textos de pantalla');

    $pruebas->igual('$12.345,50', money(12345.5), 'money() usa el formato argentino');
    $pruebas->igual('Miércoles', day_name(3), 'day_name() muestra el nombre del día');
    $pruebas->igual('', day_name(9), 'day_name() ignora un número de día inválido');
    $pruebas->igual('Ausente', status_label('no_show'), 'status_label() traduce el estado');
    $pruebas->igual('red', status_class('cancelled'), 'status_class() devuelve el color del estado');
    $pruebas->igual('Encargado', role_label('encargado'), 'role_label() muestra el nombre del perfil');
    $pruebas->igual(
        '&lt;b&gt;x&lt;/b&gt;',
        h('<b>x</b>'),
        'h() escapa el HTML para que no se ejecute'
    );
}

/**
 * Corre todas las pruebas del helper.
 *
 * @return bool TRUE si no hubo fallos.
 */
function ejecutar_pruebas_helpers(Pruebas $pruebas)
{
    echo "Pruebas de app_helper.php\n";

    cargar_app_helper();

    probar_validacion_de_fechas($pruebas);
    probar_validacion_de_horas($pruebas);
    probar_validacion_de_importes($pruebas);
    probar_validacion_de_porcentajes($pruebas);
    probar_textos_de_pantalla($pruebas);

    return $pruebas->resumen('Pruebas del helper');
}
