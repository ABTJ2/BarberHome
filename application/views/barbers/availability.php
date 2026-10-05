<div class="heading">
    <div>
        <h1>Disponibilidad de <?= h($barber['full_name']) ?></h1>
        <p>Horarios de trabajo, turnos ocupados y espacios libres.</p>
    </div>
    <a class="btn" href="<?= site_url('peluqueros') ?>">Volver a peluqueros</a>
</div>

<section class="card">
    <form class="toolbar" method="get" action="<?= site_url('peluqueros/disponibilidad/' . $barber['id']) ?>">
        <div class="field">
            <label>Día</label>
            <input class="input" type="date" name="date" value="<?= h($date) ?>" required>
        </div>
        <div class="field">
            <label>Servicios (la duración determina los huecos)</label>
            <select name="service_ids[]" multiple size="5" required>
                <?php foreach ($services as $service): ?>
                    <option value="<?= (int) $service['id'] ?>"
                        <?= in_array((string) $service['id'], $selected_services, TRUE) ? 'selected' : '' ?>>
                        <?= h($service['name']) ?> · <?= (int) $service['duration_minutes'] ?> min
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="btn primary" type="submit">Consultar</button>
    </form>

    <div class="divider"></div>
    <p>Horario configurado: <strong><?= $availability['schedule']
        ? h(substr($availability['schedule']['start_time'], 0, 5) . ' a ' . substr($availability['schedule']['end_time'], 0, 5))
        : 'No trabaja este día' ?></strong></p>

    <h3>Turnos ocupados</h3>
    <?php if (!$availability['occupied']): ?>
        <p>No hay turnos activos ese día.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($availability['occupied'] as $occupied): ?>
                <li><?= h(substr($occupied['start_at'], 11, 5) . ' a ' . substr($occupied['end_at'], 11, 5)) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h3>Espacios disponibles</h3>
    <?php if ($availability['error']): ?>
        <p><?= h($availability['error']) ?></p>
    <?php elseif (!$availability['slots']): ?>
        <p>No hay horarios libres para estos servicios.</p>
    <?php else: ?>
        <p>Duración de la atención: <?= (int) $availability['minutes'] ?> min</p>
        <div class="inline">
            <?php foreach ($availability['slots'] as $time): ?>
                <?php $query = http_build_query(array('barber_id' => $barber['id'], 'date' => $date,
                    'time' => $time, 'service_ids' => $selected_services)); ?>
                <a class="btn" href="<?= site_url('turnos/nuevo') . '?' . h($query) ?>"><?= h($time) ?></a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
