<?php
$editing=$appointment && !empty($appointment['id']);
$client_id=$appointment['client_id'] ?? (int)$this->input->get('client_id');
$barber_id=$appointment['barber_id'] ?? '';
$date=$appointment['date'] ?? ($editing ? substr($appointment['start_at'],0,10) : date('Y-m-d'));
$time=$appointment['time'] ?? ($editing ? substr($appointment['start_at'],11,5) : ($walkin ? date('H:i') : date('H:i',strtotime('+1 hour'))));
$status=$appointment['status'] ?? ($walkin ? 'attended' : 'reserved');
?>
<div class="heading"><div><h1><?= $editing ? 'Editar turno' : ($walkin ? 'Atención sin turno previo' : 'Nuevo turno') ?></h1><p>Se validan horario, servicios y superposición al guardar.</p></div></div>
<?php if($errors): ?><div class="errors"><ul><?php foreach($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="card">
  <?= form_open($editing ? 'turnos/actualizar/'.$appointment['id'] : 'turnos/guardar') ?>
  <input type="hidden" name="walk_in" value="<?= $walkin ? 1 : 0 ?>">
  <div class="formgrid">
    <div class="field"><label class="required">Cliente</label><select name="client_id" required>
      <option value="">Seleccionar</option>
      <?php foreach($clients as $client): ?><option value="<?= (int)$client['id'] ?>" <?= $client_id==$client['id'] ? 'selected' : '' ?>><?= h($client['first_name'].' '.$client['last_name'].' · '.$client['phone']) ?></option><?php endforeach; ?>
    </select><a class="small" href="<?= site_url('clientes/nuevo') ?>">¿No figura? Crear cliente</a></div>
    <div class="field"><label class="required">Peluquero</label><select name="barber_id" required>
      <option value="">Seleccionar</option>
      <?php foreach($barbers as $barber): ?><option value="<?= (int)$barber['id'] ?>" <?= $barber_id==$barber['id'] ? 'selected' : '' ?>><?= h($barber['full_name']) ?></option><?php endforeach; ?>
    </select></div>
    <div class="field"><label class="required">Fecha</label><input class="input" type="date" name="date" value="<?= h($date) ?>" required></div>
    <div class="field"><label class="required">Hora</label><input class="input" type="time" name="time" value="<?= h($time) ?>" required></div>
  </div>
  <div class="divider"></div>
  <div class="cardhead"><div><h3>Servicios</h3><p>Se pueden combinar varios en la misma atención.</p></div></div>
  <div class="service-picker">
    <?php foreach($services as $service): ?>
      <label class="service-option"><input type="checkbox" name="service_ids[]" value="<?= (int)$service['id'] ?>"
        data-service-check data-price="<?= h($service['price']) ?>" data-minutes="<?= (int)$service['duration_minutes'] ?>"
        <?= in_array((int)$service['id'],$selected_services,TRUE) ? 'checked' : '' ?>>
        <span><strong><?= h($service['name']) ?></strong><small><?= money($service['price']) ?> · <?= (int)$service['duration_minutes'] ?> min</small></span>
      </label>
    <?php endforeach; ?>
  </div>
  <div class="note" style="margin-top:14px">Estimado: <strong data-service-total>$0</strong> · <strong data-service-minutes>0 min</strong>. Los importes se verifican otra vez en el servidor.</div>
  <div class="formgrid">
    <div class="field"><label>Estado</label><select name="status">
      <?php foreach(array('reserved'=>'Reservado','attended'=>'Atendido','cancelled'=>'Cancelado','no_show'=>'Ausente') as $code=>$label): ?>
        <option value="<?= $code ?>" <?= $status===$code ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select></div>
    <div class="field"><label>Observación</label><textarea name="notes" maxlength="500"><?= h($appointment['notes'] ?? '') ?></textarea></div>
  </div>
  <div class="form-actions"><a class="btn" href="<?= site_url('turnos') ?>">Cancelar</a><button class="btn primary" type="submit">Guardar turno</button></div>
  <?= form_close() ?>
</section>
