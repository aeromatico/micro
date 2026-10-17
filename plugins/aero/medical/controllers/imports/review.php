<?php /** @var \Aero\Medical\Models\ImportBatch $batch */ ?>
<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/imports') ?>">Importar historias anteriores</a></li><li><?= e($batch->label ?: 'Lote ' . $batch->id) ?></li></ul>
<?php Block::endPut() ?>
<p class="text-muted">Confirme a qué paciente pertenece cada archivo. Los sugeridos vienen preseleccionados, pero <strong>nada se asocia hasta que usted lo confirma</strong>.</p>
<?php if (count($patients) >= 500): ?><p class="flash-message static warning">Solo se listan los primeros 500 pacientes.</p><?php endif ?>
<table class="table data">
    <thead><tr><th>Archivo</th><th>Sugerencia</th><th>Paciente</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($files as $f): $open = in_array($f->match_status, ['pending', 'suggested'], true); ?>
        <tr>
            <td><?php if ($canRead): ?><a href="<?= Backend::url('aero/medical/files/download/' . $f->id) ?>" target="_blank"><?= e($f->original_name) ?></a><?php else: ?><?= e($f->original_name) ?><?php endif ?>
                <div class="text-muted" style="font-size:11px"><?= e(number_format($f->size / 1024, 0)) ?> KB</div></td>
            <td><?= $f->suggestion_reason ? e($f->suggestion_reason) : '<span class="text-muted">—</span>' ?></td>
            <?php if ($open): ?>
            <td><?= Form::open(['data-request' => 'onConfirm', 'style' => 'display:flex;gap:6px', 'id' => 'f' . $f->id]) ?>
                <input type="hidden" name="file_id" value="<?= (int) $f->id ?>">
                <select name="patient_id" class="form-control" required>
                    <option value="">— Elegir paciente —</option>
                    <?php foreach ($patients as $p): ?>
                        <option value="<?= (int) $p->id ?>" <?= (int) $f->suggested_patient_id === (int) $p->id ? 'selected' : '' ?>><?= e($p->name) ?><?= $p->document ? ' · ' . e($p->document) : '' ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm">Confirmar</button>
                <?= Form::close() ?></td>
            <td class="text-right"><button type="button" class="btn btn-default btn-sm" data-request="onDiscard" data-request-data="file_id: <?= (int) $f->id ?>" data-request-confirm="¿Descartar este archivo?">Descartar</button></td>
            <?php else: ?>
            <td colspan="2"><?php $pp = $f->patient; ?><?= $f->match_status === 'confirmed' && $pp ? 'Asociado a <a href="' . Backend::url('aero/medical/patients/update/' . $pp->id) . '">' . e($pp->name) . '</a>' : ($f->trashed() ? 'Descartado' : '—') ?></td>
            <?php endif ?>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
