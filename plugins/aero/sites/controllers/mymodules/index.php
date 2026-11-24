<div class="mm-page" data-mm-mode="live">
    <?php if (!$tenant): ?>
        <div class="callout callout-warning no-subheader"><div class="header"><h3>No se encontró tu espacio</h3></div></div>
    <?php else: ?>
        <header class="mm-head">
            <div>
                <h2>Mis módulos</h2>
                <p>Enciende solo lo que usas: lo apagado sale de tu menú y <strong>tus datos se guardan</strong> por si lo vuelves a encender.</p>
            </div>
        </header>

        <?php if ($off): ?>
            <div class="mm-notice">
                <strong><?= e($off) ?></strong> está apagado.
                <button type="button" class="btn btn-primary btn-sm" data-mm-enable="<?= e($offCode) ?>">Encender ahora</button>
            </div>
        <?php endif ?>

        <div class="mm-tools">
            <input type="search" class="form-control mm-search" placeholder="Buscar módulo…" data-mm-search>
            <div class="mm-chips" data-mm-filter>
                <button type="button" class="is-active" data-filter="all">Todos</button>
                <button type="button" data-filter="on">Encendidos</button>
                <button type="button" data-filter="off">Apagados</button>
            </div>
        </div>

        <div class="mm-saved" data-mm-saved hidden>
            <span data-mm-saved-text></span>
            <a href="javascript:location.reload()">Actualizar menú</a>
        </div>

        <?= $this->makePartial('groups', ['groups' => $groups]) ?>
    <?php endif ?>
</div>
