<?php Block::put('breadcrumb') ?>
    <ul>
        <li><a href="<?= Backend::url('aero/plan/plans') ?>">Planes de los tenants</a></li>
        <li><?= e($this->pageTitle) ?> · <?= e($plan->tenant_name) ?></li>
    </ul>
<?php Block::endPut() ?>

<div id="plan-root"><?= $this->renderPlan() ?></div>
