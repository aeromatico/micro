<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/docs/guides') ?>">Guías</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <?php if ($formModel->has_pending || $formModel->status !== 'published'): ?>
            <button type="button" class="btn btn-success" data-request="onApprove" data-request-data="record_id: <?= (int) $formModel->id ?>"
                data-request-confirm="¿Publicar esta guía? Será visible en /guias.">Aprobar y publicar</button>
        <?php endif ?>
        <?php if ($formModel->has_pending): ?>
            <button type="button" class="btn btn-default" data-request="onReject" data-request-data="record_id: <?= (int) $formModel->id ?>"
                data-request-confirm="¿Descartar la propuesta? Lo publicado no cambia.">Descartar propuesta</button>
        <?php endif ?>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar?">Eliminar</button>
        <a href="<?= Backend::url('aero/docs/guides') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
