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
            class="btn btn-primary">
            <?= e(trans('backend::lang.form.save')) ?>
        </button>
        <button
            type="button"
            class="btn btn-danger oc-icon-trash-o"
            data-request="onDelete"
            data-request-confirm="<?= e(trans('backend::lang.form.delete_confirm')) ?>">
            <?= e(trans('backend::lang.form.delete')) ?>
        </button>
        <a href="<?= Backend::url('aero/connector/webhookendpoints') ?>" class="btn btn-default">
            <?= e(trans('backend::lang.form.cancel')) ?>
        </a>
    </div>
<?= Form::close() ?>
