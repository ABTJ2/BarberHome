<?php
// La misma vista sirve para el alta y para la edición: si el servicio ya tiene
// id, el formulario guarda los cambios; si no, lo crea.
$es_edicion = $service && !empty($service['id']);
?>
<div class="heading">
    <div>
        <h1><?= $es_edicion ? 'Editar servicio' : 'Nuevo servicio' ?></h1>
        <p>
            El precio actualizado se aplica a turnos futuros; los turnos existentes
            conservan su valor registrado.
        </p>
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

    <?= form_open($es_edicion ? 'servicios/actualizar/' . $service['id'] : 'servicios/guardar') ?>

        <div class="formgrid3">
            <div class="field">
                <label class="required">Nombre</label>
                <input class="input" name="name" value="<?= h($service['name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Precio</label>
                <input class="input" type="number" min="0" step="0.01"
                       name="price" value="<?= h($service['price'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label class="required">Duración (minutos)</label>
                <input class="input" type="number" min="1"
                       name="duration_minutes" value="<?= h($service['duration_minutes'] ?? '45') ?>" required>
            </div>
        </div>

        <div class="check" style="margin-top:14px">
            <label>
                <input type="checkbox" name="active" value="1"
                    <?= !isset($service['active']) || $service['active'] ? 'checked' : '' ?>>
                Servicio activo
            </label>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('servicios') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar</button>
        </div>

    <?= form_close() ?>

</section>
