<div data-control="toolbar">
    <a href="<?= Backend::url('aero/medical/diagnosiscatalogs/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo diagnóstico</a>
    <?php if (\Aero\Medical\Classes\Mode::forTenant(\Aero\Medical\Classes\CurrentTenant::id()) === 'human'): ?>
    <button type="button" class="btn btn-default" data-request="onLoadStarter"
            data-request-confirm="Se agregarán diagnósticos frecuentes de medicina general (CIE-10) que aún no tenga. Es una selección de partida SIN revisión médica: ajústela. ¿Continuar?">Cargar lista inicial de medicina general</button>
    <?php endif ?>
</div>
