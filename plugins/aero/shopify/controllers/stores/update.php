<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/shopify/stores') ?>">Tiendas</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <button type="button" class="btn btn-default" data-request="onTest">Probar conexión</button>
        <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar esta tienda? Los pedidos ya cobrados no se afectan.">Eliminar</button>
        <a href="<?= Backend::url('aero/shopify/stores') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
