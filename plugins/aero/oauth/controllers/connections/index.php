<div class="layout-row min-size">
<div class="padded-container">
<?php foreach ($providers as $code => $provider): $identity = $mine[$code] ?? null; ?>
    <div class="callout callout-<?= $identity ? 'success' : 'info' ?>" style="max-width:720px">
        <div class="header"><h3><?= e($provider->label()) ?></h3></div>
        <div class="content">
            <?= Form::open() ?>
            <input type="hidden" name="provider" value="<?= e($code) ?>">
            <?php if (!$provider->isConfigured()): ?>
                <p>Este proveedor aún no está configurado en el servidor (faltan credenciales).</p>
            <?php elseif ($identity): ?>
                <p>Conectada como <strong><?= e($identity->email) ?></strong>.
                   Permisos concedidos: <?= e(count((array) $identity->granted_scopes)) ?>.</p>
            <?php endif ?>

            <?php if ($provider->isConfigured()): ?>
                <?php foreach (($scopes[$code] ?? []) as $key => $def): $has = $identity?->hasScopes((array) $def['scopes']); ?>
                    <label style="display:block;margin:6px 0">
                        <input type="checkbox" name="scopes[]" value="<?= e($key) ?>" <?= $has ? 'checked disabled' : 'checked' ?>>
                        <?= e($def['label']) ?> <?= $has ? '<em>(concedido)</em>' : '' ?>
                    </label>
                <?php endforeach ?>
                <button type="button" class="btn btn-primary" data-request="onConnect">
                    <?= $identity ? 'Ampliar permisos / reconectar' : 'Conectar' ?>
                </button>
                <?php if ($identity): ?>
                    <button type="button" class="btn btn-default" data-request="onDisconnect"
                            data-request-confirm="¿Desconectar esta cuenta? Se revocan los permisos concedidos.">Desconectar</button>
                <?php endif ?>
            <?php endif ?>
            <?= Form::close() ?>
        </div>
    </div>
<?php endforeach ?>

<?php if ($everyone->count()): ?>
    <h4>Todas las cuentas vinculadas</h4>
    <table class="table data" style="max-width:900px">
        <thead><tr><th>Usuario</th><th>Proveedor</th><th>Cuenta</th><th>Último acceso</th></tr></thead>
        <tbody>
        <?php foreach ($everyone as $row): ?>
            <tr><td><?= e($row->user?->login) ?></td><td><?= e($row->provider) ?></td>
                <td><?= e($row->email) ?></td><td><?= e($row->last_login_at) ?></td></tr>
        <?php endforeach ?>
        </tbody>
    </table>
<?php endif ?>
</div>
</div>
