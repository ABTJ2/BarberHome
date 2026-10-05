<div class="heading">
    <div>
        <h1><?= h($client['first_name'] . ' ' . $client['last_name']) ?></h1>
        <p><?= h($client['phone']) ?></p>
    </div>
    <div class="inline">
        <a class="btn" href="<?= site_url('clientes/editar/' . $client['id']) ?>">Editar</a>
        <a class="btn primary" href="<?= site_url('turnos/nuevo?client_id=' . $client['id']) ?>">Nuevo turno</a>
    </div>
</div>

<div class="grid g2">

    <!-- Ficha del cliente. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Datos</h3>
                <p>Información del cliente</p>
            </div>
        </div>

        <div class="row">
            <span>Nombre</span>
            <strong><?= h($client['first_name'] . ' ' . $client['last_name']) ?></strong>
        </div>

        <div class="row">
            <span>Teléfono</span>
            <strong><?= h($client['phone']) ?></strong>
        </div>

        <div class="row">
            <span>Observaciones</span>
            <strong><?= h($client['notes'] ?: '—') ?></strong>
        </div>
    </section>

    <!-- Historial de turnos del cliente. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Historial</h3>
                <p>Turnos anteriores</p>
            </div>
        </div>

        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Peluquero</th>
                        <th>Servicios</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$history): ?>
                        <tr>
                            <td colspan="4" class="empty">Sin historial todavía.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($history as $turno): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($turno['start_at'])) ?></td>
                            <td><?= h($turno['barber_name']) ?></td>
                            <td><?= h($turno['services'] ?: '—') ?></td>
                            <td>
                                <span class="badge <?= status_class($turno['status']) ?>">
                                    <?= h(status_label($turno['status'])) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div>
