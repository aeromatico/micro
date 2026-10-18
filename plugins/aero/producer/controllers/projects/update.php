<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/producer/projects') ?>">Proyectos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if ($problems = $formModel->metadataProblems()): ?>
    <p class="flash-message static warning">Antes de enviar a YouTube: <?= e(implode(' ', $problems)) ?></p>
<?php endif ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-default" data-request="onSync">Releer carpeta y Medios</button>
        <button type="button" class="btn btn-success" data-request="onSendToYoutube" data-request-confirm="Crea borradores PRIVADOS en YouTube → Subidas. No sube nada todavía. ¿Continuar?">Enviar a YouTube</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar el proyecto? Los archivos no se borran.">Eliminar</button>
        <a href="<?= Backend::url('aero/producer/projects') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
