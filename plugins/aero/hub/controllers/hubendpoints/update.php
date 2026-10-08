<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/hub/hubendpoints') ?>"><?= e(trans('aero.hub::lang.menu.hub')) ?></a></li>
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?= Form::open(['class' => 'layout']) ?>

    <div class="layout-row">
        <?= $this->formRender() ?>
    </div>

    <div class="form-buttons">
        <button
            type="button"
            data-request="onSave"
            data-request-data="close:0"
            data-hotkey="ctrl+s, cmd+s"
            data-load-indicator="<?= e(trans('backend::lang.form.saving')) ?>"
            class="btn btn-primary">
            <?= e(trans('backend::lang.form.save')) ?>
        </button>
        <span class="btn-text">
            or <a href="<?= Backend::url('aero/hub/hubendpoints') ?>"><?= e(trans('backend::lang.form.cancel')) ?></a>
        </span>
    </div>

<?= Form::close() ?>
