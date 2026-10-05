<div class="heading">
    <div>
        <h1>Usuarios</h1>
        <p>Cuentas y perfiles de acceso.</p>
    </div>
    <a class="btn primary" href="<?= site_url('usuarios/nuevo') ?>">+ Agregar usuario</a>
</div>

<section class="card">

    <!-- Buscador: texto y eliminados. -->
    <form class="toolbar" method="get" action="<?= site_url('usuarios') ?>">
        <div class="filters">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Nombre o usuario">
            <label class="check">
                <input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Eliminados
            </label>
        </div>

        <div class="inline">
            <button class="btn" type="submit">Buscar</button>
            <a class="btn" href="<?= site_url('usuarios') ?>">Limpiar</a>
        </div>
    </form>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Usuario</th>
                    <th>Perfil</th>
                    <th>Estado</th>
                    <th>Último acceso</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="6" class="empty">No se encontraron usuarios.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $usuario): ?>
                    <tr>
                        <td><strong><?= h($usuario['full_name']) ?></strong></td>
                        <td><?= h($usuario['username']) ?></td>
                        <td><?= h($usuario['role_name']) ?></td>
                        <td>
                            <span class="badge <?= $usuario['active'] ? 'green' : 'neutral' ?>">
                                <?= $usuario['active'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td>
                            <?= $usuario['last_login_at'] ? date('d/m/Y H:i', strtotime($usuario['last_login_at'])) : '—' ?>
                        </td>
                        <td>
                            <div class="inline">
                                <?php if ($archived): ?>
                                    <?= form_open('usuarios/restaurar/' . $usuario['id']) ?>
                                        <button class="btn" type="submit">Restaurar</button>
                                    <?= form_close() ?>
                                <?php else: ?>
                                    <a class="btn" href="<?= site_url('usuarios/editar/' . $usuario['id']) ?>">Editar</a>

                                    <?php if ($usuario['id'] != $current_user['id']): ?>
                                        <?= form_open('usuarios/eliminar/' . $usuario['id']) ?>
                                            <button class="btn danger" type="submit"
                                                    data-confirm="¿Eliminar este usuario y bloquear su acceso?">
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

</section>
