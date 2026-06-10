<?= $this->formRenderDesign() ?>

<div style="margin:32px 0 24px; padding:16px 20px; background:#fff5f5; border:1px solid #f5c6cb; border-radius:4px">
    <h5 style="color:#721c24; margin:0 0 6px; font-size:13px; text-transform:uppercase; letter-spacing:.5px">
        <i class="oc-icon-warning"></i> Zona peligrosa
    </h5>
    <p style="color:#721c24; margin:0 0 12px; font-size:13px">
        Elimina este tenant y <strong>todos sus datos</strong>: páginas, SEO, contacto,
        canales de notificación, mensajes recibidos, tokens API, dominios, usuario backend
        y usuario frontend. <strong>Esta acción es irreversible.</strong>
    </p>
    <button
        type="button"
        class="btn btn-danger btn-sm"
        data-request="update_onDelete"
        data-request-confirm="¿Confirmas la eliminación permanente de este tenant y TODOS sus datos? No se puede deshacer."
        data-load-indicator="Eliminando..."
    >
        <i class="oc-icon-trash-o"></i> Eliminar tenant permanentemente
    </button>
</div>
