<?php
$notes = \Aero\Medical\Models\Consultation::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)
    ->orderByDesc('consulted_at')->limit(20)->get();
?>
<div class="form-preview" style="margin-bottom:20px">
    <div style="display:flex;gap:8px;align-items:center;margin-bottom:10px">
        <h4 style="margin:0;flex:1">Notas clínicas</h4>
        <?= Form::open() ?>
            <button type="button" class="btn btn-primary btn-sm" data-request="onStartNote" data-request-data="kind: 'consultation'">Nueva consulta</button>
            <button type="button" class="btn btn-default btn-sm" data-request="onStartNote" data-request-data="kind: 'control'">Nuevo control</button>
        <?= Form::close() ?>
    </div>
    <?php if (!count($notes)): ?>
        <p class="text-muted">Aún no hay notas.</p>
    <?php else: ?>
    <table class="table table-condensed">
        <?php foreach ($notes as $n): ?>
        <tr>
            <td style="width:150px"><?= e($n->consulted_at->format('d/m/Y H:i')) ?></td>
            <td><?= e($n->kind_label) ?></td>
            <td><?= e($n->professional_name) ?></td>
            <td><?= e($n->status_label) ?></td>
            <td class="text-right"><a href="<?= Backend::url('aero/medical/consultations/update/' . $n->id) ?>">abrir</a></td>
        </tr>
        <?php endforeach ?>
    </table>
    <?php endif ?>
</div>
