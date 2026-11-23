<?php
$money = fn ($v, $cur) => $v === null ? '—' : number_format((float) $v, 2, ',', '.') . ' ' . e($cur);
$num = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', '.');
?>
<style>
    .ads-row{display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin-bottom:14px}
    .ads-kpis{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:12px;margin:12px 0 18px}
    .ads-kpi{background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 14px}
    .ads-kpi small{display:block;color:#6b7280;font-size:12px}
    .ads-kpi strong{font-size:20px;font-variant-numeric:tabular-nums}
    .ads-table td,.ads-table th{vertical-align:middle!important}
    .ads-num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
    .ads-pill{font-size:11px;padding:2px 8px;border-radius:999px;background:#f3f4f6;color:#374151;white-space:nowrap}
    .ads-pill.on{background:#ecfdf5;color:#047857}
    .ads-muted{color:#6b7280;font-size:13px}
</style>

<?php if (!$tenantId): ?>
    <p class="flash-message static warning">Tu usuario no pertenece a ninguna cuenta: los anuncios son de cada cuenta.</p>
<?php elseif ($accounts->isEmpty()): ?>
    <div class="ads-kpi" style="max-width:560px">
        <h4 style="margin-top:0">Conecta tu cuenta publicitaria de Meta</h4>
        <p class="ads-muted">Verás el gasto y los resultados de tus campañas de Facebook e Instagram, podrás pausarlas o cambiar su presupuesto y promocionar tus publicaciones. El gasto lo pagas directamente a Meta con tu medio de pago.</p>
        <?php if ($metaReady): ?>
            <a href="<?= Backend::url('aero/social/ads/connect') ?>" class="btn btn-primary" style="background:#1877F2;border-color:#1877F2">Conectar Meta Ads</a>
        <?php else: ?>
            <p class="ads-muted">La conexión con Meta aún no está habilitada en la plataforma.</p>
        <?php endif ?>
    </div>
<?php else: ?>

<div class="ads-row">
    <form method="get" class="form-inline" style="display:flex;gap:8px;align-items:center">
        <select name="account" class="form-control" onchange="this.form.submit()">
            <?php foreach ($accounts as $acc): ?>
                <option value="<?= (int) $acc->id ?>" <?= $current && $acc->id === $current->id ? 'selected' : '' ?>>
                    <?= e($acc->displayName()) ?><?= $acc->isActive() ? '' : ' — reconectar' ?>
                </option>
            <?php endforeach ?>
        </select>
        <select name="days" class="form-control" onchange="this.form.submit()">
            <?php foreach ([7 => 'Últimos 7 días', 30 => 'Últimos 30 días', 90 => 'Últimos 90 días'] as $d => $label): ?>
                <option value="<?= $d ?>" <?= $days === $d ? 'selected' : '' ?>><?= $label ?></option>
            <?php endforeach ?>
        </select>
    </form>
    <?php if ($current->isActive()): ?>
        <button class="btn btn-default oc-icon-refresh" data-request="onSync" data-request-data="account_id: <?= (int) $current->id ?>" data-load-indicator="Actualizando…">Actualizar</button>
    <?php endif ?>
    <?php if ($metaReady): ?>
        <a href="<?= Backend::url('aero/social/ads/connect') ?>" class="btn btn-default"><?= $current->isActive() ? 'Agregar cuentas' : 'Reconectar Meta Ads' ?></a>
    <?php endif ?>
    <a href="javascript:;" class="text-danger" data-request="onDisconnect" data-request-data="account_id: <?= (int) $current->id ?>"
       data-request-confirm="¿Desconectar esta cuenta publicitaria? Sus campañas siguen activas en Meta.">Desconectar</a>
    <span class="ads-muted">
        <?= $current->synced_at ? 'Actualizado ' . e($current->synced_at->diffForHumans()) : 'Aún sin sincronizar' ?>
    </span>
</div>

<?php if (!$current->isActive()): ?>
    <p class="flash-message static warning">La conexión con esta cuenta venció. Pulsa «Reconectar Meta Ads». <?= e($current->last_error) ?></p>
<?php elseif ($current->expiresSoon()): ?>
    <p class="flash-message static warning">La conexión vence el <?= e($current->token_expires_at->format('d/m/Y')) ?>. Reconéctala antes para no perder la sincronización.</p>
<?php elseif ($current->last_error): ?>
    <p class="flash-message static warning"><?= e($current->last_error) ?></p>
<?php endif ?>
<?php if ($current->account_status !== null && (int) $current->account_status !== 1): ?>
    <p class="flash-message static warning">Meta reporta esta cuenta publicitaria como inactiva o con un problema de pago. Revísala en el Administrador de anuncios.</p>
<?php endif ?>

<div class="ads-kpis">
    <div class="ads-kpi"><small>Gasto</small><strong><?= $money($kpis['spend'], $current->currency) ?></strong></div>
    <div class="ads-kpi"><small>Alcance</small><strong><?= $num($kpis['reach']) ?></strong></div>
    <div class="ads-kpi"><small>Impresiones</small><strong><?= $num($kpis['impressions']) ?></strong></div>
    <div class="ads-kpi"><small>Clics</small><strong><?= $num($kpis['clicks']) ?></strong></div>
    <div class="ads-kpi"><small>Costo por clic</small><strong><?= $money($kpis['cpc'], $current->currency) ?></strong></div>
    <div class="ads-kpi"><small>CTR</small><strong><?= $kpis['ctr'] === null ? '—' : number_format($kpis['ctr'], 2, ',', '.') . ' %' ?></strong></div>
</div>
<p class="ads-muted" style="margin-top:-8px">El alcance se suma por día y campaña: puede contar dos veces a la misma persona.</p>

<?php if ($campaigns->isEmpty()): ?>
    <p class="ads-muted">No hay campañas en esta cuenta. Puedes promocionar una publicación desde «Publicaciones».</p>
<?php else: ?>
<table class="table data ads-table">
    <thead><tr>
        <th>Campaña</th><th>Estado</th><th class="ads-num">Presupuesto</th>
        <th class="ads-num">Gasto</th><th class="ads-num">Impresiones</th><th class="ads-num">Clics</th><th></th>
    </tr></thead>
    <tbody>
    <?php foreach ($campaigns as $c): ?>
        <?php $owner = $c->budgetOwner(); ?>
        <tr>
            <td><?= e($c->name) ?><?php if ($c->post_target_id): ?> <span class="ads-pill">Promoción</span><?php endif ?></td>
            <td><span class="ads-pill <?= $c->isActive() ? 'on' : '' ?>"><?= e($c->statusLabel()) ?></span></td>
            <td class="ads-num">
                <?php if ($owner): ?>
                    <?= $money($current->toMajor($owner->daily_budget ?: $owner->lifetime_budget), $current->currency) ?>
                    <span class="ads-muted"><?= $owner->daily_budget ? '/día' : 'total' ?></span>
                <?php else: ?>
                    <span class="ads-muted">Por conjunto</span>
                <?php endif ?>
            </td>
            <td class="ads-num"><?= $money($c->spend_sum ?? 0, $current->currency) ?></td>
            <td class="ads-num"><?= $num($c->impressions_sum ?? 0) ?></td>
            <td class="ads-num"><?= $num($c->clicks_sum ?? 0) ?></td>
            <td class="text-right" style="white-space:nowrap">
                <?php if ($current->isActive() && in_array($c->status, ['ACTIVE', 'PAUSED'], true)): ?>
                    <button class="btn btn-xs btn-default" data-request="onToggle" data-request-data="campaign_id: <?= (int) $c->id ?>"
                            data-request-confirm="<?= $c->isActive() ? '¿Pausar esta campaña?' : '¿Activar esta campaña? Empezará a gastar.' ?>">
                        <?= $c->isActive() ? 'Pausar' : 'Activar' ?>
                    </button>
                    <?php if ($owner): ?>
                        <button class="btn btn-xs btn-default" data-control="popup" data-handler="onBudgetForm"
                                data-request-data="campaign_id: <?= (int) $c->id ?>">Presupuesto</button>
                    <?php endif ?>
                <?php endif ?>
            </td>
        </tr>
    <?php endforeach ?>
    </tbody>
</table>
<?php endif ?>
<?php endif ?>
