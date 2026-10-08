<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sheets/sources') ?>">Fuentes y campos</a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons"><div class="loading-indicator-container">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-primary">Guardar</button>
        <button type="submit" data-request="onSave" data-request-data="close:1" data-load-indicator="Guardando…" class="btn btn-default">Guardar y cerrar</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar esta fuente y sus mapeos?">Eliminar</button>
    </div></div>
<?= Form::close() ?>
