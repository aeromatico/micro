<div data-control="toolbar">
    <a href="<?= Backend::url('aero/medical/ranges/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo rango</a>
    <?php if (\Aero\Medical\Classes\Mode::forTenant(\Aero\Medical\Classes\CurrentTenant::id()) === 'human'): ?>
    <button type="button" class="btn btn-default" data-request="onLoadReference"
            data-request-confirm="Se agregarán rangos de referencia para adultos que aún no tenga (no se cambia ninguno existente). Son un BORRADOR sin revisión médica: confírmelos con un médico. ¿Continuar?">
        Cargar rangos de referencia para adultos (borrador)</button>
    <?php endif ?>
</div>
