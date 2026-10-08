<?php
use Aero\Finance\Classes\CurrentTenant;

$portal = CurrentTenant::isPortal((int) $formModel->tenant_id);
?>
<div class="callout callout-<?= $portal ? 'info' : 'warning' ?>" style="margin-bottom:12px">
    <?php if ($portal): ?>
        <div class="header"><i class="icon-info"></i><h3>Contabilidad del PORTAL</h3></div>
        <div class="content"><p>Este es el libro de la plataforma (tenant master): registra lo que <strong>la plataforma</strong> cobra —renovaciones de plan y recargas de créditos—.
        Es <strong>independiente</strong> de la contabilidad de cada tenant: apagarlo o encenderlo aquí <strong>no afecta</strong> a ningún tenant.</p></div>
    <?php else: ?>
        <div class="header"><i class="icon-warning"></i><h3>Contabilidad de este negocio</h3></div>
        <div class="content"><p>Este libro es <strong>solo de este negocio</strong>: el tenant lo gobierna y es independiente del libro del portal (la plataforma no lo consolida).</p>
        <ul>
            <li><strong>Apagarlo</strong> detiene todo registro (manual y automático) desde ese momento. Los datos y libros se conservan en solo consulta.</li>
            <li><strong>Volver a encenderlo</strong> no recupera lo cobrado mientras estuvo apagado: habrá un hueco en el libro que debe cargarse a mano.</li>
            <li>Si la plataforma lo quita del plan del negocio, el tenant pierde el acceso a las pantallas pero el libro se conserva.</li>
        </ul></div>
    <?php endif ?>
</div>
