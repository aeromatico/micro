<?php
$bs = fn ($v) => 'Bs ' . rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
$state = $status['state'] ?? 'none';
$tone = ['ok' => 'success', 'soon' => 'warning', 'overdue' => 'danger', 'none' => 'info'][$state];
?>
<div style="max-width:760px;margin:1.5rem auto;padding:0 1rem">
    <?php if (!$tenant): ?>
        <div class="callout callout-warning no-subheader"><div class="header"><h3>No se encontró tu espacio</h3></div></div>
    <?php else: ?>

        <div class="callout callout-<?= $tone ?> no-subheader" style="margin-bottom:1.5rem">
            <div class="header"><i class="icon-credit-card"></i>
                <h3><?= e($plan?->name ?? 'Tu plan') ?><?= $state !== 'none' ? ' — ' . e($phrase) : '' ?></h3>
            </div>
            <div class="content">
                <?php if ($state === 'none'): ?>
                    <p>Tu plan no tiene un vencimiento por pagar ahora.</p>
                <?php else: ?>
                    <p>Vencimiento: <strong><?= e($status['expires']->format('d/m/Y')) ?></strong>
                    <?php if ($state === 'overdue'): ?>
                        · Paga antes de <strong><?= e($status['expires']->copy()->addDays(\Aero\Sites\Classes\PlanStatus::GRACE_DAYS)->format('d/m/Y')) ?></strong> para no perder el acceso.
                    <?php endif ?></p>
                <?php endif ?>
            </div>
        </div>

        <?php if ($renewal && $qr): ?>
            <div class="form-preview" style="text-align:center;margin-bottom:1.5rem;padding:1.5rem;border:1px solid #e1e5e9;border-radius:6px;background:#fff">
                <h4 style="margin-top:0">Paga tu renovación: <?= e($bs($renewal->amount)) ?></h4>
                <?php if ($qr->qr_image): ?>
                    <img src="data:image/png;base64,<?= e($qr->qr_image) ?>" alt="QR de pago" style="width:240px;height:240px;image-rendering:pixelated">
                <?php endif ?>
                <ol style="text-align:left;display:inline-block;margin:1rem 0 0;padding-left:1.2rem">
                    <li>Abre la app de tu banco y elige <strong>Pagar con QR</strong>.</li>
                    <li>Escanea este código (puedes subir una captura desde la galería).</li>
                    <li>Confirma el monto: <strong><?= e($bs($renewal->amount)) ?></strong>.</li>
                    <li>Listo: esta pantalla se actualiza sola y tu plan se extiende al instante.</li>
                </ol>
                <p class="help-block" style="margin-top:1rem">Referencia: <?= e($renewal->payment_reference) ?></p>
            </div>
        <?php elseif (in_array($state, ['soon', 'overdue'], true)): ?>
            <div style="text-align:center;margin-bottom:1.5rem">
                <button type="button" class="btn btn-primary btn-lg" data-request="onRenew" data-request-loading="#myplan-body">
                    <i class="icon-credit-card"></i> Renovar ahora
                </button>
                <p class="help-block">Generamos un QR para pagar desde la app de tu banco.</p>
            </div>
        <?php endif ?>

        <?php if ($history->count()): ?>
            <h4>Pagos anteriores</h4>
            <table class="table data" style="background:#fff">
                <thead><tr><th>Fecha</th><th>Periodo</th><th>Monto</th></tr></thead>
                <tbody>
                <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= e($h->paid_at?->format('d/m/Y')) ?></td>
                        <td><?= $h->period === 'annual' ? 'Anual' : 'Mensual' ?></td>
                        <td><?= e($bs($h->amount)) ?></td>
                    </tr>
                <?php endforeach ?>
                </tbody>
            </table>
        <?php endif ?>

        <p class="help-block">¿Dudas con tu pago? Escribe a soporte y lo revisamos.</p>
    <?php endif ?>
</div>
