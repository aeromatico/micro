<?php
use Aero\Medical\Models\Analyte;
use Aero\Medical\Models\MedicalFile;

Analyte::ensureDefaults((int) $p->tenant_id);
$analytes = Analyte::forTenant((int) $p->tenant_id)->where('is_active', true)->orderBy('sort')->orderBy('name')->get();
$files = MedicalFile::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->whereIn('kind', MedicalFile::CLINICAL_KINDS)->orderByDesc('taken_on')->orderByDesc('id')->limit(50)->get();
$notes = \Aero\Medical\Models\Consultation::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('consulted_at')->limit(30)->get();
?>
<?php if (!count($files)): ?><p class="text-muted" style="font-size:13px">Sin archivos.</p><?php else: ?>
<table class="table table-condensed" style="font-size:12.5px">
    <?php foreach ($files as $f): ?>
    <tr>
        <td style="width:90px"><?= e(($f->taken_on ?? $f->created_at)->format('d/m/Y')) ?></td>
        <td><span class="mf-badge"><?= e($f->kind_label) ?></span></td>
        <td><a href="<?= Backend::url('aero/medical/files/download/' . $f->id) ?>" target="_blank"><?= e($f->display_name) ?></a>
            <small class="text-muted"><?= e(number_format($f->size / 1024, 0)) ?> KB</small>
            <?= $f->import_id ? '<small class="text-muted">· importado</small>' : '' ?></td>
        <td class="text-right"><a class="btn btn-default btn-xs" href="<?= Backend::url('aero/medical/files/download/' . $f->id . '?descargar=1') ?>">Descargar</a>
            <button type="button" class="btn btn-danger btn-xs" data-request="onDeleteFile" data-request-data="file_id: <?= (int) $f->id ?>" data-request-confirm="¿Eliminar este archivo? Los resultados numéricos que se anotaron con él se conservan.">Quitar</button></td>
    </tr>
    <?php endforeach ?>
</table>
<?php endif ?>

<?= Form::open(['data-request' => 'onUploadStudy', 'data-request-files' => true, 'enctype' => 'multipart/form-data', 'class' => 'mc-reset', 'style' => 'margin-top:8px']) ?>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:8px;align-items:end">
        <div><label style="font-size:12px">Archivo (PDF, imagen, Word)</label><input type="file" name="study_file" class="form-control" accept=".pdf,.png,.jpg,.jpeg,.webp,.doc,.docx,.txt" required></div>
        <div><label style="font-size:12px">Tipo</label>
            <select name="kind" class="form-control"><option value="lab">Laboratorio</option><option value="imaging">Imagen diagnóstica</option><option value="history">Historia anterior</option><option value="other">Otro</option></select></div>
        <div><label style="font-size:12px">Título</label><input type="text" name="title" class="form-control" maxlength="255"></div>
        <div><label style="font-size:12px">Fecha del estudio</label><input type="date" name="taken_on" class="form-control" value="<?= e(now()->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
        <div><label style="font-size:12px">Ligar a una nota (opcional)</label>
            <select name="consultation_id" class="form-control"><option value="">— Ninguna —</option>
                <?php foreach ($notes as $n): ?><option value="<?= (int) $n->id ?>"><?= e($n->consulted_at->format('d/m/Y')) ?> · <?= e($n->kind_label) ?></option><?php endforeach ?></select></div>
    </div>
    <details style="margin-top:8px"><summary style="font-weight:normal;font-size:13px">Resultados numéricos del estudio (opcional, alimentan la hoja de seguimiento)</summary>
        <?php for ($i = 0; $i < 3; $i++): ?>
        <div style="display:grid;grid-template-columns:2fr 1fr 1fr;gap:8px;margin-top:6px">
            <select name="results[<?= $i ?>][analyte_id]" class="form-control"><option value="">— Analito —</option>
                <?php foreach ($analytes as $a): ?><option value="<?= (int) $a->id ?>"><?= e($a->name) ?><?= $a->unit ? ' (' . e($a->unit) . ')' : '' ?></option><?php endforeach ?></select>
            <input type="text" inputmode="decimal" name="results[<?= $i ?>][value]" class="form-control" placeholder="Valor">
            <input type="text" name="results[<?= $i ?>][unit]" class="form-control" placeholder="Unidad" maxlength="32">
        </div>
        <?php endfor ?>
    </details>
    <button type="submit" class="btn btn-default" style="margin-top:8px">Subir (se guarda cifrado)</button>
<?= Form::close() ?>
