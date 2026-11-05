<?php if ($record->status === 'active'): ?>
    <a href="<?= e($record->whatsapp_url) ?>" target="_blank" rel="noopener"
       onclick="$.request('onMarkSent',{data:{id:<?= (int) $record->id ?>}});event.stopPropagation()" title="Enviar por WhatsApp">WhatsApp</a>
    ·
    <a href="#" onclick="event.stopPropagation();event.preventDefault();navigator.clipboard&&navigator.clipboard.writeText('<?= e($record->invite_url) ?>');$.request('onMarkSent',{data:{id:<?= (int) $record->id ?>}});this.textContent='¡Copiado!'">Copiar enlace</a>
<?php else: ?>
    <span class="text-muted">—</span>
<?php endif ?>
