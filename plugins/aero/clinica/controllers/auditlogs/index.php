<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/clinica/auditlogs') ?>"><?= e("Bitácora") ?></a></li></ul>
<?php Block::endPut() ?>
<?= $this->listRender() ?>
