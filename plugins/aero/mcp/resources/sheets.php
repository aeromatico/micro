<?php

/** Aero.Sheets — mapeos y ejecuciones de importación/exportación. */
return [
    'sheets_mappings' => [
        'plugin' => 'Aero.Sheets', 'label' => 'mapeos de Google Sheets', 'model' => \Aero\Sheets\Models\Mapping::class,
        'fields' => 'id,name,spreadsheet_id,spreadsheet_title,sheet_title,direction,header_row,start_row,max_rows,key_field,import_mode,last_run_at,last_status,created_at',
        'search' => 'name,spreadsheet_title', 'filters' => 'direction,last_status',
    ],
    'sheets_runs' => [
        'plugin' => 'Aero.Sheets', 'label' => 'ejecuciones de Google Sheets', 'model' => \Aero\Sheets\Models\Run::class,
        'fields' => 'id,mapping_id,direction,dry_run,status,rows_read,created,updated,skipped,failed,started_at,finished_at,created_at',
        'filters' => 'mapping_id,status,direction',
    ],
];
