<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/social/posts') ?>">Publicaciones</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="button" class="btn btn-primary" data-request="onPublish" data-load-indicator="Enviando…">Publicar / programar</button>
        <button type="submit" class="btn btn-default" data-request="onSave" data-load-indicator="Guardando…">Guardar borrador</button>
        <a href="<?= Backend::url('aero/social/posts') ?>" class="btn btn-link">Cancelar</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
