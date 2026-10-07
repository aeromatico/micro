<div data-control="toolbar">
    <a href="<?= Backend::url('aero/workflows/workflows/create') ?>" class="btn btn-primary oc-icon-plus">Nuevo workflow</a>
    <button
        class="btn btn-default oc-icon-trash-o"
        disabled="disabled"
        onclick="$(this).data('request-data', { checked: $('.control-list').listWidget('getChecked') })"
        data-request="onDelete"
        data-request-confirm="¿Eliminar los workflows seleccionados con todas sus ejecuciones? No se puede deshacer."
        data-trigger-action="enable"
        data-trigger=".control-list input[type=checkbox]"
        data-trigger-condition="checked"
        data-request-success="$(this).prop('disabled', true)"
        data-stripe-load-indicator>
        Eliminar seleccionados
    </button>
</div>
