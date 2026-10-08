<?php $feed = $formModel; ?>
<div class="form-group span-full">
    <p>
        <?php if ($feed->job_id): ?><a href="<?= Backend::url('aero/tracking/jobs/update/' . $feed->job_id) ?>">Ver trabajo</a><?php endif ?>
        <?php if ($feed->map_url): ?> · <a href="<?= e($feed->map_url) ?>" target="_blank" rel="noopener">Última posición en el mapa</a><?php endif ?>
        <?php if ($feed->last_polled_at): ?> · <span class="text-muted">Última lectura: <?= e($feed->last_polled_at->diffForHumans()) ?></span><?php endif ?>
    </p>
    <?php if ($feed->last_error): ?><p class="text-danger">Último error: <?= e($feed->last_error) ?></p><?php endif ?>
</div>
