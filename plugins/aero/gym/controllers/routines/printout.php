<div style="max-width:720px;margin:20px auto;background:#fff;padding:24px">
    <h2 style="margin:0"><?= e($routine->name) ?></h2>
    <p><?= e($routine->member?->name ?? 'Plantilla') ?><?php if ($routine->goal): ?> · Objetivo: <?= e($routine->goal) ?><?php endif ?><?php if ($routine->instructor): ?> · Instructor: <?= e($routine->instructor->name) ?><?php endif ?></p>
    <?php foreach ($routine->items->groupBy(fn ($i) => $i->day_label ?: 'Sesión') as $day => $items): ?>
        <h4><?= e($day) ?></h4>
        <table class="table data"><thead><tr><th>Ejercicio</th><th>Series</th><th>Reps</th><th>Descanso</th><th>Notas</th></tr></thead><tbody>
        <?php foreach ($items as $i): ?>
            <tr><td><?= e($i->exercise) ?></td><td><?= e($i->sets) ?></td><td><?= e($i->reps) ?></td><td><?= $i->rest_seconds ? e($i->rest_seconds) . ' s' : '' ?></td><td><?= e($i->notes) ?></td></tr>
        <?php endforeach ?>
        </tbody></table>
    <?php endforeach ?>
    <?php if ($routine->notes): ?><p><em><?= e($routine->notes) ?></em></p><?php endif ?>
    <p class="text-center"><button class="btn btn-primary" onclick="window.print()">Imprimir</button></p>
</div>
