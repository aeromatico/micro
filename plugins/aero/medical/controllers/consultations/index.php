<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/medical/consultations') ?>"><?= e("Consultas") ?></a></li></ul>
<?php Block::endPut() ?>
<?= $this->listRender() ?>
