<?php $manager=$current_user['role_code']==='encargado'; ?>
<div class="heading"><div><h1>Cobros</h1><p>Importes reales cobrados y formas de pago.</p></div>
  <div class="inline"><a class="btn primary" href="<?= site_url('turnos') ?>">+ Registrar cobro</a>
    <?php if($manager): ?><a class="btn" href="<?= site_url('formas-pago') ?>">Formas de pago</a><?php endif; ?></div></div>
<section class="card">
  <form class="toolbar" method="get" action="<?= site_url('cobros') ?>">
    <div class="filters"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Cliente, peluquero o forma de pago">
      <input class="input w-auto" type="date" name="from" value="<?= h($from) ?>" title="Desde">
      <input class="input w-auto" type="date" name="to" value="<?= h($to) ?>" title="Hasta">
      <?php if($manager): ?><label class="check"><input type="checkbox" name="archived" value="1" <?= $archived?'checked':'' ?>> Anulados</label><?php endif; ?>
    </div>
    <div class="inline"><button class="btn" type="submit">Buscar</button><a class="btn" href="<?= site_url('cobros') ?>">Limpiar</a></div>
  </form>
  <div class="tablewrap"><table><thead><tr><th>Fecha</th><th>Cliente</th><th>Peluquero</th><th>Forma</th><th>Importe</th><th>Acciones</th></tr></thead><tbody>
    <?php if(!$payments): ?><tr><td colspan="6" class="empty">No se encontraron cobros.</td></tr><?php endif; ?>
    <?php foreach($payments as $payment): ?><tr>
      <td><?= date('d/m/Y H:i',strtotime($payment['paid_at'])) ?></td><td><?= h($payment['client_name']) ?></td>
      <td><?= h($payment['barber_name']) ?></td><td><?= h($payment['payment_method']) ?></td>
      <td><strong><?= money($payment['amount']) ?></strong></td><td><div class="inline">
        <a class="btn" href="<?= site_url('turnos/ver/'.$payment['appointment_id']) ?>">Ver turno</a>
        <?php if($manager): ?>
          <?php if($archived): ?>
            <?= form_open('cobros/restaurar/'.$payment['id']) ?><button class="btn" type="submit">Restaurar</button><?= form_close() ?>
          <?php else: ?>
            <a class="btn" href="<?= site_url('cobros/editar/'.$payment['id']) ?>">Editar</a>
            <?= form_open('cobros/eliminar/'.$payment['id']) ?><button class="btn danger" type="submit" data-confirm="¿Anular este cobro? Dejará de contar como ingreso y comisión.">Eliminar</button><?= form_close() ?>
          <?php endif; ?>
        <?php endif; ?>
      </div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
