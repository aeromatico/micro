<?php if ($record->backend_user_id): ?>
    <a href="javascript:;"
       class="btn btn-xs btn-default oc-icon-sign-in"
       title="Ingresar al panel como administrador de este tenant"
       data-request="onImpersonate"
       data-request-data="recordId: <?= (int) $record->id ?>"
       data-request-confirm="¿Ingresar como administrador de «<?= e($record->name) ?>»? Tu sesión actual quedará en pausa hasta que vuelvas.">
        Ingresar
    </a>
<?php else: ?>
    <span class="text-muted">—</span>
<?php endif ?>
