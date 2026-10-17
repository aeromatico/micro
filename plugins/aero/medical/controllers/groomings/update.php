<?php /** @var \Aero\Medical\Models\GroomingVisit $v */ ?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/groomings') ?>">Peluquería y spa</a></li><li><?= e($v->patient->name) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<style>.gm-photos{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:10px}.gm-photos figure{margin:0;width:160px}.gm-photos img{width:160px;height:120px;object-fit:cover;border:1px solid rgba(128,128,128,.4);border-radius:4px;background:#222}</style>
<h3 style="margin-top:0"><?= e($v->patient->name) ?> <small class="text-muted">· <?= e($v->service) ?> · <?= e($v->visited_on->format('d/m/Y')) ?></small></h3>

<?= Form::open(['style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:8px;align-items:end;max-width:900px;margin-bottom:20px']) ?>
    <div><label style="font-size:12px">Servicio</label><input type="text" name="service" class="form-control" value="<?= e($v->service) ?>" maxlength="255" required></div>
    <div><label style="font-size:12px">Fecha</label><input type="date" name="visited_on" class="form-control" value="<?= e($v->visited_on->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
    <div><label style="font-size:12px">Próxima cita</label><input type="date" name="next_visit_on" class="form-control" value="<?= e($v->next_visit_on?->toDateString()) ?>"></div>
    <div style="grid-column:1/-1"><label style="font-size:12px">Notas</label><textarea name="notes" class="form-control" rows="3" maxlength="2000"><?= e($v->notes) ?></textarea></div>
    <div><button type="button" class="btn btn-primary" data-request="onSave">Guardar</button>
        <button type="button" class="btn btn-danger" data-request="onDelete" data-request-confirm="¿Eliminar la visita y sus fotos?">Eliminar</button></div>
<?= Form::close() ?>

<?php foreach (['before' => ['Antes', $before], 'after' => ['Después', $after]] as $side => [$label, $photos]): ?>
    <h4><?= e($label) ?></h4>
    <div class="gm-photos">
        <?php foreach ($photos as $f): ?>
            <figure><a href="<?= Backend::url('aero/medical/files/download/' . $f->id) ?>" target="_blank"><img src="<?= Backend::url('aero/medical/files/download/' . $f->id) ?>" alt="<?= e($label) ?>" loading="lazy"></a>
                <button type="button" class="btn btn-danger btn-xs" style="margin-top:4px" data-request="onRemovePhoto" data-request-data="file_id: <?= (int) $f->id ?>" data-request-confirm="¿Quitar esta foto?">Quitar</button></figure>
        <?php endforeach ?>
        <?php if (!count($photos)): ?><p class="text-muted">Sin fotos.</p><?php endif ?>
    </div>
    <?= Form::open(['data-request' => 'onUploadPhoto', 'data-request-files' => true, 'enctype' => 'multipart/form-data', 'style' => 'display:flex;gap:8px;align-items:center;margin-bottom:18px']) ?>
        <input type="hidden" name="side" value="<?= e($side) ?>">
        <input type="file" name="photo_file" accept=".png,.jpg,.jpeg,.webp" class="form-control" style="max-width:340px" required>
        <button type="submit" class="btn btn-default">Subir foto (se guarda cifrada)</button>
    <?= Form::close() ?>
<?php endforeach ?>

<h4>Próxima cita</h4>
<?php if ($v->booking_id): ?><p>Agendada en Reservas (reserva #<?= (int) $v->booking_id ?>) para el <?= e($v->next_visit_on?->format('d/m/Y')) ?>.</p><?php endif ?>
<?php if (!$office): ?>
    <p class="text-muted">Para agendar desde aquí el tutor debe estar vinculado a un cliente de Reservas (y Aero.Office instalado). Mientras tanto, anote la fecha en «Próxima cita».</p>
<?php elseif (!count($opt['branches']) || !count($opt['services'])): ?>
    <p class="text-muted">Aún no hay sucursales o servicios en Reservas.</p>
<?php else: ?>
<?= Form::open(['data-request' => 'onBookNext', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;align-items:end;max-width:900px']) ?>
    <div><label style="font-size:12px">Sucursal</label><select name="branch_id" class="form-control" required><?php foreach ($opt['branches'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Servicio</label><select name="service_id" class="form-control" required><?php foreach ($opt['services'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Profesional</label><select name="worker_id" class="form-control"><option value="">— Cualquiera disponible —</option><?php foreach ($opt['workers'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Fecha y hora</label><input type="datetime-local" name="starts_at" class="form-control" required></div>
    <div style="grid-column:1/-1"><label style="font-size:12px">Motivo (lo ve recepción)</label><input type="text" name="reason" class="form-control" maxlength="500" value="<?= e('Peluquería y spa: ' . $v->service) ?>"></div>
    <div><button type="submit" class="btn btn-default">Agendar próxima cita</button></div>
<?= Form::close() ?>
<?php endif ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
