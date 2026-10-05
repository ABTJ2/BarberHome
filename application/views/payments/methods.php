<div class="heading">
    <div>
        <h1>Formas de pago</h1>
        <p>Efectivo, transferencia y métodos futuros.</p>
    </div>
</div>

<div class="grid g2">

    <!-- Métodos cargados. -->
    <section class="card">
        <div class="tablewrap">
            <table>
                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $metodo): ?>
                        <tr>
                            <td><?= h($metodo['name']) ?></td>
                            <td>
                                <span class="badge <?= $metodo['active'] ? 'green' : 'neutral' ?>">
                                    <?= $metodo['active'] ? 'Activo' : 'Inactivo' ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Alta de un método nuevo. -->
    <section class="card">
        <div class="cardhead">
            <div>
                <h3>Nueva forma de pago</h3>
                <p>Disponible para cobros futuros</p>
            </div>
        </div>

        <?= form_open('formas-pago/guardar') ?>

            <div class="field">
                <label>Nombre</label>
                <input class="input" name="name" required placeholder="Ej.: Débito">
            </div>

            <div class="form-actions">
                <button class="btn primary" type="submit">Agregar</button>
            </div>

        <?= form_close() ?>
    </section>

</div>
