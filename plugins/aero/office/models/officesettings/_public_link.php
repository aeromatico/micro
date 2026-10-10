<?php
$tenant = $formModel->tenant_id && class_exists(\Aero\Sites\Models\Tenant::class)
    ? \Aero\Sites\Models\Tenant::find($formModel->tenant_id) : null;
$url = $tenant ? request()->getScheme() . '://' . $tenant->primary_domain . '/reservas' : null;
?>
<div class="form-group span-full">
    <label>Enlace público de reservas</label>
    <?php if ($url): ?>
        <input type="text" class="form-control" readonly value="<?= e($url) ?>" onclick="this.select()">
        <p class="help-block"><?= $formModel->public_enabled ? 'Compártelo con tus clientes.' : 'Aún no está publicado: activa «Publicar portal de reservas».' ?></p>
    <?php else: ?>
        <p class="help-block">Se mostrará cuando el negocio tenga dominio.</p>
    <?php endif ?>
</div>
