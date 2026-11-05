<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/orders') ?>">Órdenes</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $o = $formModel; ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <?php if (in_array($o->status, ['pending_payment', 'verifying', 'incident'], true) && !$o->isLapsed()): ?>
            <button type="button" class="btn btn-success"
                    onclick="var n=prompt('Confirmas que el dinero YA está en tu cuenta o caja. ¿Cómo lo recibiste? (referencia, recibo, nota)'); if(n){$(this).request('onConfirm',{data:{id:<?= (int) $o->id ?>,note:n}})}">Confirmar cobro</button>
        <?php endif ?>
        <?php if (in_array($o->status, ['pending_payment', 'verifying'], true) && !$o->isLapsed()): ?>
            <button type="button" class="btn btn-warning"
                    onclick="var n=prompt('Describe la incidencia (monto, comprobante…)'); if(n!==null){$(this).request('onIncident',{data:{id:<?= (int) $o->id ?>,note:n}})}">Poner en incidencia</button>
        <?php endif ?>
        <?php if ($o->status === 'confirmed' && $o->buyer_email): ?>
            <button type="button" class="btn btn-default" data-request="onResend" data-request-data="id: <?= (int) $o->id ?>">Reenviar entradas</button>
        <?php endif ?>
        <?php if (in_array($o->status, ['pending_payment', 'verifying', 'incident', 'confirmed'], true)): ?>
            <button type="button" class="btn btn-danger" data-request="onCancelOrder" data-request-data="id: <?= (int) $o->id ?>"
                    data-request-confirm="¿Cancelar la orden? Se anulan sus entradas y se libera el cupo.">Cancelar orden</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/orders') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
