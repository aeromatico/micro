<form method="get" class="form-inline" style="margin-bottom:15px">
    <input type="text" name="q" value="<?= e($term) ?>" class="form-control" style="min-width:320px" placeholder="Nombre, apellido, RUDE o C.I. del estudiante" autofocus>
    <button class="btn btn-primary">Buscar</button>
</form>

<?php if ($term !== ''): ?>
    <?php if ($results->isEmpty()): ?><p class="text-muted">Sin resultados para «<?= e($term) ?>».</p>
    <?php else: ?>
    <table class="table data" style="max-width:800px">
        <?php foreach ($results as $s): ?>
        <tr>
            <td><a href="<?= Backend::url('aero/school/collect/student/' . $s->id) ?>"><strong><?= e($s->full_name) ?></strong></a></td>
            <td><?= e($s->course?->name) ?></td>
            <td><?= e($s->rude) ?></td>
            <td style="text-align:right"><a class="btn btn-sm btn-primary" href="<?= Backend::url('aero/school/collect/student/' . $s->id) ?>">Cobrar</a></td>
        </tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>
<?php elseif ($debtors): ?>
    <h4>Con mayor deuda vencida</h4>
    <table class="table data" style="max-width:800px">
        <?php foreach (array_slice($debtors, 0, 10) as $d): ?>
        <tr>
            <td><a href="<?= Backend::url('aero/school/collect/student/' . $d['student_id']) ?>"><?= e($d['student']) ?></a></td>
            <td><?= e($d['course']) ?></td>
            <td><?= (int) $d['charges'] ?> cuota(s) · <?= (int) $d['days'] ?> días</td>
            <td style="text-align:right"><strong class="text-danger"><?= number_format($d['due'], 2) ?></strong></td>
        </tr>
        <?php endforeach ?>
    </table>
<?php endif ?>
