<div class="heading">
    <div>
        <h1>Egresos</h1>
        <p>Agregar, buscar, editar y eliminar gastos del local.</p>
    </div>
    <a class="btn" href="<?= site_url('contabilidad') ?>">Ver contabilidad</a>
</div>

<?php if (!$archived): ?>

    <!-- Alta rápida de un egreso. -->
    <section class="card" style="margin-bottom:16px">
        <div class="cardhead">
            <div>
                <h3>Agregar egreso</h3>
                <p>Se incluirá en la contabilidad de la fecha indicada.</p>
            </div>
        </div>

        <?= form_open('egresos/guardar') ?>

            <div class="formgrid">
                <div class="field">
                    <label class="required">Fecha</label>
                    <input class="input" type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="field">
                    <label class="required">Categoría</label>
                    <select name="category_id" required>
                        <option value="">Seleccionar</option>
                        <?php foreach ($categories as $categoria): ?>
                            <option value="<?= (int) $categoria['id'] ?>"><?= h($categoria['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label class="required">Concepto</label>
                    <input class="input" name="concept" maxlength="180" required>
                </div>

                <div class="field">
                    <label class="required">Monto</label>
                    <input class="input" type="number" min="0.01" step="0.01" name="amount" required>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn primary" type="submit">Agregar egreso</button>
            </div>

        <?= form_close() ?>
    </section>

<?php endif; ?>

<section class="card">

    <!-- Filtros por texto, rango de fechas y eliminados. -->
    <form class="toolbar" method="get" action="<?= site_url('egresos') ?>">
        <div class="filters">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Concepto o categoría">
            <input class="input w-auto" type="date" name="from" value="<?= h($from) ?>" title="Desde">
            <input class="input w-auto" type="date" name="to" value="<?= h($to) ?>" title="Hasta">
            <label class="check">
                <input type="checkbox" name="archived" value="1" <?= $archived ? 'checked' : '' ?>> Eliminados
            </label>
        </div>

        <div class="inline">
            <button class="btn" type="submit">Buscar</button>
            <a class="btn" href="<?= site_url('egresos') ?>">Limpiar</a>
        </div>
    </form>

    <div class="tablewrap">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Categoría</th>
                    <th>Monto</th>
                    <th>Registró</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$expenses): ?>
                    <tr>
                        <td colspan="6" class="empty">No se encontraron egresos.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($expenses as $egreso): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($egreso['expense_date'])) ?></td>
                        <td><?= h($egreso['concept']) ?></td>
                        <td><?= h($egreso['category_name']) ?></td>
                        <td><?= money($egreso['amount']) ?></td>
                        <td><?= h($egreso['created_by_name']) ?></td>
                        <td>
                            <div class="inline">
                                <?php if ($archived): ?>
                                    <?= form_open('egresos/restaurar/' . $egreso['id']) ?>
                                        <button class="btn" type="submit">Restaurar</button>
                                    <?= form_close() ?>
                                <?php else: ?>
                                    <a class="btn" href="<?= site_url('egresos/editar/' . $egreso['id']) ?>">Editar</a>
                                    <?= form_open('egresos/anular/' . $egreso['id']) ?>
                                        <button class="btn danger" type="submit"
                                                data-confirm="¿Eliminar este egreso? Dejará de contar en contabilidad.">
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
