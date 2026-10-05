<?php
// Esta vista cubre tres casos con el mismo formulario: alta, edición y
// atención sin turno previo. Solo cambian los valores iniciales.
$es_edicion  = $appointment && !empty($appointment['id']);
$client_id   = $appointment['client_id'] ?? (int) $this->input->get('client_id');
$barber_id   = $appointment['barber_id'] ?? (int) $this->input->get('barber_id');
$date        = $appointment['date'] ?? ($es_edicion ? substr($appointment['start_at'], 0, 10) : ($this->input->get('date', TRUE) ?: date('Y-m-d')));
$time        = $appointment['time'] ?? ($es_edicion ? substr($appointment['start_at'], 11, 5)
    : ($this->input->get('time', TRUE) ?: ($walkin ? date('H:i') : date('H:i', strtotime('+1 hour')))));
$status      = $appointment['status'] ?? ($walkin ? 'attended' : 'reserved');
?>
<div class="heading">
    <div>
        <h1><?= $es_edicion ? 'Editar turno' : ($walkin ? 'Atención sin turno previo' : 'Nuevo turno') ?></h1>
        <p>Se validan horario, servicios y superposición al guardar.</p>
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

    <?= form_open($es_edicion ? 'turnos/actualizar/' . $appointment['id'] : 'turnos/guardar',
        array('data-availability-url' => site_url('turnos/disponibilidad'),
            'data-appointment-id' => $es_edicion ? $appointment['id'] : '')) ?>

        <input type="hidden" name="walk_in" value="<?= $walkin ? 1 : 0 ?>">

        <div class="formgrid">
            <div class="field">
                <label class="required">Cliente</label>
                <select name="client_id" required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= (int) $client['id'] ?>" <?= $client_id == $client['id'] ? 'selected' : '' ?>>
                            <?= h($client['first_name'] . ' ' . $client['last_name'] . ' · ' . $client['phone']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a class="small" href="<?= site_url('clientes/nuevo') ?>">¿No figura? Crear cliente</a>
            </div>

            <div class="field">
                <label class="required">Peluquero</label>
                <select name="barber_id" data-availability-input required>
                    <option value="">Seleccionar</option>
                    <?php foreach ($barbers as $peluquero): ?>
                        <option value="<?= (int) $peluquero['id'] ?>"
                            <?= $barber_id == $peluquero['id'] ? 'selected' : '' ?>>
                            <?= h($peluquero['full_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label class="required">Fecha</label>
                <input class="input" type="date" name="date" value="<?= h($date) ?>" data-availability-input required>
            </div>

            <div class="field">
                <label class="required">Hora</label>
                <input class="input" type="time" name="time" value="<?= h($time) ?>" data-appointment-time required>
            </div>
        </div>

        <div class="divider"></div>

        <!-- Servicios de la atención: se pueden combinar varios. -->
        <div class="cardhead">
            <div>
                <h3>Servicios</h3>
                <p>Se pueden combinar varios en la misma atención.</p>
            </div>
        </div>

        <div class="service-picker">
            <?php foreach ($services as $servicio): ?>
                <label class="service-option">
                    <input type="checkbox" name="service_ids[]" value="<?= (int) $servicio['id'] ?>"
                           data-service-check
                           data-availability-input
                           data-price="<?= h($servicio['price']) ?>"
                           data-minutes="<?= (int) $servicio['duration_minutes'] ?>"
                        <?= in_array((int) $servicio['id'], $selected_services, TRUE) ? 'checked' : '' ?>>
                    <span>
                        <strong><?= h($servicio['name']) ?></strong>
                        <small><?= money($servicio['price']) ?> · <?= (int) $servicio['duration_minutes'] ?> min</small>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="card" style="margin-top:16px" data-availability-results>
            <h3>Horarios disponibles</h3>
            <p data-availability-message>Seleccioná fecha, peluquero y servicios para consultar.</p>
            <div class="inline" data-availability-slots></div>
        </div>

        <div class="note" style="margin-top:14px">
            Estimado: <strong data-service-total>$0</strong> · <strong data-service-minutes>0 min</strong>.
            Los importes se verifican otra vez en el servidor.
        </div>

        <div class="formgrid">
            <div class="field">
                <label>Estado</label>
                <select name="status">
                    <?php foreach (status_options() as $codigo => $nombre): ?>
                        <option value="<?= $codigo ?>" <?= $status === $codigo ? 'selected' : '' ?>>
                            <?= $nombre ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label>Observación</label>
                <textarea name="notes" maxlength="500"><?= h($appointment['notes'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <a class="btn" href="<?= site_url('turnos') ?>">Cancelar</a>
            <button class="btn primary" type="submit">Guardar turno</button>
        </div>

    <?= form_close() ?>

</section>
