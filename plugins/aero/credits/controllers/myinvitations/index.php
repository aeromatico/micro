<?php Block::put('breadcrumb') ?>
    <ul class="breadcrumb">
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<?php if (!$tenantId): ?>
    <p class="text-muted">Elige primero un sitio (tenant) arriba a la derecha.</p>
<?php else: ?>
    <div class="form-group">
        <form data-request="onSendInvitation" data-request-success="">
            <div class="row">
                <div class="col-xs-4">
                    <select name="channel" class="form-control custom-select">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Correo</option>
                    </select>
                </div>
                <div class="col-xs-5">
                    <input type="text" name="recipient" class="form-control" placeholder="Número de WhatsApp o correo" required>
                </div>
                <div class="col-xs-3">
                    <button type="submit" class="btn btn-primary btn-block" <?= $remaining <= 0 ? 'disabled' : '' ?>>Invitar</button>
                </div>
            </div>
        </form>
        <p class="help-block">Te quedan <strong><?= $remaining ?></strong> de <?= $allowed ?> invitaciones. Cada invitado recibe el plan/periodo gratis que te asignó el equipo de Market.</p>
    </div>

    <div id="invitations-panel">
        <?= $this->makePartial('invitations_panel') ?>
    </div>
<?php endif ?>
