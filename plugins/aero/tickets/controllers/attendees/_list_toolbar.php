<div data-control="toolbar">
    <a href="<?= Backend::url('aero/tickets/attendees/create') ?>" class="btn btn-primary oc-icon-plus">Registrar asistente</a>
    <button class="btn btn-default oc-icon-check" disabled="disabled" data-list-checked-trigger data-request="onMarkPaid"
            data-request-confirm="¿Aprobar / marcar como pagadas las entradas seleccionadas?" data-list-checked-request>Aprobar / marcar pagada</button>
    <button class="btn btn-default oc-icon-envelope" disabled="disabled" data-list-checked-trigger data-request="onResend"
            data-request-confirm="¿Reenviar por correo las entradas de las órdenes seleccionadas?" data-list-checked-request>Reenviar entradas</button>
    <button class="btn btn-default oc-icon-ban" disabled="disabled" data-list-checked-trigger data-request="onVoid"
            data-request-confirm="¿Anular las entradas seleccionadas?" data-list-checked-request>Anular</button>
</div>
