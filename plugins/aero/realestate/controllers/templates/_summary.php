<?php $id = (int) $m->id; $own = $m->tenant_id !== null || \Aero\Realestate\Classes\CurrentTenant::isAdmin(); ?>
<div class="callout callout-<?= $m->is_reviewed ? 'success' : 'warning' ?>" style="margin-bottom:15px">
    <div class="header"><h3><?= e($m->name) ?> · v<?= (int) $m->version ?> · <?= $m->is_reviewed ? 'Revisada legalmente' : 'SIN revisión legal' ?></h3></div>
    <div class="content">
        <?php if ($m->is_reviewed): ?>
            <p>Revisó <strong><?= e($m->reviewed_by_name) ?></strong> el <?= e($m->reviewed_at?->format('d/m/Y')) ?>. <?= e($m->legal_notes) ?></p>
        <?php else: ?>
            <p>Los contratos generados desde esta plantilla salen como <strong>borrador</strong> con marca de agua y no pueden aprobarse ni firmarse en el sistema hasta que un abogado la revise y se registre aquí.</p>
        <?php endif ?>
        <?php if ($m->tenant_id === null): ?><p class="text-muted">Es un modelo base de la plataforma (solo lectura): duplícalo para adaptarlo a tu inmobiliaria.</p><?php endif ?>
        <p>
            <button type="button" class="btn btn-default" data-request="onDuplicate" data-request-data="id: <?= $id ?>" data-request-confirm="¿Crear una copia editable de esta plantilla?">Duplicar</button>
            <?php if ($own && !$m->is_reviewed): ?><button type="button" class="btn btn-success" data-control="popup" data-handler="onReviewForm" data-request-data="id: <?= $id ?>">Registrar revisión legal</button><?php endif ?>
        </p>
        <details><summary>Variables disponibles</summary>
        <?php foreach (\Aero\Realestate\Classes\TemplateRenderer::help() as $group => $vars): ?>
            <p><strong><?= e($group) ?>:</strong> <?php foreach ($vars as $v): ?><code>{{ <?= e($v) ?> }}</code> <?php endforeach ?></p>
        <?php endforeach ?>
        <p class="text-muted">Lo que el sistema no tiene sale como una línea en blanco para completar a mano.</p>
        </details>
    </div>
</div>
