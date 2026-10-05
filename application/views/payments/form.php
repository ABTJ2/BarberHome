<?php
// Precio de lista del turno: se usa como valor sugerido del cobro.
$total_lista = 0;

foreach ($appointment['services'] as $servicio) {
    $total_lista += (float) $servicio['price_snapshot'];
}
?>
<div class="heading">
    <div>
        <h1>Registrar cobro</h1>
        <p><?= h($appointment['client_name']) ?> · <?= date('d/m/Y H:i', strtotime($appointment['start_at'])) ?></p>
    </div>
</div>

<?php if ($errors): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?= h($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="grid g2">

    <!-- Datos del turno que se está cobrando. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Atención</h3>
                <p>Datos vinculados al cobro</p>
            </div>
        </div>

        <div class="row">
            <span>Peluquero</span>
            <strong><?= h($appointment['barber_name']) ?></strong>
        </div>

        <div class="row">
            <span>Servicios</span>
            <strong><?= h(implode(', ', array_column($appointment['services'], 'name'))) ?></strong>
        </div>

        <div class="row">
            <span>Precio de lista</span>
            <strong><?= money($total_lista) ?></strong>
        </div>

        <div class="row">
            <span>Estado actual</span>
            <span class="badge <?= status_class($appointment['status']) ?>">
                <?= h(status_label($appointment['status'])) ?>
            </span>
        </div>
    </section>

    <!-- Formulario del cobro. -->
    <section class="card">
        <?= form_open('cobros/guardar') ?>

            <input type="hidden" name="appointment_id" value="<?= $appointment['id'] ?>">

            <div class="field">
                <label class="required">Importe real cobrado</label>
                <input class="input" type="number" min="0.01" step="0.01"
                       name="amount" value="<?= h($total_lista) ?>" required>
            </div>

            <div class="field" style="margin-top:13px">
                <label class="required">Forma de pago</label>
                <select name="payment_method_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($methods as $metodo): ?>
                        <option value="<?= $metodo['id'] ?>"><?= h($metodo['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field" style="margin-top:13px">
                <label>Observación</label>
                <textarea name="notes" placeholder="Promoción, descuento u otra aclaración"></textarea>
            </div>

            <div class="note" style="margin-top:14px">
                Al confirmar, el turno queda <strong>Atendido</strong> y el cobro se contabiliza
                automáticamente como ingreso.
            </div>

            <div class="form-actions">
                <a class="btn" href="<?= site_url('turnos/ver/' . $appointment['id']) ?>">Cancelar</a>
                <button class="btn primary" type="submit">Confirmar cobro</button>
            </div>

        <?= form_close() ?>
    </section>

</div>
