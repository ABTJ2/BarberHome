<div class="heading"><div><h1>Agenda / Turnos</h1><p>Buscar y administrar turnos.</p></div>
  <div class="inline"><a class="btn" href="<?= site_url('turnos/sin-reserva') ?>">Atención sin turno</a>
    <a class="btn primary" href="<?= site_url('turnos/nuevo') ?>">+ Agregar turno</a></div></div>
<section class="card">
  <form class="toolbar" method="get" action="<?= site_url('turnos') ?>">
    <div class="filters">
      <input class="input w-auto" type="date" name="date" value="<?= h($date) ?>" title="Dejar vacío para buscar en todas las fechas">
      <input class="input" name="q" value="<?= h($q) ?>" placeholder="Cliente, celular o peluquero">
      <select name="barber_id"><option value="">Todos los peluqueros</option>
        <?php foreach($barbers as $barber): ?><option value="<?= (int)$barber['id'] ?>" <?= $barber_id==$barber['id']?'selected':'' ?>><?= h($barber['full_name']) ?></option><?php endforeach; ?>
      </select>
      <select name="status"><option value="">Todos los estados</option>
        <?php foreach(array('reserved'=>'Reservado','attended'=>'Atendido','cancelled'=>'Cancelado','no_show'=>'Ausente') as $code=>$label): ?><option value="<?= $code ?>" <?= $status===$code?'selected':'' ?>><?= $label ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="inline"><button class="btn" type="submit">Buscar</button><a class="btn" href="<?= site_url('turnos') ?>">Limpiar</a></div>
  </form>
  <p class="small muted">Dejá la fecha vacía para buscar en toda la agenda.</p>
  <div class="tablewrap"><table><thead><tr><th>Fecha y hora</th><th>Cliente</th><th>Peluquero</th><th>Servicios</th><th>Duración</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php if(!$appointments): ?><tr><td colspan="7" class="empty">No se encontraron turnos.</td></tr><?php endif; ?>
    <?php foreach($appointments as $appointment): ?><tr>
      <td><strong><?= date('d/m/Y H:i',strtotime($appointment['start_at'])) ?></strong></td>
      <td><?= h($appointment['client_name']) ?> <?= $appointment['walk_in']?'<span class="badge gold">Sin reserva</span>':'' ?></td>
      <td><?= h($appointment['barber_name']) ?></td><td><?= h($appointment['services'] ?: '—') ?></td>
      <td><?= (int)$appointment['service_minutes'] ?> min</td>
      <td><span class="badge <?= status_class($appointment['status']) ?>"><?= h(status_label($appointment['status'])) ?></span></td>
      <td><div class="inline"><a class="btn" href="<?= site_url('turnos/ver/'.$appointment['id']) ?>">Ver</a>
        <?php if(!$appointment['has_payment']): ?><a class="btn" href="<?= site_url('turnos/editar/'.$appointment['id']) ?>">Editar</a>
          <?php if($appointment['status']!=='cancelled'): ?><?= form_open('turnos/eliminar/'.$appointment['id']) ?><button class="btn danger" type="submit" data-confirm="¿Eliminar este turno? Quedará cancelado y se conservará en el historial.">Eliminar</button><?= form_close() ?><?php endif; ?>
        <?php endif; ?>
      </div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
