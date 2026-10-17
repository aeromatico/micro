<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/today') ?>">Agenda de hoy</a></li></ul>
<?php Block::endPut() ?>
<?php if (!$office): ?>
    <p class="flash-message static warning">La agenda usa Aero.Office (Reservas), que no está instalado. Puede abrir pacientes y escribir notas desde Pacientes.</p>
<?php elseif (!$rows): ?>
    <p class="text-muted" style="padding:20px">No hay reservas para hoy.</p>
<?php else: ?>
<table class="table data">
    <thead><tr><th>Hora</th><th>Cliente</th><th>Paciente</th><th>Servicio</th><th>Profesional</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): $b = $row['booking']; ?>
        <tr>
            <td><strong><?= e($b->starts_at->format('H:i')) ?></strong></td>
            <td><?= e($b->customer?->name) ?></td>
            <td><?php
                if ($row['note']) { echo e($row['note']->patient?->name); }
                elseif (count($row['patients']) === 1) { echo e($row['patients'][0]->name); }
                elseif (count($row['patients']) > 1) { echo '<span class="text-muted">' . count($row['patients']) . ' pacientes: elegir</span>'; }
                else { echo '<span class="text-muted">—</span>'; } ?></td>
            <td><?= e($b->service_name) ?></td>
            <td><?= e($b->worker?->name ?: $b->worker_name) ?></td>
            <td><?= e($b->status_label) ?></td>
            <td class="text-right"><?= $this->makePartial('row_actions', ['row' => $row]) ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
