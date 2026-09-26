<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/hub/hubendpoints') ?>"><?= e(trans('aero.hub::lang.menu.hub')) ?></a></li>
    </ul>
<?php Block::endPut() ?>

<?= $this->listRender() ?>
