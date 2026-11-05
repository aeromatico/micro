<?php $flash = session('aero_tickets_scanner_link'); $show = $flash && (int) ($flash['id'] ?? 0) === (int) $formModel->id; ?>
<?php if ($show): ?>
<div class="callout callout-success"><div class="content">
    <p><strong>Enlace del escáner (cópialo ahora: no se vuelve a mostrar):</strong></p>
    <input type="text" readonly class="form-control" value="<?= e($flash['url']) ?>" onclick="this.select()">
    <p class="help-block">Ábrelo en el celular de quien validará. Quien tenga este enlace puede validar los eventos asignados: compártelo solo con tu personal. Si se filtra, genera uno nuevo.</p>
</div></div>
<?php else: ?>
<div class="callout callout-info"><div class="content">
    Enlace terminado en <strong>…<?= e($formModel->token_hint) ?></strong>. Estado: <strong><?= $formModel->is_active ? 'activo' : 'desactivado' ?></strong>
    <?php if ($formModel->last_seen_at): ?> · último uso <?= e($formModel->last_seen_at->format('d/m H:i')) ?> (<?= e($formModel->last_seen_agent) ?>)<?php endif ?>.
    Por seguridad el enlace no se guarda en claro; usa «Generar enlace nuevo» si lo perdiste.
</div></div>
<?php endif ?>
