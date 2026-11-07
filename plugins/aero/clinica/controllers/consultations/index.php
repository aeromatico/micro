<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/clinica/consultations') ?>"><?= e("Consultas") ?></a></li></ul>
<?php Block::endPut() ?>
<?= \Aero\Clinica\Classes\Hints::render('consultations') ?>
<?= $this->listRender() ?>
