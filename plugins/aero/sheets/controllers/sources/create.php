<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/sheets/sources') ?>">Fuentes y campos</a></li>
        <li>Nueva fuente</li>
    </ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons"><div class="loading-indicator-container">
        <button type="submit" data-request="onSave" data-request-data="redirect:0" data-hotkey="ctrl+s, cmd+s" data-load-indicator="Guardando…" class="btn btn-primary">Crear y elegir campos</button>
        <a href="<?= Backend::url('aero/sheets/sources') ?>" class="btn btn-default">Cancelar</a>
    </div></div>
<?= Form::close() ?>
