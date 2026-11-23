<?php
$targets = $post->targets()->with('account')->get();
$open = $targets->whereIn('status', ['queued', 'publishing', 'processing'])->isNotEmpty();
$colors = ['published' => '#047857', 'failed' => '#b91c1c', 'cancelled' => '#6b7280'];
$user = BackendAuth::getUser();
$canBoost = $user && ($user->hasAccess('aero.social.ads') || $user->hasAccess('aero.social.superadmin'));
?>
<?php if ($targets->isNotEmpty()): ?>
<div style="background:#fff;border:1px solid #e5e7eb;border-radius:10px;padding:12px 16px;margin-bottom:16px">
    <strong>Estado: <?= e($post->status_label) ?></strong>
    <?php if ($post->scheduled_at && $post->status === 'scheduled'): ?>
        · programado para el <?= e($post->scheduled_at->format('d/m/Y H:i')) ?>
    <?php endif ?>
    <table class="table" style="margin:8px 0 0">
        <?php foreach ($targets as $t): ?>
            <tr>
                <td><?= e($t->account?->displayName() ?? 'Cuenta eliminada') ?></td>
                <td style="color:<?= $colors[$t->status] ?? '#b45309' ?>"><?= e($t->status_label) ?></td>
                <td>
                    <?php if ($t->permalink): ?><a href="<?= e($t->permalink) ?>" target="_blank" rel="noopener">Ver publicación</a><?php endif ?>
                    <?php if ($t->error): ?><span class="text-danger"><?= e($t->error) ?></span><?php endif ?>
                    <?php if ($t->refunded_at && $t->credits_charged): ?><span class="text-muted"> (créditos devueltos)</span><?php endif ?>
                </td>
                <td class="text-right">
                    <?php if ($canBoost && $t->status === 'published' && $t->external_id && in_array($t->network, ['facebook', 'instagram'], true)): ?>
                        <button type="button" class="btn btn-xs btn-primary" data-control="popup" data-handler="onBoostForm"
                                data-request-data="target_id: <?= (int) $t->id ?>">Promocionar</button>
                    <?php endif ?>
                    <?php if ($t->status === 'failed'): ?>
                        <button type="button" class="btn btn-xs btn-default" data-request="onRetryTarget" data-request-data="target_id: <?= (int) $t->id ?>"
                                data-request-confirm="¿Reintentar? Se vuelve a cobrar esta red.">Reintentar</button>
                    <?php endif ?>
                </td>
            </tr>
        <?php endforeach ?>
    </table>
    <?php if ($open): ?>
        <script>setTimeout(function(){ oc.request('#socTargets', 'onRefreshTargets'); }, 5000);</script>
    <?php endif ?>
</div>
<?php endif ?>
