<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/finance/entries') ?>">Libro diario</a></li><li>Asiento #<?= $entry->number ?></li></ul>
<?php Block::endPut() ?>
<h4>Asiento #<?= $entry->number ?> · <?= $entry->date->format('d/m/Y') ?>
    <?php if ($entry->status === 'void'): ?><span class="label label-danger">anulado</span><?php endif ?>
    <?php if ($entry->reversal_of_id): ?><span class="label label-default">anulación</span><?php endif ?></h4>
<p><?= e($entry->description) ?></p>
<table class="table data">
    <thead><tr><th>Cuenta</th><th>Detalle</th><th style="text-align:right">Debe</th><th style="text-align:right">Haber</th></tr></thead>
    <tbody>
    <?php foreach ($entry->lines as $l): ?>
        <tr><td><?= e($l->account?->label) ?></td><td><?= e($l->memo) ?></td>
            <td style="text-align:right"><?= $l->debit > 0 ? number_format($l->debit, 2) : '' ?></td>
            <td style="text-align:right"><?= $l->credit > 0 ? number_format($l->credit, 2) : '' ?></td></tr>
    <?php endforeach ?>
    </tbody>
    <tfoot><tr><th colspan="2">Total</th><th style="text-align:right"><?= number_format($entry->lines->sum('debit'), 2) ?></th><th style="text-align:right"><?= number_format($entry->lines->sum('credit'), 2) ?></th></tr></tfoot>
</table>
<a href="<?= Backend::url('aero/finance/entries') ?>" class="btn btn-default">Volver</a>
