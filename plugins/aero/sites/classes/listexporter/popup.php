<?= Form::open() ?>
    <input type="hidden" name="list_id" value="<?= e($listId) ?>">
    <div class="modal-header">
        <h4 class="modal-title">Exportar lista</h4>
        <button type="button" class="btn-close" data-dismiss="popup"></button>
    </div>
    <div class="modal-body">
        <p class="form-text before-field">Elige las columnas del archivo. Se exportan los registros que ves ahora (con tus filtros y búsqueda).</p>

        <div class="list-export-all">
            <label><input type="checkbox" data-list-export-all checked> Todas las columnas</label>
        </div>
        <div class="list-export-columns">
            <?php foreach ($columns as $name => $c): ?>
                <label class="list-export-col">
                    <input type="checkbox" name="columns[]" value="<?= e($name) ?>" <?= $c['checked'] ? 'checked' : '' ?>>
                    <?= e(trim(strip_tags(__($c['column']->label)))) ?>
                </label>
            <?php endforeach ?>
        </div>

        <div class="list-export-options">
            <label class="list-export-opt"><input type="radio" name="format" value="csv" checked> CSV (Excel)</label>
            <label class="list-export-opt"><input type="radio" name="format" value="pdf"> PDF</label>
            <select name="delimiter" class="form-control custom-select select-no-search list-export-delim">
                <option value=";">CSV separado por punto y coma (Excel en español)</option>
                <option value=",">CSV separado por coma</option>
            </select>
        </div>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-request="onListExportRun" data-request-data="list_id: '<?= e($listId) ?>'" data-stripe-load-indicator>
            Descargar
        </button>
        <span class="button-separator">o</span>
        <button type="button" class="btn btn-link text-muted" data-dismiss="popup">Cancelar</button>
    </div>
<?= Form::close() ?>
