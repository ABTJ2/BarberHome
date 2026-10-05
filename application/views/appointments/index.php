<div class="heading">
    <div>
        <h1>Agenda / Turnos</h1>
        <p>Buscar y administrar turnos.</p>
    </div>
    <div class="inline">
        <a class="btn" href="<?= site_url('turnos/sin-reserva') ?>">Atención sin turno</a>
        <a class="btn primary" href="<?= site_url('turnos/nuevo') ?>">+ Agregar turno</a>
    </div>
</div>

<section class="card">

    <!-- Filtros: con fecha se ve la agenda del día; sin fecha, el historial. -->
    <form class="toolbar" method="get" action="<?= site_url('turnos') ?>">
        <div class="filters">
            <input class="input w-auto" type="date" name="date" value="<?= h($date) ?>"
                   title="Dejar vacío para buscar en todas las fechas">

            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Cliente, celular o peluquero">

            <select name="barber_id">
                <option value="">Todos los peluqueros</option>
                <?php foreach ($barbers as $peluquero): ?>
                    <option value="<?= (int) $peluquero['id'] ?>"
                        <?= $barber_id == $peluquero['id'] ? 'selected' : '' ?>>
                        <?= h($peluquero['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="status">
                <option value="">Todos los estados</option>
                <?php foreach (status_options() as $codigo => $nombre): ?>
                    <option value="<?= $codigo ?>" <?= $status === $codigo ? 'selected' : '' ?>>
                        <?= $nombre ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="inline">
            <button class="btn" type="submit">Buscar</button>
            <a class="btn" href="<?= site_url('turnos') ?>">Limpiar</a>
        </div>
    </form>

    <p class="small muted">Dejá la fecha vacía para buscar en toda la agenda.</p>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha y hora</th>
                    <th>Cliente</th>
                    <th>Peluquero</th>
                    <th>Servicios</th>
                    <th>Duración</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$appointments): ?>
                    <tr>
                        <td colspan="7" class="empty">No se encontraron turnos.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($appointments as $turno): ?>
                    <tr>
                        <td><strong><?= date('d/m/Y H:i', strtotime($turno['start_at'])) ?></strong></td>

                        <td>
                            <?= h($turno['client_name']) ?>
                            <?php if ($turno['walk_in']): ?>
                                <span class="badge gold">Sin reserva</span>
                            <?php endif; ?>
                        </td>

                        <td><?= h($turno['barber_name']) ?></td>
                        <td><?= h($turno['services'] ?: '—') ?></td>
                        <td><?= (int) $turno['service_minutes'] ?> min</td>

                        <td>
                            <span class="badge <?= status_class($turno['status']) ?>">
                                <?= h(status_label($turno['status'])) ?>
                            </span>
                        </td>

                        <td>
                            <div class="inline">
                                <a class="btn" href="<?= site_url('turnos/ver/' . $turno['id']) ?>">Ver</a>

                                <?php if (!$turno['has_payment']): ?>
                                    <a class="btn" href="<?= site_url('turnos/editar/' . $turno['id']) ?>">Editar</a>

                                    <?php if ($turno['status'] !== 'cancelled'): ?>
                                        <?= form_open('turnos/eliminar/' . $turno['id']) ?>
                                            <button class="btn danger" type="submit"
                                                    data-confirm="¿Eliminar este turno? Quedará cancelado y se conservará en el historial.">
                                                Eliminar
                                            </button>
                                        <?= form_close() ?>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php $this->load->view('partials/pagination'); ?>

</section>
