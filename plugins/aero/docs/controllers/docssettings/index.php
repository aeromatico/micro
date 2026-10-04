<?php Block::put('breadcrumb') ?>
    <ul><li><a href="<?= Backend::url('aero/docs/articles') ?>">Docs</a></li><li><?= e($this->pageTitle) ?></li></ul>
<?php Block::endPut() ?>
<div class="layout">
    <div class="layout-row">
        <div class="padded-container">
            <?php if ($isPlatform): ?>
                <p class="text-muted">La documentación de la plataforma está siempre disponible en el portal. Este interruptor es de cada tenant.</p>
            <?php else: ?>
                <form data-request="onSave" data-request-flash>
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>>
                            Activar la documentación en mi sitio
                        </label>
                        <p class="help-block">Si está desactivada, tu sitio no muestra la sección de documentación ni sus enlaces. Viene desactivada por defecto.</p>
                    </div>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </form>
            <?php endif ?>
        </div>
    </div>
</div>
