<?php /** @var \Aero\Workspaces\Controllers\Charges $this */ ?>
<?php $t = $report['totals'] ?>
<div class="layout-row min-size">
    <div class="padded-container">
        <?php if (!$report['charging']): ?>
            <p class="callout callout-warning no-subheader"><span class="header"><i class="icon-warning"></i> El cobro está apagado (Ajustes → Workspaces). Estas cifras son lo cobrado mientras estuvo encendido.</span></p>
        <?php endif ?>

        <p>
            Periodo:
            <?php foreach ([7 => '7 días', 30 => '30 días', 90 => '90 días', 365 => '1 año'] as $d => $label): ?>
                <a href="?days=<?= $d ?>" class="btn btn-sm <?= $report['days'] === $d ? 'btn-primary' : 'btn-default' ?>"><?= e($label) ?></a>
            <?php endforeach ?>
        </p>

        <table class="table data" style="max-width:640px">
            <thead><tr><th>Concepto</th><th style="text-align:right">Puntos</th></tr></thead>
            <tbody>
                <tr><td>Contrataciones</td><td style="text-align:right"><?= (int) $t['hire'] ?></td></tr>
                <tr><td>Mensajes de chat</td><td style="text-align:right"><?= (int) $t['turn'] ?></td></tr>
                <tr><td>Encargos</td><td style="text-align:right"><?= (int) $t['task'] ?></td></tr>
                <tr><td>Reembolsos por encargos cancelados</td><td style="text-align:right">−<?= (int) $t['refunded'] ?></td></tr>
                <tr><td><strong>Neto</strong></td><td style="text-align:right"><strong><?= (int) $t['net'] ?></strong></td></tr>
            </tbody>
        </table>

        <h4>Por agente (contrataciones y mensajes)</h4>
        <table class="table data">
            <thead><tr><th>Agente</th><th style="text-align:right">Contrataciones</th><th style="text-align:right">Pts contratación</th><th style="text-align:right">Mensajes</th><th style="text-align:right">Pts mensajes</th><th style="text-align:right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($report['agents'] as $a): ?>
                <tr><td><?= e($a['name']) ?></td><td style="text-align:right"><?= (int) $a['hires'] ?></td><td style="text-align:right"><?= (int) $a['hire'] ?></td><td style="text-align:right"><?= (int) $a['messages'] ?></td><td style="text-align:right"><?= (int) $a['turns'] ?></td><td style="text-align:right"><strong><?= (int) $a['total'] ?></strong></td></tr>
            <?php endforeach ?>
            <?php if (!$report['agents']): ?><tr><td colspan="6" class="text-muted">Sin cobros en el periodo.</td></tr><?php endif ?>
            </tbody>
        </table>

        <h4>Por tenant</h4>
        <table class="table data">
            <thead><tr><th>Tenant</th><th style="text-align:right">Contrataciones</th><th style="text-align:right">Mensajes</th><th style="text-align:right">Encargos</th><th style="text-align:right">Total</th></tr></thead>
            <tbody>
            <?php foreach ($report['tenants'] as $r): ?>
                <tr><td><?= e($tenantNames[$r['tenant_id']] ?? '#' . $r['tenant_id']) ?></td><td style="text-align:right"><?= (int) $r['hire'] ?></td><td style="text-align:right"><?= (int) $r['turn'] ?></td><td style="text-align:right"><?= (int) $r['task'] ?></td><td style="text-align:right"><strong><?= (int) $r['total'] ?></strong></td></tr>
            <?php endforeach ?>
            <?php if (!$report['tenants']): ?><tr><td colspan="5" class="text-muted">Sin cobros en el periodo.</td></tr><?php endif ?>
            </tbody>
        </table>
    </div>
</div>
