<?php
// La misma vista sirve para el alta y para la edición: si el peluquero ya
// tiene id, el formulario guarda los cambios; si no, lo crea.
$es_edicion = $barber && !empty($barber['id']);

// Se acomodan los horarios por día (1 = lunes ... 7 = domingo) para poder
// mostrar los siete días siempre en el mismo orden.
$horarios_por_dia = array();

foreach ($schedules as $horario) {
    $horarios_por_dia[(int) $horario['day_of_week']] = $horario;
}
?>
<div class="heading">
    <div>
        <h1><?= $es_edicion ? 'Editar peluquero' : 'Nuevo peluquero' ?></h1>
        <p>Datos, servicios, días y horarios de trabajo.</p>
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

    <?= form_open($es_edicion ? 'peluqueros/actualizar/' . $barber['id'] : 'peluqueros/guardar') ?>

        <div class="formgrid">
            <div class="field">
                <label class="required">Nombre y apellido</label>
                <input class="input" name="full_name" value="<?= h($barber['full_name'] ?? '') ?>" required>
            </div>

            <div class="field">
                <label>Teléfono</label>
                <input class="input" name="phone" value="<?= h($barber['phone'] ?? '') ?>">
            </div>

            <div class="field">
                <label class="required">Porcentaje</label>
                <input class="input" type="number" min="0" max="100" step="0.01"
                       name="commission_percent" value="<?= h($barber['commission_percent'] ?? '50') ?>" required>
            </div>

            <div class="check">
                <label>
                    <input type="checkbox" name="active" value="1"
                        <?= !isset($barber['active']) || $barber['active'] ? 'checked' : '' ?>>
                    Peluquero activo
                </label>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Servicios que puede realizar. -->
        <div class="cardhead">
            <div>
                <h3>Servicios que realiza</h3>
                <p>Se usan para validar los turnos</p>
            </div>
        </div>

        <div class="service-picker">
            <?php foreach ($services as $servicio): ?>
                <label class="service-option">
                    <input type="checkbox" name="service_ids[]" value="<?= $servicio['id'] ?>"
                        <?= in_array((int) $servicio['id'], $selected_services) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= h($servicio['name']) ?></strong>
                        <small><?= money($servicio['price']) ?> · <?= (int) $servicio['duration_minutes'] ?> min</small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="divider"></div>

        <!-- Días y horarios de trabajo. -->
        <div class="cardhead">
            <div>
                <h3>Días y horarios</h3>
                <p>El sistema impedirá turnos fuera de estos rangos</p>
            </div>
        </div>

        <?php for ($dia = 1; $dia <= 7; $dia++): ?>
            <?php $horario = isset($horarios_por_dia[$dia]) ? $horarios_por_dia[$dia] : NULL; ?>

            <div class="schedule-grid">
                <div class="day"><?= h(day_name($dia)) ?></div>

                <label class="checkday">
                    <input type="checkbox" name="day_<?= $dia ?>" value="1" <?= $horario ? 'checked' : '' ?>>
                    Trabaja
                </label>

                <input class="input" type="time" name="start_<?= $dia ?>"
                       value="<?= h($horario ? substr($horario['start_time'], 0, 5) : '09:00') ?>">

                <input class="input" type="time" name="end_<?= $dia ?>"
                       value="<?= h($horario ? substr($horario['end_time'], 0, 5) : '18:00') ?>">
            </div>
        <?php endfor; ?>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('peluqueros') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar peluquero</button>
        </div>

    <?= form_close() ?>

</section>
