<?php $items = $formModel->exists ? $formModel->interactions()->orderByDesc('id')->limit(100)->get() : collect(); ?>
<?php if ($items->isEmpty()): ?>
    <p class="text-muted">Sin movimientos todavía.</p>
<?php else: ?>
<table class="table data">
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Detalle</th></tr></thead>
    <tbody>
    <?php foreach ($items as $i): ?>
        <tr><td style="white-space:nowrap"><?= e($i->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($i->type_name) ?></td><td><?= e($i->body) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
