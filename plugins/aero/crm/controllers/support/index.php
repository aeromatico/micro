<div class="layout-row"><div class="padded-container">
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
    <div><h3 style="margin:0">Soporte de la plataforma</h3>
    <p class="text-muted" style="margin:2px 0 0">Tus consultas al equipo de la plataforma. Los tickets de tus propios clientes están en CRM → Tickets.</p></div>
    <a href="<?= Backend::url('aero/crm/support/create') ?>" class="btn btn-primary">Nuevo ticket</a>
</div>
<?php if ($tickets->isEmpty()): ?>
    <p class="text-muted">Todavía no abriste ningún ticket.</p>
<?php else: ?>
<table class="table data" style="background:#fff">
    <thead><tr><th>N°</th><th>Asunto</th><th>Departamento</th><th>Estado</th><th>Creado</th></tr></thead>
    <tbody>
    <?php $states = ['open' => 'Abierto', 'pending' => 'En espera', 'resolved' => 'Resuelto', 'closed' => 'Cerrado']; ?>
    <?php foreach ($tickets as $t): ?>
        <tr onclick="location='<?= Backend::url('aero/crm/support/view/' . $t->id) ?>'" style="cursor:pointer">
            <td><?= e($t->number) ?></td>
            <td><?= e($t->subject) ?></td>
            <td><?= e($t->department?->name) ?></td>
            <td><?= e($states[$t->status] ?? $t->status) ?></td>
            <td><?= $t->created_at->format('d/m/Y H:i') ?></td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
</div></div>
