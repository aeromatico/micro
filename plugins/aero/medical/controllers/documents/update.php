<?php /** @var \Aero\Medical\Models\MedicalDocument $d */ ?>
<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/medical/patients') ?>">Pacientes</a></li>
        <li><a href="<?= Backend::url('aero/medical/patients/update/' . $d->patient_id) ?>"><?= e($d->patient->name) ?></a></li>
        <li><?= e($d->kind_label) ?></li>
    </ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<h3 style="margin-top:0"><?= e($d->title) ?> <small class="text-muted">· <?= e($d->patient->name) ?> · <?= $d->isIssued() ? 'Emitido el ' . e($d->issued_at->format('d/m/Y H:i')) : 'Borrador' ?></small></h3>

<?php if ($editable): ?>
    <?= Form::open() ?>
        <div class="form-group"><label>Título</label><input type="text" class="form-control" name="title" value="<?= e($d->title) ?>" maxlength="255"></div>
        <div class="form-group"><label>Texto del documento</label>
            <textarea class="form-control" name="body" rows="18" style="font-family:inherit"><?= e($d->body) ?></textarea>
            <p class="help-block">Las variables ya están resueltas. Corrija lo que haga falta; las líneas <code><?= e(\Aero\Medical\Classes\DocumentRenderer::BLANK) ?></code> son datos que faltan.
                Al emitir queda inmutable y se imprime con la firma y el sello de quien lo emite.</p></div>
        <div class="form-buttons">
            <button type="button" class="btn btn-default" data-request="onSave">Guardar borrador</button>
            <button type="button" class="btn btn-primary" data-request="onIssue" data-request-confirm="¿Emitir el documento? Quedará inmutable.">Emitir con firma y sello</button>
            <button type="button" class="btn btn-danger" data-request="onDiscard" data-request-confirm="¿Descartar este borrador?">Descartar</button>
        </div>
    <?= Form::close() ?>
<?php else: ?>
    <div style="white-space:pre-wrap;max-width:780px;border:1px solid rgba(128,128,128,.3);padding:16px;border-radius:4px;margin-bottom:12px"><?= e($d->body) ?></div>
    <?php if ($d->isIssued()): ?>
        <p class="text-muted">Emitido por <?= e($d->professional_name) ?> · código <?= e($d->short_hash) ?> ·
            <?= $d->integrityOk() ? 'íntegro' : '<strong style="color:#e53935">NO COINCIDE: el contenido fue alterado</strong>' ?></p>
        <a class="btn btn-primary" target="_blank" href="<?= Backend::url('aero/medical/documents/print/' . $d->id) ?>">Imprimir con firma y sello</a>
    <?php else: ?>
        <p class="text-muted">Borrador de <?= e($d->professional_name) ?>. Solo quien lo generó puede editarlo y emitirlo.</p>
    <?php endif ?>
    <a class="btn btn-default" href="<?= Backend::url('aero/medical/patients/update/' . $d->patient_id) ?>">Ficha del paciente</a>
<?php endif ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
