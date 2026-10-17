<?php
$opt = \Aero\Medical\Classes\Scheduling::options((int) $p->tenant_id);
?>
<?php if (!\Aero\Medical\Classes\Scheduling::available()): ?>
    <p class="text-muted">Agendar requiere Aero.Office (Reservas).</p>
<?php elseif (!$p->customer_id): ?>
    <p class="text-muted">Para agendar, vincule al paciente con un cliente de Reservas (persona o tutor) en «Datos del paciente».</p>
<?php elseif (!count($opt['branches']) || !count($opt['services'])): ?>
    <p class="text-muted">Aún no hay sucursales o servicios en Reservas.</p>
<?php else: ?>
<?= Form::open(['data-request' => 'onBookControl', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:8px;align-items:end']) ?>
    <div><label style="font-size:12px">Sucursal</label><select name="branch_id" class="form-control" required><?php foreach ($opt['branches'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Servicio</label><select name="service_id" class="form-control" required><?php foreach ($opt['services'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Profesional</label><select name="worker_id" class="form-control"><option value="">— Cualquiera disponible —</option><?php foreach ($opt['workers'] as $id => $n): ?><option value="<?= (int) $id ?>"><?= e($n) ?></option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Fecha y hora</label><input type="datetime-local" id="book-date" name="starts_at" class="form-control" required></div>
    <div style="grid-column:1/-1"><label style="font-size:12px">Motivo <span class="text-muted">(lo verá recepción en la reserva; breve y sin datos clínicos sensibles)</span></label>
        <input type="text" id="book-reason" name="reason" class="form-control" maxlength="500" placeholder="Control"></div>
    <div><button type="submit" class="btn btn-default">Agendar control</button></div>
<?= Form::close() ?>
<p class="text-muted" style="font-size:12px;margin:6px 0 0">Usa la disponibilidad de Reservas: si el horario no está libre, no se agenda.</p>
<?php endif ?>
