<?php
use Aero\Medical\Classes\Mode;
use Aero\Medical\Classes\VitalsSchema;

$p = $c->patient;
$u = BackendAuth::getUser();
$v = $c->vitals;
$content = (array) $c->content;
$allergies = trim((string) $p->allergies);
?>
<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/medical/consultations') ?>">Consultas</a></li>
        <li><a href="<?= Backend::url('aero/medical/patients/update/' . $p->id) ?>"><?= e($p->name) ?></a></li>
        <li><?= e($c->kind_label) ?></li>
    </ul>
<?php Block::endPut() ?>

<style>
    /* Colores neutros: el panel puede estar en modo claro u oscuro. */
    .mc-banner{padding:10px 14px;border-radius:4px;margin-bottom:14px;font-size:14px;border:1px solid rgba(128,128,128,.35);background:rgba(128,128,128,.08)}
    .mc-banner.alert{background:rgba(229,57,53,.14);border-color:#e57373}
    .mc-grid{display:grid;grid-template-columns:minmax(0,2fr) minmax(260px,1fr);gap:20px}
    @media (max-width:900px){.mc-grid{grid-template-columns:1fr}}
    .mc-card{background:rgba(128,128,128,.08);border:1px solid rgba(128,128,128,.28);border-radius:4px;padding:14px;margin-bottom:14px}
    .mc-card h5{margin:0 0 8px;font-size:12px;text-transform:uppercase;letter-spacing:.04em;opacity:.7}
    .mc-sec{margin-bottom:16px}.mc-sec>label{font-weight:600;display:block;margin-bottom:4px}
    .mc-sec textarea{width:100%;resize:vertical}
    .mc-vitals{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
    .mc-vitals label{font-size:12px;opacity:.8;display:block}.mc-vitals .u{opacity:.6;font-size:11px}
    .mc-ro{white-space:pre-wrap}.mc-prev{font-size:13px}.mc-prev b{display:block;margin-top:8px}
    .mc-status{opacity:.75;font-size:13px}.mc-status.err{color:#e53935;opacity:1}
    .mc-hash{font-family:monospace;font-size:12px;opacity:.75}
</style>

<?php if (!$this->fatalError): ?>

<div class="mc-banner <?= $allergies !== '' ? 'alert' : 'none' ?>" id="mc-allergies">
    <strong>Alergias:</strong>
    <?= $allergies !== '' ? nl2br(e($allergies)) : 'ninguna registrada' ?>
</div>

<div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:8px;margin-bottom:12px">
    <div>
        <h3 style="margin:0"><?= e($p->name) ?>
            <small class="text-muted">· <?= e($p->age_label ?: 's/e') ?> · <?= e($c->kind_label) ?> del <?= e($c->consulted_at->format('d/m/Y H:i')) ?></small>
        </h3>
        <div class="text-muted" style="font-size:13px">
            <?= e(Mode::term('professional', $mode)) ?>: <?= e($c->professional_name) ?>
            <?= $c->service_name ? ' · ' . e($c->service_name) : '' ?>
            · <strong><?= $c->isSigned() ? 'Firmada' : 'Borrador' ?></strong>
        </div>
    </div>
    <div>
        <?php if ($c->isSigned()): ?>
            <a class="btn btn-default" target="_blank" href="<?= Backend::url('aero/medical/consultations/print/' . $c->id) ?>">Imprimir con firma y sello</a>
            <a class="btn btn-default" href="<?= Backend::url('aero/medical/charges?consultation_id=' . $c->id) ?>">Registrar cobro</a>
        <?php endif ?>
        <a class="btn btn-default" href="<?= Backend::url('aero/medical/patients/update/' . $p->id) ?>">Ficha del paciente</a>
    </div>
</div>

<div class="mc-grid">
<div>
<?php if ($editable): ?>
    <?= Form::open(['id' => 'mc-form', 'data-request' => 'onAutosave', 'data-track-input' => '1200', 'data-request-flash' => '0']) ?>
        <?php foreach ($c->sectionList() as $s): ?>
            <?php if ($s['type'] === 'vitals'): ?>
            <div class="mc-sec">
                <label><?= e($s['label']) ?></label>
                <div class="mc-vitals">
                <?php foreach ($vitalFields as $name => $f): ?>
                    <div>
                        <label for="v-<?= e($name) ?>"><?= e($f['label']) ?> <span class="u"><?= e($f['unit']) ?></span></label>
                        <?php if (!empty($f['computed'])): ?>
                            <input type="text" class="form-control" id="mc-<?= e($name) ?>" value="<?= e($v?->{$name}) ?>" readonly placeholder="calculado">
                        <?php elseif (isset($f['options'])): ?>
                            <select class="form-control" name="vitals[<?= e($name) ?>]" id="v-<?= e($name) ?>">
                                <option value="">—</option>
                                <?php foreach ($f['options'] as $k => $lbl): ?>
                                    <option value="<?= e($k) ?>" <?= $v?->{$name} === $k ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                <?php endforeach ?>
                            </select>
                        <?php else: ?>
                            <input type="number" class="form-control" name="vitals[<?= e($name) ?>]" id="v-<?= e($name) ?>"
                                   step="<?= e($f['step']) ?>" min="<?= e($f['min']) ?>" max="<?= e($f['max']) ?>"
                                   value="<?= e($v?->{$name} !== null ? rtrim(rtrim((string) $v->{$name}, '0'), '.') : '') ?>">
                        <?php endif ?>
                    </div>
                <?php endforeach ?>
                </div>
            </div>
            <?php else: ?>
            <div class="mc-sec">
                <label for="s-<?= e($s['key']) ?>"><?= e($s['label']) ?></label>
                <textarea class="form-control" name="content[<?= e($s['key']) ?>]" id="s-<?= e($s['key']) ?>" rows="<?= (int) ($s['rows'] ?? 3) ?>"><?= e($content[$s['key']] ?? '') ?></textarea>
            </div>
            <?php endif ?>
        <?php endforeach ?>

        <div class="form-buttons" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
            <button type="button" class="btn btn-primary" data-request="onSign"
                    data-request-confirm="¿Firmar la nota? Quedará inmutable; los cambios posteriores serán adendas.">Firmar nota</button>
            <button type="button" class="btn btn-danger" data-request="onDiscard"
                    data-request-confirm="¿Descartar este borrador?">Descartar borrador</button>
            <span class="mc-status" id="mc-status" aria-live="polite">Se guarda solo mientras escribe.</span>
        </div>
    <?= Form::close() ?>

<?php else: ?>
    <?php if (!$c->isSigned()): ?>
        <div class="mc-banner none">Borrador de <?= e($c->professional_name) ?>. Solo quien lo abrió puede editarlo y firmarlo.</div>
    <?php endif ?>
    <?php foreach ($c->sectionList() as $s): ?>
        <div class="mc-sec">
            <label><?= e($s['label']) ?></label>
            <?php if ($s['type'] === 'vitals'): ?>
                <table class="table table-condensed" style="max-width:520px">
                <?php foreach ($vitalFields as $name => $f): $val = VitalsSchema::format($name, $v?->{$name}); if ($val === '') continue; ?>
                    <tr><th style="width:55%"><?= e($f['label']) ?></th><td><?= e($val) ?></td></tr>
                <?php endforeach ?>
                </table>
            <?php else: ?>
                <div class="mc-ro"><?= trim((string) ($content[$s['key']] ?? '')) !== '' ? e($content[$s['key']]) : '<span class="text-muted">—</span>' ?></div>
            <?php endif ?>
        </div>
    <?php endforeach ?>
<?php endif ?>

<?= $this->makePartial('clinical_cards') ?>

<?php if ($c->isSigned()): ?>
    <div class="mc-card">
        <h5>Firma</h5>
        Firmada por <strong><?= e($c->professional_name) ?></strong> el <?= e($c->signed_at->format('d/m/Y H:i')) ?>.
        <div class="mc-hash">Código de integridad: <?= e($c->short_hash) ?> ·
            <?= $c->integrityOk() ? 'verificado' : '<strong style="color:#e53935">NO COINCIDE: el contenido fue alterado</strong>' ?></div>
    </div>

    <div class="mc-card">
        <h5>Adendas</h5>
        <?php foreach ($c->addenda as $a): ?>
            <div style="margin-bottom:10px">
                <small class="text-muted"><?= e($a->user_name) ?> · <?= e($a->created_at->format('d/m/Y H:i')) ?></small>
                <div class="mc-ro"><?= e($a->body) ?></div>
            </div>
        <?php endforeach ?>
        <?php if (!count($c->addenda)): ?><p class="text-muted">Sin adendas.</p><?php endif ?>
        <?= Form::open(['id' => 'mc-addendum']) ?>
            <textarea class="form-control" name="addendum" rows="3" placeholder="Agregar una adenda (no modifica la nota firmada)"></textarea>
            <button type="button" class="btn btn-default" style="margin-top:8px" data-request="onAddendum">Agregar adenda</button>
        <?= Form::close() ?>
    </div>
<?php endif ?>
</div>

<aside>
    <div class="mc-card">
        <h5><?= e(Mode::term('history', $mode)) ?></h5>
        <div class="mc-ro"><?= trim((string) $p->personal_history) !== '' ? e($p->personal_history) : '<span class="text-muted">—</span>' ?></div>
        <?php if ($mode !== Mode::ANIMAL && trim((string) $p->family_history) !== ''): ?>
            <h5 style="margin-top:12px">Antecedentes familiares</h5>
            <div class="mc-ro"><?= e($p->family_history) ?></div>
        <?php endif ?>
    </div>
    <div class="mc-card">
        <h5>Medicación actual</h5>
        <div class="mc-ro"><?= trim((string) $p->medications) !== '' ? e($p->medications) : '<span class="text-muted">—</span>' ?></div>
    </div>
    <div class="mc-card mc-prev">
        <h5>Anotado la vez anterior</h5>
        <?php if ($previous): ?>
            <small class="text-muted"><?= e($previous->kind_label) ?> del <?= e($previous->consulted_at->format('d/m/Y')) ?> · <?= e($previous->professional_name) ?></small>
            <a href="<?= Backend::url('aero/medical/consultations/update/' . $previous->id) ?>" style="float:right">abrir</a>
            <?php $pc = (array) $previous->content; foreach ($c->sectionList() as $s):
                if ($s['type'] !== 'text' || trim((string) ($pc[$s['key']] ?? '')) === '') continue; ?>
                <b><?= e($s['label']) ?></b>
                <div class="mc-ro"><?= e($pc[$s['key']]) ?></div>
            <?php endforeach ?>
            <?php if ($previous->vitals): $line = []; foreach ($vitalFields as $n => $f) { $t = VitalsSchema::format($n, $previous->vitals->{$n}); if ($t !== '') $line[] = $f['label'] . ' ' . $t; } ?>
                <?php if ($line): ?><b>Signos vitales</b><div><?= e(implode(' · ', $line)) ?></div><?php endif ?>
            <?php endif ?>
        <?php else: ?>
            <p class="text-muted">Es la primera nota firmada de este paciente.</p>
        <?php endif ?>
    </div>
</aside>
</div>

<?php if ($editable): ?>
<script>
(function () {
    var status = document.getElementById('mc-status');
    var form = document.getElementById('mc-form');
    function set(msg, err) { status.textContent = msg; status.className = 'mc-status' + (err ? ' err' : ''); }
    form.addEventListener('input', function () { set('Escribiendo…'); });
    form.addEventListener('ajax:before-send', function (e) { if (e.target === form) set('Guardando…'); });
    form.addEventListener('ajax:done', function (e) {
        if (e.target !== form) return;
        var d = (e.detail && (e.detail.data || e.detail.response)) || {};
        d = d.data || d;
        set('Guardado ' + (d.saved || ''));
        var bmi = document.getElementById('mc-bmi');
        if (bmi && d.bmi !== undefined && d.bmi !== null) bmi.value = d.bmi;
    });
    form.addEventListener('ajax:fail', function (e) { if (e.target === form) set('No se pudo guardar. Reintente.', true); });
})();
</script>
<?php endif ?>

<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
