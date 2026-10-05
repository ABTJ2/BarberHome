<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Funciones de apoyo para las vistas y los controladores.
 *
 * Aquí viven los cálculos de presentación (formato de dinero, textos de
 * estado, etc.) y las validaciones de formato de fecha, hora y montos.
 */

/**
 * Escapa un texto antes de mostrarlo en el HTML.
 *
 * Todas las vistas usan h() en los datos que vienen de la base o del
 * formulario, para que un nombre con caracteres especiales no rompa la
 * página ni ejecute código.
 */
function h($value)
{
    return html_escape((string) $value);
}

/**
 * Formatea un importe con el estilo argentino: $12.000,50
 */
function money($value)
{
    return '$' . number_format((float) $value, 2, ',', '.');
}

/**
 * Estados posibles de un turno, con su nombre legible.
 *
 * Vive en un solo lugar para que el filtro de la agenda y las etiquetas de las
 * tablas nunca se desincronicen.
 */
function status_options()
{
    return array(
        'reserved' => 'Reservado',
        'attended' => 'Atendido',
        'cancelled'=> 'Cancelado',
        'no_show'  => 'Ausente',
    );
}

/**
 * Nombre legible de cada estado de turno.
 */
function status_label($status)
{
    $labels = status_options();

    return isset($labels[$status]) ? $labels[$status] : ucfirst($status);
}

/**
 * Clase de color del badge según el estado del turno.
 */
function status_class($status)
{
    $classes = array(
        'reserved' => 'green',
        'attended' => 'blue',
        'cancelled'=> 'red',
        'no_show'  => 'gold',
    );

    return isset($classes[$status]) ? $classes[$status] : 'neutral';
}

/**
 * Nombre legible del perfil del usuario.
 */
function role_label($code)
{
    return $code === 'encargado' ? 'Encargado' : 'Recepcionista';
}

/**
 * Nombre del día de la semana.
 *
 * La numeración es la misma que usa date('N') y la tabla barber_schedules:
 * 1 = lunes ... 7 = domingo.
 */
function day_name($n)
{
    $days = array(
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    );

    return isset($days[$n]) ? $days[$n] : '';
}

/**
 * Mensaje de éxito o error de la operación anterior.
 */
function flash_message()
{
    $CI =& get_instance();

    if ($message = $CI->session->flashdata('success')) {
        return '<div class="alert success">' . h($message) . '</div>';
    }

    if ($message = $CI->session->flashdata('error')) {
        return '<div class="alert error">' . h($message) . '</div>';
    }

    return '';
}

/**
 * URL de un asset con la fecha de modificación agregada.
 *
 * Así, al cambiar el CSS o el JS el navegador descarga la versión nueva
 * en lugar de usar la que tenía guardada.
 */
function asset_url($path)
{
    return base_url($path) . '?v=' . filemtime(FCPATH . $path);
}

/**
 * URL de una página del listado, conservando los filtros vigentes.
 *
 * Los filtros vacíos se mantienen en la URL a propósito: en algunos listados
 * un vacío significa algo (por ejemplo date= es lo que abre el historial de
 * turnos en lugar de la agenda del día).
 *
 * @param string $ruta   ruta del listado, por ejemplo 'clientes'
 * @param int    $pagina página destino
 * @param array  $filtros filtros vigentes del listado
 */
function page_url($ruta, $pagina, $filtros = array())
{
    $filtros['page'] = (int) $pagina;

    return site_url($ruta) . '?' . http_build_query(array_filter(
        $filtros,
        function ($valor) {
            return $valor !== NULL;
        }
    ));
}

/**
 * ¿El texto es una fecha real en formato YYYY-MM-DD?
 * Ejemplo válido: 2026-03-14. Inválido: 2026-02-31 o 14/03/2026.
 */
function valid_day($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
        return FALSE;
    }

    $date = DateTime::createFromFormat('!Y-m-d', $value);

    // createFromFormat acepta 31/02 y lo corrige: se compara de nuevo
    // para detectar fechas que no existen.
    return $date && $date->format('Y-m-d') === $value;
}

/**
 * ¿El texto es una hora en formato HH:MM, de 00:00 a 23:59?
 */
function valid_clock($value)
{
    return is_string($value)
        && preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $value) === 1;
}

/**
 * ¿El texto es un importe válido?
 *
 * @param bool $positive si es TRUE, además tiene que ser mayor a cero.
 */
function valid_money($value, $positive = FALSE)
{
    return is_scalar($value)
        && preg_match('/^\d{1,10}(?:\.\d{1,2})?$/D', (string) $value) === 1
        && (!$positive || (float) $value > 0);
}

/**
 * ¿El texto es un porcentaje válido (0 a 100)?
 * Es el porcentaje de comisión del peluquero.
 */
function valid_percent($value)
{
    return is_scalar($value)
        && preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', (string) $value) === 1
        && (float) $value <= 100;
}
