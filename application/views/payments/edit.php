<div class="heading">
    <div>
        <h1>Corregir cobro</h1>
        <p>Función restringida al Encargado.</p>
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

<section class="card" style="max-width:760px">

    <?= form_open('cobros/actualizar/' . $payment['id']) ?>

        <div class="formgrid">
            <div class="field">
                <label>Importe</label>
                <input class="input" type="number" step="0.01" min="0.01"
                       name="amount" value="<?= h($payment['amount']) ?>" required>
            </div>

            <div class="field">
                <label>Forma de pago</label>
                <select name="payment_method_id" required>
                    <?php foreach ($methods as $metodo): ?>
                        <option value="<?= $metodo['id'] ?>"
                            <?= $payment['payment_method_id'] == $metodo['id'] ? 'selected' : '' ?>>
                            <?= h($metodo['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="field" style="margin-top:13px">
            <label>Observación</label>
            <textarea name="notes"><?= h($payment['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('contabilidad') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar corrección</button>
        </div>

    <?= form_close() ?>

</section>
