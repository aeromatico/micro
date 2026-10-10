<?php
$stColor = ['pending' => '#d97706', 'confirmed' => '#2563eb', 'in_service' => '#7c3aed', 'completed' => '#16a34a'];
$done = collect($steps)->where('done', true)->count();
?>
<div style="padding:15px">
<?php if ($steps && $done < count($steps)): ?>
    <div class="callout callout-info" style="margin-bottom:20px">
        <div class="header"><h3>Configuración inicial (<?= $done ?>/<?= count($steps) ?>)</h3></div>
        <div class="content">
            <ol style="margin:0;padding-left:20px">
            <?php foreach ($steps as $st): ?>
                <li style="margin:4px 0">
                    <?= $st['done'] ? '✅' : '⬜' ?>
                    <a href="<?= Backend::url($st['url']) ?>"><?= e($st['label']) ?></a>
                </li>
            <?php endforeach ?>
            </ol>
        </div>
    </div>
<?php endif ?>

<?php if ($publicUrl): ?>
    <p>Tu portal de reservas: <a href="<?= e($publicUrl) ?>" target="_blank"><strong><?= e($publicUrl) ?></strong></a></p>
<?php endif ?>

    <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px">
        <?php foreach ([['Hoy', $counts['today']], ['Por aprobar', $counts['pending']], ['Próx. 7 días', $counts['week']], ['No asistió (30 d)', $counts['no_shows']]] as [$label, $n]): ?>
        <div style="flex:1;min-width:150px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;padding:14px">
            <div style="font-size:12px;color:#6b7280;text-transform:uppercase"><?= e($label) ?></div>
            <div style="font-size:28px;font-weight:700"><?= (int) $n ?></div>
        </div>
        <?php endforeach ?>
    </div>

    <h4>Pendientes de aprobación</h4>
    <?php if ($pending->isEmpty()): ?>
        <p class="text-muted">No hay solicitudes pendientes.</p>
    <?php else: ?>
    <table class="table table-condensed">
        <?php foreach ($pending as $b): ?>
        <tr>
            <td style="width:150px"><?= e($b->starts_at->format('d/m H:i')) ?></td>
            <td><?= e($b->customer?->name) ?> — <?= e($b->service_name) ?></td>
            <td><?= e($b->worker_name) ?></td>
            <td style="width:90px"><a href="<?= Backend::url('aero/office/bookings/update/' . $b->id) ?>">Revisar</a></td>
        </tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>

    <h4 style="margin-top:24px">Agenda de hoy</h4>
    <?php if ($today->isEmpty()): ?>
        <p class="text-muted">Sin citas para hoy.</p>
    <?php else: ?>
    <table class="table table-condensed">
        <?php foreach ($today as $b): ?>
        <tr>
            <td style="width:120px"><strong><?= e($b->starts_at->format('H:i')) ?></strong>–<?= e($b->ends_at->format('H:i')) ?></td>
            <td><?= e($b->customer?->name) ?> — <?= e($b->service_name) ?></td>
            <td><?= e($b->worker_name) ?></td>
            <td style="width:110px"><span style="color:#fff;background:<?= $stColor[$b->status] ?? '#6b7280' ?>;padding:1px 8px;border-radius:10px;font-size:11px"><?= e($b->status_label) ?></span></td>
            <td style="width:70px"><a href="<?= Backend::url('aero/office/bookings/update/' . $b->id) ?>">Abrir</a></td>
        </tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>
</div>
