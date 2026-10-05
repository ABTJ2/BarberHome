<?php
/**
 * Barra de paginación de los listados largos.
 *
 * Se incluye desde los listados de clientes, historial de turnos, cobros y
 * egresos. No muestra nada si todo entra en una sola página.
 *
 * Espera tres variables:
 * - $paginacion: lo devuelve MY_Controller::paginacion()
 * - $ruta_paginacion: ruta del listado, por ejemplo 'clientes'
 * - $filtros_paginacion: filtros vigentes, para no perderlos al cambiar de página
 */
if (empty($paginacion) || $paginacion['pages'] < 2) {
    return;
}
?>
<div class="space" style="margin-top:15px">

    <span class="small muted">
        Mostrando <?= (int) $paginacion['first'] ?>&ndash;<?= (int) $paginacion['last'] ?>
        de <?= (int) $paginacion['total'] ?>
    </span>

    <span class="inline">
        <?php if ($paginacion['page'] > 1): ?>
            <a class="btn" href="<?= page_url($ruta_paginacion, $paginacion['page'] - 1, $filtros_paginacion) ?>">
                Anterior
            </a>
        <?php endif; ?>

        <span class="small muted">
            Página <?= (int) $paginacion['page'] ?> de <?= (int) $paginacion['pages'] ?>
        </span>

        <?php if ($paginacion['page'] < $paginacion['pages']): ?>
            <a class="btn" href="<?= page_url($ruta_paginacion, $paginacion['page'] + 1, $filtros_paginacion) ?>">
                Siguiente
            </a>
        <?php endif; ?>
    </span>

</div>
