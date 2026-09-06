<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>
    <div class="form-buttons">
        <button
            type="submit"
            data-request="onSave"
            data-request-data="close:1"
            data-hotkey="ctrl+enter, cmd+enter"
            data-load-indicator="<?= e(trans('backend::lang.form.creating')) ?>"
            class="btn btn-primary">
            <?= e(trans('backend::lang.form.create')) ?>
        </button>
        <a href="<?= Backend::url('aero/connector/connectors') ?>" class="btn btn-default">
            <?= e(trans('backend::lang.form.cancel')) ?>
        </a>
    </div>
<?= Form::close() ?>
