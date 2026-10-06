<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tracking/feeds') ?>">Seguimiento externo</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-load-indicator="Leyendo el enlace..." data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Empezar a seguir</button>
        <a href="<?= Backend::url('aero/tracking/feeds') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
