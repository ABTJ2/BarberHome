<div class="heading">
    <div>
        <h1>Clientes</h1>
        <p>Buscar, agregar, editar y eliminar clientes.</p>
    </div>
    <a class="btn primary" href="<?= site_url('clientes/nuevo') ?>">+ Agregar cliente</a>
</div>

<section class="card">

    <!-- Buscador: texto y clientes eliminados. -->
    <form class="toolbar" method="get" action="<?= site_url('clientes') ?>">
        <div class="filters">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Nombre, apellido o celular">
            <label class="check">
                <input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Eliminados
            </label>
        </div>

        <div class="inline">
            <button class="btn" type="submit">Buscar</button>
            <a class="btn" href="<?= site_url('clientes') ?>">Limpiar</a>
        </div>
    </form>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Celular</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$clients): ?>
                    <tr>
                        <td colspan="4" class="empty">No se encontraron clientes.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><strong><?= h($client['first_name'] . ' ' . $client['last_name']) ?></strong></td>
                        <td><?= h($client['phone']) ?></td>
                        <td><?= h($client['notes'] ?: '—') ?></td>
                        <td>
                            <div class="inline">
                                <?php if ($archived): ?>
                                    <?= form_open('clientes/restaurar/' . $client['id']) ?>
                                        <button class="btn" type="submit">Restaurar</button>
                                    <?= form_close() ?>
                                <?php else: ?>
                                    <a class="btn" href="<?= site_url('clientes/ver/' . $client['id']) ?>">Ver</a>
                                    <a class="btn" href="<?= site_url('clientes/editar/' . $client['id']) ?>">Editar</a>
                                    <?= form_open('clientes/eliminar/' . $client['id']) ?>
                                        <button class="btn danger" type="submit"
                                                data-confirm="¿Eliminar a este cliente? Su historial se conservará.">
                                            Eliminar
                                        </button>
                                    <?= form_close() ?>
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
