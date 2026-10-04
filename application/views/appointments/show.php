<?php
$total=0;
foreach($appointment['services'] as $service) $total+=(float)$service['price_snapshot'];
$paid=!empty($appointment['payment']);
?>
<div class="heading"><div><h1>Turno #<?= (int)$appointment['id'] ?></h1><p><?= date('d/m/Y H:i',strtotime($appointment['start_at'])) ?> · <?= h($appointment['client_name']) ?></p></div>
  <div class="inline">
    <?php if(!$paid): ?><a class="btn" href="<?= site_url('turnos/editar/'.$appointment['id']) ?>">Editar</a><?php endif; ?>
    <?php if(!$paid && !in_array($appointment['status'],array('cancelled','no_show'),TRUE)): ?>
      <a class="btn primary" href="<?= site_url('cobros/nuevo/'.$appointment['id']) ?>">Marcar atendido / cobrar</a>
    <?php endif; ?>
    <?php if(!$paid && $appointment['status']!=='cancelled'): ?>
      <?= form_open('turnos/eliminar/'.$appointment['id']) ?><button class="btn danger" type="submit" data-confirm="¿Eliminar este turno? Se marcará como cancelado.">Eliminar</button><?= form_close() ?>
    <?php endif; ?>
  </div>
</div>
<div class="grid g2">
  <section class="card">
    <div class="row"><span>Cliente</span><strong><?= h($appointment['client_name']) ?></strong></div>
    <div class="row"><span>Peluquero</span><strong><?= h($appointment['barber_name']) ?></strong></div>
    <div class="row"><span>Fecha y hora</span><strong><?= date('d/m/Y H:i',strtotime($appointment['start_at'])) ?></strong></div>
    <div class="row"><span>Fin estimado</span><strong><?= date('H:i',strtotime($appointment['end_at'])) ?></strong></div>
    <div class="row"><span>Estado</span><span class="badge <?= status_class($appointment['status']) ?>"><?= h(status_label($appointment['status'])) ?></span></div>
    <div class="row"><span>Observación</span><strong><?= h($appointment['notes'] ?: '—') ?></strong></div>
    <?php if(!$paid): ?>
      <?= form_open('turnos/estado/'.$appointment['id']) ?>
        <div class="field" style="margin-top:16px"><label>Cambiar estado</label><select name="status">
          <?php foreach(array('reserved'=>'Reservado','attended'=>'Atendido','cancelled'=>'Cancelado','no_show'=>'Ausente') as $code=>$label): ?>
            <option value="<?= $code ?>" <?= $appointment['status']===$code ? 'selected' : '' ?>><?= $label ?></option>
          <?php endforeach; ?>
        </select></div>
        <div class="form-actions"><button class="btn" type="submit">Guardar estado</button></div>
      <?= form_close() ?>
    <?php endif; ?>
  </section>
  <section class="card">
    <div class="cardhead"><div><h3>Servicios</h3><p>Precio y duración registrados al crear el turno</p></div></div>
    <?php foreach($appointment['services'] as $service): ?>
      <div class="row"><span><?= h($service['name']) ?> · <?= (int)$service['duration_snapshot'] ?> min</span><strong><?= money($service['price_snapshot']) ?></strong></div>
    <?php endforeach; ?>
    <div class="row"><span>Total de lista</span><strong><?= money($total) ?></strong></div>
    <?php if($paid): ?>
      <div class="divider"></div>
      <div class="row"><span>Cobro</span><strong><?= $appointment['payment']['voided_at']?'Anulado':money($appointment['payment']['amount']) ?></strong></div>
      <div class="row"><span>Forma de pago</span><strong><?= h($appointment['payment']['payment_method']) ?></strong></div>
    <?php endif; ?>
  </section>
</div>
