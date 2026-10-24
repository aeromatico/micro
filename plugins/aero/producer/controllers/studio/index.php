<style>
    .pr-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; padding: 20px; }
    .pr-card { background: #fff; border: 1px solid #e3e6ea; border-radius: 8px; overflow: hidden; display: flex; flex-direction: column; }
    .pr-card video, .pr-card .pr-ph { width: 100%; aspect-ratio: 9 / 12; object-fit: cover; background: #1f2937; display: block; }
    .pr-card .pr-ph { display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 13px; }
    .pr-body { padding: 14px; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .pr-body h4 { margin: 0; font-size: 16px; }
    .pr-body p { margin: 0; color: #5b6573; font-size: 13px; flex: 1; }
    .pr-meta { display: flex; gap: 6px; flex-wrap: wrap; font-size: 12px; color: #5b6573; }
    .pr-meta span { background: #f1f3f5; border-radius: 10px; padding: 2px 8px; }
</style>
<?php if ($styles->isEmpty()): ?>
    <p class="flash-message static info">Todavía no hay estilos disponibles.</p>
<?php else: ?>
    <div class="pr-grid">
        <?php foreach ($styles as $style): ?>
            <div class="pr-card">
                <?php if ($style->preview_url): ?>
                    <video src="<?= e($style->preview_url) ?>" muted loop playsinline preload="metadata" controls></video>
                <?php else: ?>
                    <div class="pr-ph">Muestra próximamente</div>
                <?php endif ?>
                <div class="pr-body">
                    <h4><?= e($style->name) ?></h4>
                    <p><?= e($style->description) ?></p>
                    <div class="pr-meta">
                        <span><?= e($style->kind_label) ?></span>
                        <span>hasta <?= (int) $style->max_seconds ?> s</span>
                        <span><?= $style->credit_cost > 0 ? (int) $style->credit_cost . ' créditos' : 'sin costo' ?></span>
                    </div>
                    <a href="<?= Backend::url('aero/producer/orders/create?style=' . $style->id) ?>" class="btn btn-primary">Usar este estilo</a>
                </div>
            </div>
        <?php endforeach ?>
    </div>
<?php endif ?>
