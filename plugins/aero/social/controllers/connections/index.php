<?php
$iconFor = fn ($code) => [
    'facebook'  => '<path fill="currentColor" d="M22 12a10 10 0 1 0-11.6 9.9v-7H7.9V12h2.5V9.8c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.5h-1.2c-1.2 0-1.6.8-1.6 1.6V12h2.8l-.4 2.9h-2.3v7A10 10 0 0 0 22 12z"/>',
    'instagram' => '<path fill="currentColor" d="M12 2.2c3.2 0 3.6 0 4.8.1 3.3.1 4.8 1.7 4.9 4.9.1 1.3.1 1.6.1 4.8s0 3.6-.1 4.8c-.1 3.2-1.7 4.8-4.9 4.9-1.3.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-3.3-.1-4.8-1.7-4.9-4.9C2.2 15.6 2.2 15.2 2.2 12s0-3.6.1-4.8C2.4 4 3.9 2.4 7.2 2.3 8.4 2.2 8.8 2.2 12 2.2zM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 8.2a3.2 3.2 0 1 1 0-6.4 3.2 3.2 0 0 1 0 6.4zm5.2-9.6a1.2 1.2 0 1 0 0 2.4 1.2 1.2 0 0 0 0-2.4z"/>',
    'tiktok'    => '<path fill="currentColor" d="M16.6 5.8A4.3 4.3 0 0 1 15.5 3h-3.1v12.4a2.6 2.6 0 1 1-2.6-2.6c.3 0 .5 0 .8.1V9.7a5.8 5.8 0 1 0 5 5.7V9.2a7.3 7.3 0 0 0 4.3 1.4V7.5a4.3 4.3 0 0 1-3.3-1.7z"/>',
    'youtube'   => '<path fill="currentColor" d="M23 7.2a3 3 0 0 0-2.1-2.1C19 4.6 12 4.6 12 4.6s-7 0-8.9.5A3 3 0 0 0 1 7.2 31 31 0 0 0 .5 12a31 31 0 0 0 .5 4.8 3 3 0 0 0 2.1 2.1c1.9.5 8.9.5 8.9.5s7 0 8.9-.5a3 3 0 0 0 2.1-2.1 31 31 0 0 0 .5-4.8 31 31 0 0 0-.5-4.8zM9.8 15V9l5.8 3-5.8 3z"/>',
    'x'         => '<path fill="currentColor" d="M17.8 3h3.2l-7 8 8.3 10h-6.5l-5-6.6L5 21H1.8l7.5-8.6L1.3 3H8l4.6 6.1L17.8 3zm-1.1 16.2h1.8L7.4 4.7H5.5l11.2 14.5z"/>',
][$code] ?? '';
?>
<style>
    .soc-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;margin-top:8px}
    .soc-card{background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:18px;display:flex;flex-direction:column;gap:12px}
    .soc-head{display:flex;align-items:center;gap:10px}
    .soc-head svg{width:28px;height:28px}
    .soc-head h4{margin:0;font-size:16px;font-weight:600}
    .soc-soon{opacity:.55}
    .soc-acc{display:flex;align-items:center;gap:10px;padding:8px 10px;border:1px solid #f0f0f0;border-radius:8px}
    .soc-acc img{width:32px;height:32px;border-radius:50%;object-fit:cover;background:#eee}
    .soc-acc .soc-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .soc-badge{font-size:11px;padding:2px 8px;border-radius:999px;background:#ecfdf5;color:#047857}
    .soc-badge.bad{background:#fef2f2;color:#b91c1c}
    .soc-muted{color:#6b7280;font-size:13px;margin:0}
</style>

<?php if (!$tenantId): ?>
    <p class="flash-message static warning">Tu usuario no pertenece a ninguna cuenta: las conexiones son de cada cuenta.</p>
<?php endif ?>

<?php if ($limit !== null): ?>
    <p class="<?= $used >= $limit ? 'flash-message static warning' : 'soc-muted' ?>">
        Cuentas conectadas: <strong><?= (int) $used ?> de <?= (int) $limit ?></strong> que incluye tu plan.
        <?php if ($used >= $limit): ?><a href="<?= Backend::url('aero/sites/myplan') ?>">Mejorar plan</a><?php endif ?>
    </p>
<?php endif ?>
<p class="soc-muted">Conecta las redes de tu negocio. Las cuentas son tuyas: puedes desconectarlas aquí o desde la propia red cuando quieras.</p>

<div class="soc-grid">
<?php foreach ($networks as $code => $net): ?>
    <?php $list = $accounts[$code] ?? collect(); ?>
    <div class="soc-card <?= $net['available'] ? '' : 'soc-soon' ?>">
        <div class="soc-head" style="color:<?= e($net['color']) ?>">
            <svg viewBox="0 0 24 24"><?= $iconFor($code) ?></svg>
            <h4 style="color:#111"><?= e($net['label']) ?></h4>
        </div>

        <?php foreach ($list as $acc): ?>
            <div class="soc-acc">
                <?php if ($acc->avatar_url): ?><img src="<?= e($acc->avatar_url) ?>" alt=""><?php else: ?><img alt=""><?php endif ?>
                <div class="soc-name" title="<?= e($acc->last_error) ?>">
                    <?= e($acc->name ?: $acc->external_id) ?><?= $acc->username ? ' <span class="soc-muted">@' . e($acc->username) . '</span>' : '' ?>
                </div>
                <?php if ($acc->isActive()): ?>
                    <span class="soc-badge">Conectada</span>
                <?php else: ?>
                    <span class="soc-badge bad">Reconectar</span>
                <?php endif ?>
                <?php if ($code !== 'instagram'): ?>
                    <a href="javascript:;" class="text-danger" title="Desconectar"
                       data-request="onDisconnect" data-request-data="id: <?= (int) $acc->id ?>"
                       data-request-confirm="¿Desconectar «<?= e($acc->name) ?>»?<?= $code === 'facebook' ? ' También se desconecta su Instagram.' : '' ?>">×</a>
                <?php endif ?>
            </div>
        <?php endforeach ?>

        <?php if (!$net['available']): ?>
            <p class="soc-muted">Próximamente.</p>
        <?php elseif ($code === 'instagram' && $list->isEmpty()): ?>
            <p class="soc-muted">Instagram se conecta junto con Facebook: debe ser una cuenta profesional (empresa o creador) vinculada a tu página.</p>
        <?php elseif ($net['connect'] === 'meta' && $code === 'facebook'): ?>
            <?php if ($metaReady && $tenantId): ?>
                <a href="<?= Backend::url('aero/social/connections/connect/meta') ?>" class="btn btn-primary" style="background:#1877F2;border-color:#1877F2">
                    <?= $list->isEmpty() ? 'Conectar con Facebook' : 'Agregar o reconectar páginas' ?>
                </a>
                <p class="soc-muted">Se abre Facebook: elige tus páginas y, si tienes, sus cuentas de Instagram.</p>
            <?php else: ?>
                <p class="soc-muted">La conexión con Meta aún no está habilitada en la plataforma.</p>
            <?php endif ?>
        <?php elseif ($net['connect'] === 'oauth'): ?>
            <?php if (!empty($oauthReady[$code]) && $tenantId): ?>
                <a href="<?= Backend::url('aero/social/connections/connect/' . $code) ?>" class="btn btn-primary" style="background:<?= e($net['color']) ?>;border-color:<?= e($net['color']) ?>">
                    <?= $list->isEmpty() ? 'Conectar ' . e($net['label']) : 'Agregar o reconectar' ?>
                </a>
                <?php if ($code === 'tiktok' && !config('aero.social::tiktok.audited')): ?>
                    <p class="soc-muted">Por ahora los videos se publican en privado (solo tú los ves) hasta que TikTok apruebe la app.</p>
                <?php endif ?>
            <?php else: ?>
                <p class="soc-muted">Aún no está habilitada en la plataforma.
                    <?php if (in_array($code, $byo, true)): ?>Puedes usar tu propia app en <a href="<?= Backend::url('aero/social/apps') ?>">Llaves propias</a>.<?php endif ?>
                </p>
            <?php endif ?>
        <?php endif ?>
    </div>
<?php endforeach ?>
</div>
