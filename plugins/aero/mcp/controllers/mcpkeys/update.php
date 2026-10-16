<?= Form::open(['class' => 'layout']) ?>
    <div class="form-group">
        <label><?= e(trans('aero.api::lang.key.secret')) ?></label>
        <div class="input-group">
            <input type="text" id="aeroApiKeyValue" class="form-control" readonly
                value="<?= e($formModel->key_prefix) ?>&hellip;">
            <span class="input-group-btn">
                <button type="button" class="btn btn-default"
                    data-request="onRevealKey"
                    data-request-success="aeroApiShowKey(data)"
                    data-load-indicator="<?= e(trans('aero.api::lang.key.revealing')) ?>">
                    <?= e(trans('aero.api::lang.key.reveal')) ?>
                </button>
                <button type="button" class="btn btn-default"
                    data-request="onRegenerateKey"
                    data-request-confirm="<?= e(trans('aero.api::lang.key.regenerate_confirm')) ?>"
                    data-request-success="aeroApiShowKey(data)">
                    <?= e(trans('aero.api::lang.key.regenerate')) ?>
                </button>
            </span>
        </div>
        <p class="help-block"><?= e(trans('aero.api::lang.key.secret_comment')) ?></p>
    </div>

    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>

    <div class="form-buttons">
        <button type="submit" data-request="onSave" data-request-data="close:1" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">
            <?= e(trans('backend::lang.form.save')) ?>
        </button>
        <?php if (!$formModel->isRevoked()): ?>
            <button type="button" class="btn btn-danger"
                data-request="onRevokeKey"
                data-request-confirm="<?= e(trans('aero.api::lang.key.revoke_confirm')) ?>">
                <?= e(trans('aero.api::lang.key.revoke')) ?>
            </button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/mcp/mcpkeys') ?>" class="btn btn-default">
            <?= e(trans('backend::lang.form.cancel')) ?>
        </a>
    </div>
<?= Form::close() ?>

<script>
    function aeroApiShowKey(data) {
        if (data && data.key) {
            $('#aeroApiKeyValue').val(data.key).select();
        }
    }
</script>
