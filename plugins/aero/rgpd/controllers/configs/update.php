<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/rgpd/configs') ?>">Cookies (RGPD)</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-hotkey="ctrl+s, cmd+s" class="btn btn-primary">Guardar</button>
        <button type="submit" data-request="onSave" data-request-data="close:1" class="btn btn-default">Guardar y cerrar</button>
    </div>
<?= Form::close() ?>
