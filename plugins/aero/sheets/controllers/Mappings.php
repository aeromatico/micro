<?php namespace Aero\Sheets\Controllers;

use Aero\Oauth\Classes\Oauth;
use Aero\Sheets\Classes\CurrentTenant;
use Aero\Sheets\Classes\ScopesToTenant;
use Aero\Sheets\Classes\SheetsClient;
use Aero\Sheets\Classes\SheetsException;
use Aero\Sheets\Classes\SyncService;
use Aero\Sheets\Models\Source;
use ApplicationException;
use Backend\Classes\Controller;
use BackendAuth;
use BackendMenu;
use Flash;

class Mappings extends Controller
{
    use ScopesToTenant;

    public $implement = [\Backend\Behaviors\ListController::class, \Backend\Behaviors\FormController::class];

    public $listConfig = 'config_list.yaml';
    public $formConfig = 'config_form.yaml';

    public $requiredPermissions = ['aero.sheets.use', 'aero.sheets.superadmin'];

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sheets', 'sheets', 'mappings');
    }

    public function update($recordId = null, $context = null)
    {
        $this->vars['connected'] = (bool) SheetsClient::connection(BackendAuth::getUser());
        $this->vars['connectUrl'] = Oauth::connectUrl('google', ['sheets'], parse_url(\Backend::url('aero/sheets/mappings/update/' . $recordId), PHP_URL_PATH));

        return $this->asExtension('FormController')->update($recordId, $context);
    }

    public function formBeforeCreate($model): void
    {
        if (!CurrentTenant::isAdmin()) {
            $model->tenant_id = CurrentTenant::id();
            if (!$model->tenant_id) {
                throw new ApplicationException('No se pudo determinar tu tenant.');
            }
        }
        $model->backend_user_id = BackendAuth::getUser()->id;
    }

    /** Impide, aun manipulando el POST, elegir una fuente no accesible. */
    public function formBeforeSave($model): void
    {
        $ok = Source::accessibleBy(CurrentTenant::isAdmin())->where('id', $model->source_id)->exists();
        if (!$ok) {
            throw new ApplicationException('Esa fuente de datos no está disponible para ti.');
        }
    }

    protected function mapping()
    {
        return $this->formFindModelObject($this->params[0] ?? null);
    }

    public function onPreview()
    {
        $m = $this->mapping();
        $this->vars['preview'] = null;

        try {
            if (!$m->spreadsheet_id || !$m->sheet_title) {
                throw new SheetsException('Guarda primero la hoja y la pestaña.');
            }
            $from = max(1, $m->header_row ?: $m->start_row);
            $rows = (new SheetsClient(BackendAuth::getUser()))->get(
                $m->spreadsheet_id,
                SheetsClient::a1($m->sheet_title, "A{$from}:ZZ" . ($from + 7))
            );
            $this->vars['preview'] = ['from' => $from, 'rows' => $rows];
        } catch (SheetsException $e) {
            $this->vars['previewError'] = $e->getMessage();
        }

        return ['#sheets-preview' => $this->makePartial('preview')];
    }

    /** Empareja columnas por cabecera (importar) o propone A, B, C… (exportar). */
    public function onAutoMap()
    {
        $m = $this->mapping();
        $fields = $m->source->fieldMap($m->direction);
        $norm = fn ($s) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(strtr((string) $s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n'])));
        $columns = [];

        if ($m->direction === 'import') {
            try {
                $headers = $m->header_row > 0
                    ? ((new SheetsClient(BackendAuth::getUser()))->get($m->spreadsheet_id, SheetsClient::a1($m->sheet_title, "{$m->header_row}:{$m->header_row}"))[0] ?? [])
                    : [];
            } catch (SheetsException $e) {
                throw new ApplicationException($e->getMessage());
            }
            foreach ($headers as $h) {
                foreach ($fields as $key => $f) {
                    if ($h !== '' && in_array($norm($h), [$norm($key), $norm($f['label'])], true)) {
                        $columns[] = ['field' => $key, 'column' => $h];
                        unset($fields[$key]);
                        break;
                    }
                }
            }
        } else {
            $i = 0;
            foreach ($fields as $key => $f) {
                $columns[] = ['field' => $key, 'column' => $f['label']];
                $i++;
            }
        }

        if (!$columns) {
            throw new ApplicationException('No encontré cabeceras que coincidan con los campos autorizados.');
        }

        $m->columns = $columns;
        $m->save();
        Flash::success(count($columns) . ' columnas emparejadas. Revísalas y ajusta lo que haga falta.');

        return \Redirect::refresh();
    }

    public function onRun()
    {
        $m = $this->mapping();
        $dry = (bool) post('dry');

        $run = (new SyncService(new SheetsClient(BackendAuth::getUser())))->run($m, BackendAuth::getUser(), $dry);

        $msg = sprintf(
            '%s%s: %d filas leídas, %d creadas, %d actualizadas, %d omitidas, %d con error.',
            $dry ? 'Prueba (nada se guardó) · ' : '',
            $run->status === 'ok' ? 'Listo' : ($run->status === 'failed' ? 'Falló' : 'Con errores'),
            $run->rows_read, $run->created, $run->updated, $run->skipped, $run->failed
        );

        if ($run->status === 'ok') {
            Flash::success($msg);
        } else {
            Flash::error($msg . ' ' . implode(' | ', array_slice((array) $run->errors, 0, 3)));
        }

        return \Redirect::refresh();
    }
}
