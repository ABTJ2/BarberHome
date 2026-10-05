<?php
// La misma vista sirve para el alta y para la edición: si el cliente ya tiene
// id, el formulario guarda los cambios; si no, lo crea.
$es_edicion = $client && !empty($client['id']);
?>
<div class="heading">
    <div>
        <h1><?= $es_edicion ? 'Editar cliente' : 'Nuevo cliente' ?></h1>
        <p>Datos mínimos definidos en el relevamiento.</p>
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

<section class="card">

    <?= form_open($es_edicion ? 'clientes/actualizar/' . $client['id'] : 'clientes/guardar') ?>

        <div class="formgrid">
            <div class="field">
                <label class="required">Nombre</label>
                <input class="input" name="first_name" value="<?= h($client['first_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Apellido</label>
                <input class="input" name="last_name" value="<?= h($client['last_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Teléfono</label>
                <input class="input" name="phone" value="<?= h($client['phone'] ?? '') ?>" required>
            </div>
        </div>

        <div class="field" style="margin-top:13px">
            <label>Observaciones</label>
            <textarea name="notes"><?= h($client['notes'] ?? '') ?></textarea>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('clientes') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar cliente</button>
        </div>

    <?= form_close() ?>

</section>
