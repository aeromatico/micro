<?php namespace Aero\Sheets\Models;

use Model;

class Mapping extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sheets_mappings';

    public $fillable = [
        'tenant_id', 'backend_user_id', 'source_id', 'name', 'spreadsheet_id', 'spreadsheet_title', 'sheet_title',
        'direction', 'header_row', 'start_row', 'max_rows', 'key_field', 'import_mode',
        'write_headers', 'clear_before_export', 'columns',
    ];

    public $jsonable = ['columns'];

    public $rules = [
        'name'        => 'required',
        'source_id'   => 'required|integer',
        'direction'   => 'in:import,export',
        'header_row'  => 'integer|min:0|max:100000',
        'start_row'   => 'integer|min:1|max:1000000',
        'max_rows'    => 'nullable|integer|min:1|max:100000',
        'import_mode' => 'in:create,update,upsert',
    ];

    public $belongsTo = ['source' => [Source::class, 'key' => 'source_id']];

    public $hasMany = ['runs' => [Run::class, 'key' => 'mapping_id']];

    public $attributes = ['direction' => 'import', 'header_row' => 1, 'start_row' => 2, 'import_mode' => 'upsert', 'write_headers' => true];

    public function beforeValidate(): void
    {
        if ($this->spreadsheet_id) {
            $this->spreadsheet_id = \Aero\Sheets\Classes\SheetsClient::spreadsheetId($this->spreadsheet_id) ?? $this->spreadsheet_id;
        }
        if ($this->start_row && $this->header_row && $this->start_row <= $this->header_row) {
            throw new \ValidationException(['start_row' => 'La fila de inicio debe ser posterior a la fila de cabeceras.']);
        }
    }

    public function beforeSave(): void
    {
        // Cambiar de fuente invalida columnas y clave: se reinician.
        if ($this->exists && $this->isDirty('source_id')) {
            $this->columns = [];
            $this->key_field = null;
        }
    }

    public function getSourceIdOptions(): array
    {
        $isAdmin = \Aero\Sheets\Classes\CurrentTenant::isAdmin();

        return Source::accessibleBy($isAdmin)->orderBy('label')->pluck('label', 'id')->all();
    }

    public function getFieldOptions(): array
    {
        $dir = $this->direction;

        return collect($this->source?->fieldMap($dir) ?? [])->map(fn ($f) => $f['label'] . ' (' . $f['key'] . ')')->all();
    }

    public function getKeyFieldOptions(): array
    {
        return ['' => '— ninguno —'] + $this->getFieldOptions();
    }

    public function getSheetTitleOptions(): array
    {
        if (!$this->spreadsheet_id) {
            return [];
        }

        try {
            $meta = (new \Aero\Sheets\Classes\SheetsClient(\BackendAuth::getUser()))->meta($this->spreadsheet_id);

            return array_combine($meta['sheets'], $meta['sheets']);
        } catch (\Throwable) {
            return [];
        }
    }
}
