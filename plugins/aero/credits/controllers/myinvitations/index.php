<?php Block::put('breadcrumb') ?>
    <ul class="breadcrumb">
        <li><?= e($this->pageTitle) ?></li>
    </ul>
<?php Block::endPut() ?>

<style>
    .inv-wrap { max-width: 980px; padding: 20px 4px 40px; display: flex; flex-direction: column; gap: 20px; }
    .inv-cards { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; }
    @media (max-width: 800px) { .inv-cards { grid-template-columns: 1fr; } }
    .inv-card { border: 1px solid rgba(128,128,128,.25); border-radius: 8px; padding: 20px 22px; background: rgba(128,128,128,.06); display: flex; flex-direction: column; gap: 14px; }
    .inv-card h3 { margin: 0; font-size: 16px; font-weight: 600; }
    .inv-card p { margin: 0; opacity: .8; line-height: 1.5; }
    .inv-badge { align-self: flex-start; font-size: 12px; padding: 3px 10px; border-radius: 999px; background: rgba(99,102,241,.18); color: #a5b4fc; }
    .inv-form { display: flex; gap: 10px; flex-wrap: wrap; }
    .inv-form select { flex: 0 0 150px; }
    .inv-form input { flex: 1 1 220px; }
    .inv-form .btn { flex: 0 0 auto; padding-left: 24px; padding-right: 24px; }
    .inv-quota { font-size: 13px; opacity: .75; }
    .inv-card .btn { align-self: flex-start; }
    .inv-list-title { margin: 0 0 10px; font-size: 16px; font-weight: 600; }
</style>

<?php if (!$tenantId): ?>
    <p class="text-muted">Elige primero un sitio (tenant) arriba a la derecha.</p>
<?php else: ?>
    <div class="inv-wrap">
        <div class="inv-cards">
            <div class="inv-card">
                <h3>Invitar a un amigo</h3>
                <span class="inv-badge">Plan Trial · 7 días</span>
                <p>Tu invitado recibe <strong>todas las funciones del plan PRO por 7 días</strong>, gratis.</p>
                <form data-request="onSendInvitation" data-request-success="" class="inv-form">
                    <select name="channel" class="form-control custom-select">
                        <option value="whatsapp">WhatsApp</option>
                        <option value="email">Correo</option>
                    </select>
                    <input type="text" name="recipient" class="form-control" placeholder="Número de WhatsApp o correo" required>
                    <button type="submit" class="btn btn-primary" <?= $remaining <= 0 ? 'disabled' : '' ?>>Invitar</button>
                </form>
                <div class="inv-quota">Te quedan <strong><?= $remaining ?></strong> de <?= $allowed ?> invitaciones.</div>
            </div>

            <div class="inv-card">
                <h3>Regalar una suscripción</h3>
                <p>¿Quieres dar más que una prueba? Regala una suscripción de pago a quien tú elijas.</p>
                <a href="/regalar" target="_blank" rel="noopener" class="btn btn-default">
                    <i class="icon-gift"></i>&nbsp; Regalar suscripción
                </a>
            </div>
        </div>

        <div>
            <h3 class="inv-list-title">Invitaciones enviadas</h3>
            <div id="invitations-panel">
                <?= $this->makePartial('invitations_panel') ?>
            </div>
        </div>
    </div>
<?php endif ?>
