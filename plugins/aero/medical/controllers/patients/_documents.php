<?php
use Aero\Medical\Models\DocumentTemplate;
use Aero\Medical\Models\MedicalDocument;

$docs = MedicalDocument::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('id')->limit(30)->get();
$templates = DocumentTemplate::forTenant((int) $p->tenant_id)->where('is_active', true)->orderBy('name')->get();
$notes = \Aero\Medical\Models\Consultation::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->where('status', 'signed')->orderByDesc('consulted_at')->limit(20)->get();
?>
<?php if (!count($docs)): ?><p class="text-muted" style="font-size:13px">Sin documentos.</p><?php else: ?>
<table class="table table-condensed" style="font-size:12.5px">
    <?php foreach ($docs as $d): ?>
    <tr><td style="width:90px"><?= e(($d->issued_at ?? $d->created_at)->format('d/m/Y')) ?></td><td><span class="mf-badge"><?= e($d->kind_label) ?></span></td>
        <td><?= e($d->title) ?></td><td><?= $d->isIssued() ? 'Emitido' : '<em>Borrador</em>' ?></td>
        <td class="text-right"><a href="<?= Backend::url('aero/medical/documents/update/' . $d->id) ?>">abrir</a></td></tr>
    <?php endforeach ?>
</table>
<?php endif ?>
<?php if (!count($templates)): ?>
    <p class="text-muted" style="font-size:12px">Sin formatos: créelos en «Formatos de documentos» (certificados, constancias, informes, consentimientos).</p>
<?php else: ?>
<?= Form::open(['data-request' => 'onGenerateDocument', 'style' => 'display:flex;gap:8px;flex-wrap:wrap;align-items:end;margin-top:6px']) ?>
    <div><label style="font-size:12px">Formato</label><select name="template_id" class="form-control" required><?php foreach ($templates as $t): ?><option value="<?= (int) $t->id ?>"><?= e($t->name) ?> (<?= e($t->kind_label ?? \Aero\Medical\Models\DocumentTemplate::KINDS[$t->kind] ?? $t->kind) ?>)</option><?php endforeach ?></select></div>
    <div><label style="font-size:12px">Tomar diagnóstico y motivo de la nota</label>
        <select name="consultation_id" class="form-control"><option value="">— La última firmada —</option>
            <?php foreach ($notes as $n): ?><option value="<?= (int) $n->id ?>"><?= e($n->consulted_at->format('d/m/Y')) ?> · <?= e($n->kind_label) ?></option><?php endforeach ?></select></div>
    <button type="submit" class="btn btn-default">Generar borrador</button>
<?= Form::close() ?>
<?php endif ?>
