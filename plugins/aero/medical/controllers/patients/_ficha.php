<?php
use Aero\Medical\Classes\Mode;
use Aero\Medical\Models\Analyte;

/** @var \Aero\Medical\Models\Patient $p */
$mode = $p->kind;
$allergies = trim((string) $p->allergies);
Analyte::ensureDefaults((int) $p->tenant_id);
$analytes = Analyte::forTenant((int) $p->tenant_id)->where('is_active', true)->orderBy('sort')->orderBy('name')->get();
$notes = \Aero\Medical\Models\Consultation::where('tenant_id', $p->tenant_id)->where('patient_id', $p->id)->orderByDesc('consulted_at')->limit(15)->get();
$upcoming = [];
if (class_exists(\Aero\Office\Models\Booking::class) && $p->customer_id) {
    $upcoming = \Aero\Office\Models\Booking::where('tenant_id', $p->tenant_id)->where('customer_id', $p->customer_id)
        ->whereIn('status', ['pending', 'confirmed'])->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get();
}
?>
<style>
    .mf-banner{padding:10px 14px;border-radius:4px;margin-bottom:12px;font-size:14px;border:1px solid rgba(128,128,128,.35);background:rgba(128,128,128,.08)}
    .mf-banner.alert{background:rgba(229,57,53,.14);border-color:#e57373}
    .mf-grid{display:grid;grid-template-columns:minmax(230px,300px) minmax(0,1fr);gap:18px;margin-bottom:18px}
    @media (max-width:1000px){.mf-grid{grid-template-columns:1fr}}
    .mf-card{background:rgba(128,128,128,.08);border:1px solid rgba(128,128,128,.28);border-radius:4px;padding:12px;margin-bottom:12px}
    .mf-card h5{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7}
    .mf-ro{white-space:pre-wrap;font-size:13px}
    .mf-sheet{overflow-x:auto}.mf-sheet table{border-collapse:collapse;min-width:100%}
    .mf-sheet th,.mf-sheet td{border:1px solid rgba(128,128,128,.28);padding:5px 8px;font-size:12.5px;vertical-align:top;white-space:pre-wrap}
    .mf-sheet th.rowh{text-align:left;position:sticky;left:0;background:#2b333b;color:#e8eaed;min-width:150px}
    .mf-sheet tr.grp td{background:rgba(128,128,128,.18);font-weight:600;text-transform:uppercase;font-size:11px;letter-spacing:.04em}
    .mf-out{color:#ff5252;font-weight:700}
    .mf-draft{opacity:.6;font-style:italic}
</style>

<div class="mf-banner <?= $allergies !== '' ? 'alert' : '' ?>"><strong>Alergias:</strong> <?= $allergies !== '' ? nl2br(e($allergies)) : 'ninguna registrada' ?></div>

<div class="mf-grid">
<aside>
    <div class="mf-card">
        <h5><?= e(Mode::term('patient', $mode)) ?></h5>
        <div><strong><?= e($p->name) ?></strong></div>
        <div class="text-muted" style="font-size:13px">
            <?= e($p->age_label ?: 'edad s/e') ?><?= $p->document ? ' · ' . e($p->document) : '' ?>
            <?php if ($mode === Mode::ANIMAL): ?><br><?= e(trim((Mode::species()[$p->species] ?? '') . ' ' . $p->breed)) ?><?= $p->microchip ? ' · chip ' . e($p->microchip) : '' ?><?php endif ?>
            <?php if ($p->customer): ?><br><?= e(Mode::term('guardian', $mode)) ?>: <?= e($p->customer->name) ?> <?= e($p->customer->phone) ?><?php endif ?>
        </div>
    </div>
    <div class="mf-card"><h5>Medicación actual</h5><div class="mf-ro"><?= trim((string) $p->medications) !== '' ? e($p->medications) : '<span class="text-muted">—</span>' ?></div></div>
    <div class="mf-card"><h5><?= e(Mode::term('history', $mode)) ?></h5><div class="mf-ro"><?= trim((string) $p->personal_history) !== '' ? e($p->personal_history) : '<span class="text-muted">—</span>' ?></div>
        <?php if ($mode !== Mode::ANIMAL && trim((string) $p->family_history) !== ''): ?><h5 style="margin-top:10px">Antecedentes familiares</h5><div class="mf-ro"><?= e($p->family_history) ?></div><?php endif ?></div>
    <?php if (count($upcoming)): ?>
    <div class="mf-card"><h5>Próximas reservas</h5>
        <?php foreach ($upcoming as $b): ?><div style="font-size:13px"><?= e($b->starts_at->format('d/m H:i')) ?> · <?= e($b->service_name) ?> <small class="text-muted">(<?= e($b->status_label) ?>)</small></div><?php endforeach ?>
    </div>
    <?php endif ?>
    <div class="mf-card">
        <h5>Notas clínicas</h5>
        <?= Form::open() ?>
            <button type="button" class="btn btn-primary btn-sm" data-request="onStartNote" data-request-data="kind: 'consultation'">Nueva consulta</button>
            <button type="button" class="btn btn-default btn-sm" data-request="onStartNote" data-request-data="kind: 'control'">Nuevo control</button>
        <?= Form::close() ?>
        <?php if (!count($notes)): ?><p class="text-muted" style="margin:8px 0 0">Aún no hay notas.</p>
        <?php else: ?><table class="table table-condensed" style="margin:8px 0 0;font-size:12.5px">
            <?php foreach ($notes as $n): ?><tr>
                <td><?= e($n->consulted_at->format('d/m/y')) ?></td><td><?= e($n->kind_label) ?></td><td><?= $n->isSigned() ? 'Firmada' : '<em>Borrador</em>' ?></td>
                <td class="text-right"><a href="<?= Backend::url('aero/medical/consultations/update/' . $n->id) ?>">abrir</a></td></tr><?php endforeach ?>
        </table><?php endif ?>
    </div>
</aside>

<section>
    <h4 style="margin:0 0 8px">Hoja de seguimiento</h4>
    <div id="mc-sheet"><?= $this->makePartial('sheet', ['p' => $p]) ?></div>

    <div class="mf-card" style="margin-top:14px">
        <h5>Anotar un resultado de laboratorio</h5>
        <?= Form::open(['data-request' => 'onAddLab', 'class' => 'mc-reset', 'style' => 'display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:8px;align-items:end']) ?>
            <div><label style="font-size:12px">Analito</label>
                <select name="analyte_id" class="form-control"><option value="">— Otro (escribir) —</option>
                    <?php foreach ($analytes as $a): ?><option value="<?= (int) $a->id ?>"><?= e($a->name) ?><?= $a->unit ? ' (' . e($a->unit) . ')' : '' ?></option><?php endforeach ?></select></div>
            <div><label style="font-size:12px">…o nombre</label><input type="text" name="analyte_name" class="form-control" maxlength="255"></div>
            <div><label style="font-size:12px">Valor</label><input type="text" inputmode="decimal" name="value" class="form-control" required></div>
            <div><label style="font-size:12px">Unidad</label><input type="text" name="unit" class="form-control" maxlength="32"></div>
            <div><label style="font-size:12px">Fecha</label><input type="date" name="measured_on" class="form-control" value="<?= e(now()->toDateString()) ?>" max="<?= e(now()->toDateString()) ?>"></div>
            <div><button type="submit" class="btn btn-default">Agregar</button></div>
        <?= Form::close() ?>
        <div id="mc-labs" style="margin-top:10px"><?= $this->makePartial('labs', ['p' => $p]) ?></div>
    </div>
</section>
</div>
<script>
document.addEventListener('ajax:done', function (e) { var f = e.target; if (f && f.classList && f.classList.contains('mc-reset')) f.reset(); });
</script>
