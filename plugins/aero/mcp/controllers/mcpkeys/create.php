<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>
    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Crear</button>
        <a href="<?= Backend::url('aero/mcp/mcpkeys') ?>" class="btn btn-default">Cancelar</a>
    </div>
<?= Form::close() ?>
