<div class="heading"><div><h1>Peluqueros</h1><p>Disponibilidad, servicios y porcentajes.</p></div>
  <a class="btn primary" href="<?= site_url('peluqueros/nuevo') ?>">+ Agregar peluquero</a></div>
<section class="card">
  <form class="toolbar" method="get" action="<?= site_url('peluqueros') ?>">
    <div class="filters"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Nombre o teléfono">
      <label class="check"><input type="checkbox" name="archived" value="1" <?= $archived?'checked':'' ?>> Eliminados</label></div>
    <div class="inline"><button class="btn" type="submit">Buscar</button><a class="btn" href="<?= site_url('peluqueros') ?>">Limpiar</a></div>
  </form>
  <div class="tablewrap"><table><thead><tr><th>Peluquero</th><th>Teléfono</th><th>Porcentaje</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php if(!$barbers): ?><tr><td colspan="5" class="empty">No se encontraron peluqueros.</td></tr><?php endif; ?>
    <?php foreach($barbers as $barber): ?><tr>
      <td><strong><?= h($barber['full_name']) ?></strong></td><td><?= h($barber['phone'] ?: '—') ?></td><td><?= h($barber['commission_percent']) ?>%</td>
      <td><span class="badge <?= $barber['active']?'green':'neutral' ?>"><?= $barber['active']?'Activo':'Inactivo' ?></span></td>
      <td><div class="inline">
        <?php if($archived): ?>
          <?= form_open('peluqueros/restaurar/'.$barber['id']) ?><button class="btn" type="submit">Restaurar</button><?= form_close() ?>
        <?php else: ?>
          <a class="btn" href="<?= site_url('peluqueros/editar/'.$barber['id']) ?>">Editar</a>
          <a class="btn" href="<?= site_url('peluqueros/reasignar/'.$barber['id']) ?>">Reasignar turnos</a>
          <a class="btn" href="<?= site_url('turnos?date=&barber_id='.$barber['id']) ?>">Agenda</a>
          <?= form_open('peluqueros/eliminar/'.$barber['id']) ?><button class="btn danger" type="submit" data-confirm="¿Eliminar a este peluquero? Los turnos históricos se conservarán.">Eliminar</button><?= form_close() ?>
        <?php endif; ?>
      </div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
