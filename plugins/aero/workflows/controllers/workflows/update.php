<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/workflows/workflows') ?>">Workflows</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar</button>
        <a href="<?= Backend::url('aero/workflows/workflows') ?>" class="btn btn-default">Cancelar</a>
        <span class="m-l">
            <input type="text" name="test_payload" class="form-control" style="display:inline-block;width:260px" placeholder='Entrada de prueba (JSON), ej. {"email":"a@b.c"}'>
            <button type="button" data-request="onTestRun" data-request-data="recordId: '<?= $formModel->id ?>'" data-request-flash class="btn btn-default oc-icon-play">Probar</button>
            <span id="testRunLink" class="m-l"></span>
        </span>
    </div>
<?= Form::close() ?>
