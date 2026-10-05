<div class="heading">
    <div>
        <h1>Contabilidad</h1>
        <p>Movimientos y liquidaciones basados en importes cobrados.</p>
    </div>
    <div class="inline">
        <a class="btn" href="<?= site_url('egresos') ?>">Administrar egresos</a>
        <a class="btn" href="<?= site_url('formas-pago') ?>">Formas de pago</a>
    </div>
</div>

<!-- Período a liquidar. -->
<section class="card" style="margin-bottom:16px">
    <form class="toolbar" method="get" action="<?= site_url('contabilidad') ?>">
        <div class="filters">
            <div class="field">
                <label>Desde</label>
                <input class="input w-auto" type="date" name="from" value="<?= h($from) ?>" required>
            </div>

            <div class="field">
                <label>Hasta</label>
                <input class="input w-auto" type="date" name="to" value="<?= h($to) ?>" required>
            </div>
        </div>

        <button class="btn primary" type="submit">Consultar período</button>
    </form>
</section>

<!-- Resultado del período. -->
<div class="kpis">
    <div class="kpi">
        <span>Ingresos</span>
        <strong><?= money($income) ?></strong>
        <small>Cobros registrados</small>
    </div>

    <div class="kpi">
        <span>Egresos</span>
        <strong><?= money($expenses_total) ?></strong>
        <small>Gastos del local</small>
    </div>

    <div class="kpi">
        <span>A pagar peluqueros</span>
        <strong><?= money($commissions) ?></strong>
        <small>Porcentajes al cobrar</small>
    </div>

    <div class="kpi">
        <span>Resultado del local</span>
        <strong><?= money($result) ?></strong>
        <small>Ingresos - egresos - comisiones</small>
    </div>
</div>

<div class="grid g2" style="margin-top:16px">

    <!-- Alta de egreso. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Registrar egreso</h3>
                <p>Solo Encargado</p>
            </div>
        </div>

        <?= form_open('egresos/guardar') ?>

            <div class="formgrid">
                <div class="field">
                    <label>Fecha</label>
                    <input class="input" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="field">
                    <label>Categoría</label>
                    <select name="category_id" required>
                        <?php foreach ($categories as $categoria): ?>
                            <option value="<?= (int) $categoria['id'] ?>"><?= h($categoria['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Concepto</label>
                    <input class="input" name="concept" maxlength="180" required>
                </div>

                <div class="field">
                    <label>Monto</label>
                    <input class="input" type="number" min="0.01" step="0.01" name="amount" required>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn primary" type="submit">Registrar egreso</button>
            </div>

        <?= form_close() ?>
    </section>

    <!-- Liquidación por peluquero. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Liquidación por peluquero</h3>
                <p>Producción = importes reales cobrados; a pagar = comisiones históricas. El porcentaje mostrado es efectivo si cambió durante el período.</p>
            </div>
        </div>

        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Peluquero</th>
                        <th>Atenciones</th>
                        <th>Producción</th>
                        <th>Porcentaje efectivo</th>
                        <th>A pagar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$production): ?>
                        <tr>
                            <td colspan="5" class="empty">Sin cobros en este período.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($production as $peluquero): ?>
                        <tr>
                            <td><?= h($peluquero['full_name']) ?></td>
                            <td><?= (int) $peluquero['attentions'] ?></td>
                            <td><?= money($peluquero['production']) ?></td>
                            <td>
                                <?php if ($peluquero['production'] > 0): ?>
                                    <?= h(number_format(100 * $peluquero['payout'] / $peluquero['production'], 2, ',', '.')) ?> %
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?= money($peluquero['payout']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>

<?php
// Cobros y egresos se juntan en una sola lista para mostrar el movimiento real
// de caja, del más reciente al más antiguo.
$movimientos = array();

foreach ($payments as $cobro) {
    $movimientos[] = array(
        'date' => $cobro['paid_at'],
        'kind' => 'Cobro',
        'detail' => $cobro['client_name'] . ' · ' . $cobro['barber_name'] . ' · '
            . $cobro['payment_method'] . ' · comisión ' . $cobro['commission_percent_snapshot'] . '%',
        'amount' => $cobro['amount'],
        'link' => site_url('cobros/editar/' . $cobro['id']),
    );
}

foreach ($expenses as $egreso) {
    $movimientos[] = array(
        'date' => $egreso['expense_date'] . ' ' . substr($egreso['created_at'], 11, 8),
        'kind' => 'Egreso',
        'detail' => $egreso['concept'] . ' · ' . $egreso['category_name'],
        'amount' => -$egreso['amount'],
        'link' => site_url('egresos/editar/' . $egreso['id']),
    );
}

usort($movimientos, function ($primero, $segundo) {
    return strcmp($segundo['date'], $primero['date']);
});
?>

<!-- Movimientos del período. -->
<section class="card" style="margin-top:16px">
    <div class="cardhead">
        <div>
            <h3>Movimientos</h3>
            <p>Ordenados del más reciente al más antiguo</p>
        </div>
    </div>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Detalle</th>
                    <th>Importe</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$movimientos): ?>
                    <tr>
                        <td colspan="5" class="empty">Sin movimientos en este período.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($movimientos as $movimiento): ?>
                    <tr>
                        <td><?= date('d/m/Y H:i', strtotime($movimiento['date'])) ?></td>
                        <td><?= h($movimiento['kind']) ?></td>
                        <td><?= h($movimiento['detail']) ?></td>
                        <td><?= money($movimiento['amount']) ?></td>
                        <td><a class="btn" href="<?= $movimiento['link'] ?>">Corregir</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
