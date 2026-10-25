/* Botón «Exportar» en toda barra de herramientas ligada a una lista (ver ListExporter.php). */
(function ($) {
    function inject() {
        $('.toolbar-widget[data-list-linkage]').each(function () {
            var $bar = $(this).find('.control-toolbar').first();
            if (!$bar.length || $bar.find('.toolbar-export').length) return;

            var listId = $(this).attr('data-list-linkage');
            var $item = $('<div class="toolbar-item toolbar-export"></div>');
            var $a = $('<a href="javascript:;" title="Exportar a CSV o PDF"><i class="icon-download"></i>&nbsp;Exportar</a>');
            $a.on('click', function () {
                $(this).popup({ handler: 'onListExportLoad', extraData: { list_id: listId } });
            });
            $item.append($a);

            var $setup = $bar.find('.toolbar-setup');
            if ($setup.length) $item.insertBefore($setup); else $bar.append($item);
        });
    }

    $(document).on('change', '[data-list-export-all]', function () {
        $(this).closest('form').find('.list-export-columns input[type=checkbox]').prop('checked', this.checked);
    });

    $(document).ready(inject);
    $(window).on('ajaxUpdateComplete', inject);
    $(document).on('render', inject);
})(jQuery);
