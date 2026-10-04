<div class="heading"><div><h1>Servicios</h1><p>Precios y duración aproximada.</p></div>
  <a class="btn primary" href="<?= site_url('servicios/nuevo') ?>">+ Agregar servicio</a></div>
<section class="card">
  <form class="toolbar" method="get" action="<?= site_url('servicios') ?>">
    <div class="filters"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Nombre del servicio">
      <label class="check"><input type="checkbox" name="archived" value="1" <?= $archived?'checked':'' ?>> Eliminados</label></div>
    <div class="inline"><button class="btn" type="submit">Buscar</button><a class="btn" href="<?= site_url('servicios') ?>">Limpiar</a></div>
  </form>
  <div class="tablewrap"><table><thead><tr><th>Servicio</th><th>Precio</th><th>Duración</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php if(!$services): ?><tr><td colspan="5" class="empty">No se encontraron servicios.</td></tr><?php endif; ?>
    <?php foreach($services as $service): ?><tr>
      <td><strong><?= h($service['name']) ?></strong></td><td><?= money($service['price']) ?></td><td><?= (int)$service['duration_minutes'] ?> min</td>
      <td><span class="badge <?= $service['active']?'green':'neutral' ?>"><?= $service['active']?'Activo':'Inactivo' ?></span></td>
      <td><div class="inline">
        <?php if($archived): ?>
          <?= form_open('servicios/restaurar/'.$service['id']) ?><button class="btn" type="submit">Restaurar</button><?= form_close() ?>
        <?php else: ?>
          <a class="btn" href="<?= site_url('servicios/editar/'.$service['id']) ?>">Editar</a>
          <?= form_open('servicios/eliminar/'.$service['id']) ?><button class="btn danger" type="submit" data-confirm="¿Eliminar este servicio? Los turnos anteriores conservarán su precio.">Eliminar</button><?= form_close() ?>
        <?php endif; ?>
      </div></td>
    </tr><?php endforeach; ?>
  </tbody></table></div>
</section>
