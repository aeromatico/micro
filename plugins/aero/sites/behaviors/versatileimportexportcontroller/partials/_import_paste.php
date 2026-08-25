<div class="versatile-import-paste">
    <p class="text-muted">
        Copia celdas desde Excel/Google Sheets (o pega texto separado por comas) y pégalas aquí.
        La primera fila puede ser el encabezado, igual que en un CSV.
    </p>

    <div class="form-group">
        <textarea
            name="paste_data"
            class="form-control"
            rows="8"
            placeholder="nombre&#9;email&#9;telefono&#10;Juan Pérez&#9;juan@correo.com&#9;70000000"
        ></textarea>
    </div>

    <div class="form-group">
        <label class="radio radio-inline">
            <input type="radio" name="paste_delimiter" value="tab" checked>
            Separado por tabulaciones (pegado directo de Excel/Sheets)
        </label>
        <label class="radio radio-inline">
            <input type="radio" name="paste_delimiter" value="comma">
            Separado por comas
        </label>
    </div>

    <button
        type="button"
        class="btn btn-default versatile-import-paste-btn"
        data-request="onImportFromPaste"
        data-request-data="{ file_format: 'csv' }"
        data-request-update="{ '#versatileImportContainer': true }"
        data-attach-loading
        data-load-indicator="Procesando datos pegados..."
    >
        <i class="icon-clipboard"></i> Usar estos datos pegados
    </button>
</div>
