<?php if ($record->lat !== null && $record->lng !== null && $record->lat !== '' && $record->lng !== ''): ?>
    <a href="https://www.google.com/maps?q=<?= e($record->lat) ?>,<?= e($record->lng) ?>" target="_blank" rel="noopener noreferrer"
       onclick="event.stopPropagation()" title="Abrir la ubicación en el mapa" aria-label="Ver <?= e($record->title) ?> en el mapa">📍 Ver mapa</a>
<?php else: ?>
    <span class="text-muted" title="Esta propiedad aún no tiene ubicación en el mapa">—</span>
<?php endif ?>
