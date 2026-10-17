<?php
$sheet = \Aero\Medical\Classes\FollowUpSheet::build($p);
$cols = $sheet['columns'];
?>
<?php if (!$cols || !$sheet['rows'] && !array_filter(array_column($cols, 'note'))): ?>
    <p class="text-muted">Aún no hay visitas ni resultados. La hoja se llena al firmar notas y al anotar laboratorio.</p>
<?php else: ?>
<div class="mf-sheet">
<table>
    <thead><tr>
        <th class="rowh"></th>
        <?php foreach ($cols as $col): ?>
            <th style="text-align:center;min-width:110px">
                <?php if ($col['note']): ?>
                    <a href="<?= Backend::url('aero/medical/consultations/update/' . $col['note']->id) ?>" title="Abrir la nota <?= $col['signed'] ? 'tal como se firmó' : '(borrador)' ?>"><?= e($col['date']->format('d/m/Y')) ?></a>
                    <div class="text-muted" style="font-weight:normal;font-size:11px"><?= e($col['label']) ?></div>
                <?php else: ?>
                    <?= e($col['date']->format('d/m/Y')) ?><div class="text-muted" style="font-weight:normal;font-size:11px">Laboratorio</div>
                <?php endif ?>
            </th>
        <?php endforeach ?>
    </tr></thead>
    <tbody>
    <?php $group = null; foreach ($sheet['rows'] as $row): ?>
        <?php if ($row['group'] !== $group): $group = $row['group']; ?><tr class="grp"><td colspan="<?= count($cols) + 1 ?>"><?= e($group) ?></td></tr><?php endif ?>
        <tr>
            <th class="rowh"><?= e($row['label']) ?><?= $row['unit'] ? ' <span class="text-muted" style="font-weight:normal">' . e($row['unit']) . '</span>' : '' ?></th>
            <?php foreach ($cols as $col): $cell = $row['cells'][$col['key']] ?? null; ?>
                <td style="text-align:center" class="<?= $cell && $cell['flag'] ? 'mf-out' : '' ?>" <?= $cell && $cell['flag'] ? 'title="' . ($cell['flag'] === 'high' ? 'Por encima del rango' : 'Por debajo del rango') . '"' : '' ?>>
                    <?= $cell ? e($cell['text']) . ($cell['flag'] ? ($cell['flag'] === 'high' ? ' ▲' : ' ▼') : '') : '' ?></td>
            <?php endforeach ?>
        </tr>
    <?php endforeach ?>
        <tr class="grp"><td colspan="<?= count($cols) + 1 ?>">Estado de la nota</td></tr>
        <tr><th class="rowh">Firmada</th>
            <?php foreach ($cols as $col): ?>
                <td style="text-align:center" class="<?= $col['signed'] === false ? 'mf-draft' : '' ?>"><?= $col['signed'] === null ? '' : ($col['signed'] ? '✓ firmada' : 'borrador') ?></td>
            <?php endforeach ?>
        </tr>
    </tbody>
</table>
</div>
<?php endif ?>
