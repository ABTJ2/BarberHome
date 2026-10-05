<!doctype html>
<html lang="es" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Barber House · Iniciar sesión</title>
    <link rel="stylesheet" href="<?= asset_url('assets/css/app.css') ?>">
</head>
<body>

<button class="icon themefixed" type="button" data-theme-toggle><span data-theme-icon>☾</span></button>

<div class="login">

    <!-- Marca: logo y nombre del local. -->
    <section class="loginbrand">
        <div>
            <img src="<?= base_url('assets/img/logo.png') ?>" alt="Barber House">
            <h1>Barber House</h1>
            <p>Turnos, clientes y control del local.</p>
        </div>
    </section>

    <!-- Formulario de acceso. -->
    <section class="loginpanel">
        <div class="logincard">
            <h2>Iniciar sesión</h2>
            <p>Acceso para personal autorizado.</p>

            <?php if (!empty($error)): ?>
                <div class="login-error"><?= h($error) ?></div>
            <?php endif; ?>

            <?= form_open('login') ?>

                <div class="field">
                    <label>Usuario</label>
                    <input class="input" name="username" autocomplete="username" required>
                </div>

                <div class="field">
                    <label>Contraseña</label>
                    <input class="input" type="password" name="password" autocomplete="current-password" required>
                </div>

                <button class="btn primary" type="submit">Ingresar</button>

            <?= form_close() ?>
        </div>
    </section>

</div>

<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
