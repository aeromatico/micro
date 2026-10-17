<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/imports') ?>">Importar historias anteriores</a></li></ul>
<?php Block::endPut() ?>
<div class="form-preview" style="max-width:820px">
    <p>Suba historias anteriores (PDF, imágenes, Word o texto). Cada archivo se guarda <strong>cifrado y conserva el original</strong>.
        El sistema sugiere a qué paciente corresponde cada uno (por nombre o documento) y <strong>usted lo confirma</strong>: hasta entonces el archivo no está en ninguna ficha.</p>
    <?= Form::open(['data-request' => 'onUpload', 'data-request-files' => true, 'enctype' => 'multipart/form-data']) ?>
        <div class="form-group"><label>Archivos (hasta <?= \Aero\Medical\Classes\ImportService::MAX_FILES ?> por lote, 20 MB cada uno)</label>
            <input type="file" name="files[]" multiple class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.txt"></div>
        <div class="form-group"><label>Nombre del lote (opcional)</label><input type="text" name="label" class="form-control" maxlength="255" placeholder="Ej.: historias del sistema anterior, 2025"></div>
        <button type="submit" class="btn btn-primary">Subir y sugerir pacientes</button>
    <?= Form::close() ?>
</div>

<h4 style="margin-top:26px">Lotes anteriores</h4>
<?php if (!count($batches)): ?><p class="text-muted">Aún no hay importaciones.</p><?php else: ?>
<table class="table data" style="max-width:820px">
    <thead><tr><th>Fecha</th><th>Lote</th><th>Archivos</th><th>Por revisar</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($batches as $b): $pend = $b->pendingCount(); ?>
        <tr><td><?= e($b->created_at->format('d/m/Y H:i')) ?></td><td><?= e($b->label ?: '—') ?></td><td><?= (int) $b->total ?></td>
            <td><?= $pend ? '<strong>' . $pend . '</strong>' : '0' ?></td>
            <td class="text-right"><a href="<?= Backend::url('aero/medical/imports/review/' . $b->id) ?>"><?= $pend ? 'Revisar' : 'Ver' ?></a></td></tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
