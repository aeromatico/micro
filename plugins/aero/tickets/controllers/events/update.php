<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/events') ?>">Eventos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $ev = $formModel; ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <?php if ($ev->status === 'draft'): ?>
            <button type="button" class="btn btn-success" data-request="onPublish" data-request-data="id: <?= (int) $ev->id ?>">Publicar</button>
        <?php elseif ($ev->status === 'published'): ?>
            <button type="button" class="btn btn-default" data-request="onUnpublish" data-request-data="id: <?= (int) $ev->id ?>">Volver a borrador</button>
        <?php endif ?>
        <?php if (in_array($ev->status, ['draft', 'published', 'ongoing'], true)): ?>
            <button type="button" class="btn btn-warning" data-request="onCancelEvent" data-request-data="id: <?= (int) $ev->id ?>"
                    data-request-confirm="¿Cancelar este evento?">Cancelar evento</button>
        <?php endif ?>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este evento?">Eliminar</button>
        <a href="<?= Backend::url('aero/tickets/events') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
