<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/producer/orders') ?>">Mis videos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php $locked = !$formModel->is_editable && !$this->user->hasAccess('aero.producer.superadmin') ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender(['preview' => $locked]) ?></div>
    <div class="form-buttons">
        <?php if ($this->user->hasAccess('aero.producer.superadmin') && ($formModel->style->kind ?? null) === 'presenter' && in_array($formModel->status, ['queued', 'failed'], true)): ?>
            <?php if ($formModel->status === 'queued'): ?>
                <button type="button" class="btn btn-warning" data-request="onProduce" data-load-indicator="Enviando…" data-request-confirm="Esto gasta saldo real de HeyGen (~US$1 por video de 25 s). ¿Producir ahora?">Producir con HeyGen</button>
            <?php endif ?>
        <?php endif ?>
        <?php if ($this->user->hasAccess('aero.producer.superadmin') && $formModel->status === 'queued'): ?>
            <button type="button" class="btn btn-default" data-request="onReject" data-request-confirm="Rechaza el pedido y devuelve los créditos al tenant. ¿Continuar?">Rechazar y devolver créditos</button>
        <?php endif ?>
        <?php if (!$locked): ?>
            <button type="submit" data-request="onSave" data-hotkey="ctrl+enter, cmd+enter" class="btn btn-primary">Guardar borrador</button>
            <button type="submit" data-request="onSave" data-request-data="send:1" data-request-confirm="<?= e(($q = $formModel->style ? \Aero\Producer\Classes\Billing::quote($formModel->style, $formModel->tenant_id) : null) ? '¿Enviar el pedido? Se cobran ' . $q['cost'] . ' ' . mb_strtolower($q['type']) . '.' : '¿Enviar el pedido para producción?') ?>" class="btn btn-success">Guardar y enviar</button>
            <button type="button" class="btn btn-danger pull-right" data-request="onDelete" data-request-confirm="¿Eliminar este pedido?">Eliminar</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/producer/orders') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
