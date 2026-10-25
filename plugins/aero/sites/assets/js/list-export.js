/* Botón «Exportar» en toda barra de herramientas ligada a una lista (ver ListExporter.php).
   JS puro: este script se carga antes que jQuery. El popup lo abre el data-control="popup" estándar. */
(function () {
    function inject() {
        document.querySelectorAll('.toolbar-widget[data-list-linkage]').forEach(function (bar) {
            var strip = bar.querySelector('.control-toolbar');
            if (!strip || strip.querySelector('.toolbar-export')) return;

            // Dentro del panel de botones (no como item aparte): así la caja de búsqueda sigue a la derecha.
            var panel = strip.querySelector('.toolbar-primary [data-control="toolbar"]') || strip.querySelector('.toolbar-primary');
            if (!panel) return;

            var a = document.createElement('a');
            a.href = 'javascript:;';
            a.className = 'btn btn-secondary toolbar-export';
            a.title = 'Exportar a CSV o PDF';
            a.setAttribute('data-control', 'popup');
            a.setAttribute('data-handler', 'onListExportLoad');
            a.setAttribute('data-extra-data', "list_id: '" + bar.getAttribute('data-list-linkage') + "'");
            a.innerHTML = '<i class="icon-download"></i> Exportar';
            panel.appendChild(a);
        });
    }

    document.addEventListener('change', function (e) {
        if (!e.target.matches || !e.target.matches('[data-list-export-all]')) return;
        var form = e.target.closest('form');
        form && form.querySelectorAll('.list-export-columns input[type=checkbox]').forEach(function (c) { c.checked = e.target.checked; });
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', inject); else inject();
    new MutationObserver(inject).observe(document.documentElement, { childList: true, subtree: true });
})();
