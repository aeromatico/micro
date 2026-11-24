<div class="mm-page mm-welcome" data-mm-mode="welcome">
    <?php if (!$tenant): ?>
        <div class="callout callout-warning no-subheader"><div class="header"><h3>No se encontró tu espacio</h3></div></div>
    <?php else: ?>
        <header class="mm-hero">
            <div class="mm-step">Toma menos de un minuto</div>
            <h2>¡Bienvenido a <?= e($tenant->name) ?>! ¿Qué quieres usar?</h2>
            <p>
                <?php if ($niche): ?>
                    Ya marcamos lo básico y lo que sirve para <strong><?= e($niche) ?></strong>.
                <?php else: ?>
                    Ya marcamos lo básico para empezar.
                <?php endif ?>
                Activa o quita lo que quieras; lo puedes cambiar cuando sea en <strong>Mis módulos</strong>.
            </p>
        </header>

        <?= $this->makePartial('groups', ['groups' => $groups]) ?>

        <footer class="mm-footer">
            <span class="mm-count"><strong data-mm-count>0</strong> módulos elegidos</span>
            <span class="mm-footer-actions">
                <button type="button" class="btn btn-link" data-mm-skip>Saltar</button>
                <button type="button" class="btn btn-primary btn-lg" data-mm-finish>Empezar →</button>
            </span>
        </footer>
    <?php endif ?>
</div>
