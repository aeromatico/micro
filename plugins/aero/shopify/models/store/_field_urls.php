<?php $store = $this->model; ?>
<div class="form-group">
    <p><strong>URL del webhook</strong> (tema <code>orders/create</code>, formato JSON):</p>
    <input type="text" class="form-control" readonly onclick="this.select()" value="<?= e($store->webhookUrl()) ?>">
    <p class="help-block" style="margin-top:12px">
        <strong>Página para que el cliente vea su QR</strong> (pégala en las instrucciones del método de pago manual):
    </p>
    <input type="text" class="form-control" readonly onclick="this.select()" value="<?= e($store->lookupUrl()) ?>">
    <p class="help-block" style="margin-top:12px">
        Token: <?= $store->access_token ? '✔ guardado' : '✖ falta' ?> ·
        Secreto: <?= $store->client_secret ? '✔ guardado' : '✖ falta' ?>
        <?php if ($store->last_error): ?><br><span class="text-danger">Último error: <?= e($store->last_error) ?></span><?php endif ?>
    </p>
</div>
