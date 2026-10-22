<?php $events = $formModel->exists ? $formModel->events()->orderByDesc('id')->limit(100)->get() : collect(); ?>
<?php if ($events->isEmpty()): ?>
    <p class="text-muted">Sin movimientos todavía.</p>
<?php else: ?>
<table class="table data">
    <thead><tr><th>Fecha</th><th>Evento</th></tr></thead>
    <tbody>
    <?php foreach ($events as $e): ?>
        <tr><td style="white-space:nowrap"><?= e($e->created_at?->format('d/m/Y H:i')) ?></td><td><?= e($e->summary) ?></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
