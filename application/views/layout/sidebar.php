<?php $class=$this->router->class; $role=$current_user['role_code'] ?? 'recepcionista'; ?>
<aside class="sidebar">
  <div class="brand"><img src="<?= base_url('assets/img/logo.png') ?>" alt="Barber House"><div class="brand-copy"><strong>Barber House</strong><small>Gestión del local</small></div></div>
  <nav class="nav">
    <a class="<?= $class==='dashboard'?'active':'' ?>" href="<?= site_url('inicio') ?>"><span>⌂</span><span class="lab">Inicio</span></a>
    <a class="<?= $class==='appointments'?'active':'' ?>" href="<?= site_url('turnos') ?>"><span>◷</span><span class="lab">Agenda / Turnos</span></a>
    <a class="<?= $class==='clients'?'active':'' ?>" href="<?= site_url('clientes') ?>"><span>◎</span><span class="lab">Clientes</span></a>
    <a class="<?= $class==='payments'?'active':'' ?>" href="<?= site_url('cobros') ?>"><span>$</span><span class="lab">Cobros</span></a>
    <?php if($role==='encargado'): ?>
    <a class="<?= $class==='barbers'?'active':'' ?>" href="<?= site_url('peluqueros') ?>"><span>♙</span><span class="lab">Peluqueros</span></a>
    <a class="<?= $class==='services'?'active':'' ?>" href="<?= site_url('servicios') ?>"><span>✂</span><span class="lab">Servicios</span></a>
    <a class="<?= $class==='accounting' && $this->router->method==='expenses'?'active':'' ?>" href="<?= site_url('egresos') ?>"><span>−</span><span class="lab">Egresos</span></a>
    <a class="<?= $class==='accounting' && $this->router->method!=='expenses'?'active':'' ?>" href="<?= site_url('contabilidad') ?>"><span>▣</span><span class="lab">Contabilidad</span></a>
    <a class="<?= $class==='users'?'active':'' ?>" href="<?= site_url('usuarios') ?>"><span>⚙</span><span class="lab">Usuarios</span></a>
    <?php endif; ?>
  </nav>
  <div class="bottom"><button type="button" data-theme-toggle><span data-theme-icon>☾</span><span class="lab">Cambiar tema</span></button><?= form_open('logout') ?><button type="submit"><span>↪</span><span class="lab">Cerrar sesión</span></button><?= form_close() ?></div>
</aside>
<div class="main">
<header class="top"><div class="top-left"><button class="icon" type="button" data-side>☰</button><div><small class="muted">Barber House</small><strong style="display:block;font-size:13px"><?= h($page_title ?? '') ?></strong></div></div><div class="top-right"><button class="icon" type="button" data-theme-toggle><span data-theme-icon>☾</span></button><div class="user"><div class="ava"><?= h(strtoupper(substr($current_user['full_name'] ?? 'U',0,1))) ?></div><div><strong><?= h($current_user['full_name'] ?? '') ?></strong><small><?= h(role_label($role)) ?></small></div></div></div></header>
<main class="content">
<?= flash_message() ?>
