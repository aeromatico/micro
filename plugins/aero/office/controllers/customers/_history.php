<?php
$upcoming = $c->bookings()->where('starts_at', '>=', now())->whereIn('status', ['pending', 'confirmed'])->orderBy('starts_at')->get();
$past = $c->bookings()->where(fn ($q) => $q->where('starts_at', '<', now())->orWhereNotIn('status', ['pending', 'confirmed']))
    ->orderByDesc('starts_at')->limit(30)->get();
?>
<div style="margin-bottom:20px">
    <p>
        <?php if ($c->user_id): ?>
            <span class="label label-success">Con cuenta de cliente</span>
        <?php else: ?>
            <button class="btn btn-default btn-sm" data-request="onCreateUser" data-request-confirm="¿Crear/vincular el usuario con el correo del cliente?">Crear acceso de cliente</button>
        <?php endif ?>
    </p>
    <h4>Próximas reservas</h4>
    <?php if ($upcoming->isEmpty()): ?><p class="text-muted">Ninguna.</p><?php else: ?>
    <table class="table table-condensed">
        <?php foreach ($upcoming as $b): ?>
        <tr><td style="width:140px"><?= e($b->starts_at->format('d/m/Y H:i')) ?></td><td><?= e($b->service_name) ?> · <?= e($b->worker_name) ?></td>
            <td><?= e($b->status_label) ?></td><td><a href="<?= Backend::url('aero/office/bookings/update/' . $b->id) ?>">Abrir</a></td></tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>
    <h4>Historial de servicios</h4>
    <?php if ($past->isEmpty()): ?><p class="text-muted">Sin historial.</p><?php else: ?>
    <table class="table table-condensed">
        <?php foreach ($past as $b): ?>
        <tr><td style="width:140px"><?= e($b->starts_at->format('d/m/Y H:i')) ?></td><td><?= e($b->service_name) ?> · <?= e($b->worker_name) ?></td>
            <td><?= e($b->status_label) ?></td><td><a href="<?= Backend::url('aero/office/bookings/update/' . $b->id) ?>">Abrir</a></td></tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>
</div>
