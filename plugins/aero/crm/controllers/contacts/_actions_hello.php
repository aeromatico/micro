<div class="padded-container">
    <h4>Comunicación</h4>

    <?php if (!class_exists(\Aero\Hello\Models\Contact::class)): ?>
        <p class="help-block">El plugin Aero.Hello no está instalado.</p>
    <?php elseif (!$model->hello_contact_id): ?>
        <p class="help-block">Este contacto todavía no está vinculado a Hello (WhatsApp/email).</p>
        <button
            type="button"
            class="btn btn-default"
            data-request="onLinkHelloContact"
            data-request-data="record_id: <?= $model->id ?>"
            data-request-flash>
            <i class="icon-link"></i> Vincular con Hello
        </button>
    <?php else: ?>
        <form data-request="onSendMessage" data-request-flash>
            <input type="hidden" name="record_id" value="<?= $model->id ?>">
            <div class="form-group">
                <select name="message_type" class="form-control custom-select" style="max-width:200px;margin-bottom:8px">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="email">Email</option>
                </select>
            </div>
            <div class="form-group">
                <textarea name="message_body" class="form-control" rows="3" placeholder="Escribe el mensaje..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary" data-load-indicator="Enviando...">
                <i class="icon-send"></i> Enviar mensaje
            </button>
        </form>
    <?php endif; ?>
</div>
