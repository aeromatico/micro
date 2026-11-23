<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/social/posts') ?>">Publicaciones</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>

<?php if (!$this->fatalError): ?>
<?php $post = $formModel; ?>
<div id="socTargets"><?= $this->makePartial('targets', ['post' => $post]) ?></div>

<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <?php if ($post->isEditable()): ?>
            <button type="button" class="btn btn-primary" data-request="onPublish" data-load-indicator="Enviando…"
                    data-request-confirm="¿Publicar (o programar) en las cuentas elegidas? Se descuentan los créditos de cada red.">Publicar / programar</button>
            <button type="submit" class="btn btn-default" data-request="onSave" data-load-indicator="Guardando…">Guardar borrador</button>
            <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar el borrador?">Eliminar</button>
        <?php elseif ($post->isCancellable()): ?>
            <button type="button" class="btn btn-warning" data-request="onCancelSchedule"
                    data-request-confirm="¿Cancelar la programación? Se devuelven los créditos.">Cancelar programación</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/social/posts') ?>" class="btn btn-link">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
