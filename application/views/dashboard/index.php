<?php
// El Encargado ve además los números del local; la Recepcionista solo la jornada.
$es_encargado = $current_user['role_code'] === 'encargado';
?>
<div class="heading">
    <div>
        <h1>Inicio</h1>
        <p>Resumen de la jornada.</p>
    </div>
    <a class="btn primary" href="<?= site_url('turnos/nuevo') ?>">+ Nuevo turno</a>
</div>

<!-- Resumen del día. -->
<div class="kpis">
    <div class="kpi">
        <span>Turnos de hoy</span>
        <strong><?= (int) $counts['total'] ?></strong>
        <small><?= (int) $counts['reserved'] ?> pendientes</small>
    </div>

    <div class="kpi">
        <span>Atendidos</span>
        <strong><?= (int) $counts['attended'] ?></strong>
        <small>Jornada actual</small>
    </div>

    <div class="kpi">
        <span>Cancelados / ausentes</span>
        <strong><?= (int) ($counts['cancelled'] + $counts['no_show']) ?></strong>
        <small>Jornada actual</small>
    </div>

    <div class="kpi">
        <span>Cobrado hoy</span>
        <strong><?= money($income) ?></strong>
        <small>Importes reales</small>
    </div>

    <?php if ($es_encargado): ?>
        <div class="kpi">
            <span>Egresos</span>
            <strong><?= money($expenses) ?></strong>
            <small>Hoy</small>
        </div>

        <div class="kpi">
            <span>Comisiones</span>
            <strong><?= money($commissions) ?></strong>
            <small>Hoy</small>
        </div>

        <div class="kpi">
            <span>Resultado del local</span>
            <strong><?= money($local_result) ?></strong>
            <small>Hoy</small>
        </div>
    <?php endif; ?>
</div>

<div class="grid g2" style="margin-top:16px">

    <!-- Turnos del día. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Agenda de hoy</h3>
                <p>Próximos y recientes</p>
            </div>
            <a class="btn" href="<?= site_url('turnos') ?>">Ver agenda</a>
        </div>

        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Peluquero</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recent): ?>
                        <tr>
                            <td colspan="4" class="empty">Sin turnos hoy.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($recent as $turno): ?>
                        <tr>
                            <td><?= date('H:i', strtotime($turno['start_at'])) ?></td>
                            <td>
                                <a href="<?= site_url('turnos/ver/' . $turno['id']) ?>"><?= h($turno['client_name']) ?></a>
                            </td>
                            <td><?= h($turno['barber_name']) ?></td>
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

    <!-- Atajos. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Accesos rápidos</h3>
                <p>Funciones más utilizadas</p>
            </div>
        </div>

        <div class="stack">
            <a class="btn primary" href="<?= site_url('turnos/nuevo') ?>">Crear turno</a>
            <a class="btn" href="<?= site_url('turnos/sin-reserva') ?>">Atención sin turno previo</a>
            <a class="btn" href="<?= site_url('clientes/nuevo') ?>">Registrar cliente</a>
            <a class="btn" href="<?= site_url('cobros') ?>">Cobros</a>
            <?php if ($es_encargado): ?>
                <a class="btn" href="<?= site_url('contabilidad') ?>">Contabilidad</a>
            <?php endif; ?>
        </div>
    </section>

</div>
