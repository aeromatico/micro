<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sheets/mappings') ?>">Mapeos</a></li>
        <li>Nuevo mapeo</li>
    </ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons"><div class="loading-indicator-container">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-primary">Crear y configurar columnas</button>
        <a href="<?= Backend::url('aero/sheets/mappings') ?>" class="btn btn-default">Cancelar</a>
    </div></div>
<?= Form::close() ?>
