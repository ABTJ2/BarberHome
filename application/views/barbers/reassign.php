<div class="heading">
    <div>
        <h1>Reasignar turnos</h1>
        <p><?= h($barber['full_name']) ?> · turnos futuros reservados.</p>
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

<?php if (!$appointments): ?>

    <section class="card empty">No hay turnos futuros pendientes para reasignar.</section>

<?php else: ?>

    <section class="card">

        <?= form_open('peluqueros/reasignar/' . $barber['id']) ?>

            <div class="formgrid">
                <div class="field">
                    <label>Turno afectado</label>
                    <select name="appointment_id" required>
                        <?php foreach ($appointments as $turno): ?>
                            <option value="<?= $turno['id'] ?>">
                                <?= date('d/m/Y H:i', strtotime($turno['start_at'])) ?> · <?= h($turno['client_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Nuevo peluquero</label>
                    <select name="barber_id" required>
                        <?php foreach ($barbers as $otro): ?>
                            <?php if ($otro['id'] == $barber['id']) {
                                continue;
                            } ?>
                            <option value="<?= $otro['id'] ?>"><?= h($otro['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label>Nueva fecha</label>
                    <input class="input" type="date" name="date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="field">
                    <label>Nueva hora</label>
                    <input class="input" type="time" name="time" value="10:00" required>
                </div>
            </div>

            <div class="note" style="margin-top:14px">
                Antes de guardar se vuelve a validar horario de trabajo, servicios y superposición.
            </div>

            <div class="form-actions">
                <a class="btn" href="<?= site_url('peluqueros') ?>">Volver</a>
                <button class="btn primary" type="submit">Reasignar turno</button>
            </div>

        <?= form_close() ?>

    </section>

<?php endif; ?>
