<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/tickets/payments') ?>">Pagos</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<?php if (!$this->fatalError): ?>
<?php $p = $formModel; $id = (int) $p->id; ?>
<?= Form::open(['class' => 'layout']) ?>
    <div class="layout-row"><?= $this->formRender() ?></div>
    <div class="form-buttons">
        <?php if (in_array($p->status, ['pending', 'submitted', 'expired'], true)): ?>
            <button type="button" class="btn btn-success"
                onclick="var a=prompt('Monto que VERIFICASTE en tu banco (Bs). Esperado: <?= number_format((float) $p->amount, 2, '.', '') ?>','<?= number_format((float) $p->amount, 2, '.', '') ?>'); if(a===null)return; var n=prompt('Nota de verificación<?= $p->is_suspicious ? ' (OBLIGATORIA: el pago está marcado como sospechoso)' : ' (opcional)' ?>','') ; if(n===null)return; $(this).request('onApprove',{data:{id:<?= $id ?>,amount:a,note:n}})">Aprobar pago</button>
        <?php endif ?>
        <?php if (in_array($p->status, ['pending', 'submitted'], true)): ?>
            <button type="button" class="btn btn-danger"
                onclick="var r=prompt('Motivo del rechazo (se le muestra al comprador)'); if(r){$(this).request('onReject',{data:{id:<?= $id ?>,reason:r}})}">Rechazar</button>
            <button type="button" class="btn btn-warning"
                onclick="var r=prompt('Describe la inconsistencia (monto, titular, comprobante…)'); if(r){$(this).request('onInconsistency',{data:{id:<?= $id ?>,reason:r}})}">Reportar inconsistencia</button>
        <?php endif ?>
        <?php if ($p->status === 'refund_pending'): ?>
            <button type="button" class="btn btn-primary"
                onclick="var r=prompt('Referencia de la devolución al comprador'); if(r){$(this).request('onRefunded',{data:{id:<?= $id ?>,reference:r}})}">Registrar reembolso</button>
        <?php endif ?>
        <a href="<?= Backend::url('aero/tickets/payments') ?>" class="btn btn-default">Volver</a>
    </div>
<?= Form::close() ?>
<?php else: ?>
    <p class="flash-message static error"><?= e($this->fatalError) ?></p>
<?php endif ?>
