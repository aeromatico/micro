<?php namespace Aero\Sites\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Publica el layout del dashboard de un usuario como dashboard INICIAL de
 * todos: lo copia a la definición global («System Dashboard»), que ven los
 * usuarios que aún no personalizaron el suyo. Quien ya editó el suyo lo conserva
 * (--reset-others lo descarta para que vuelva a la plantilla; los usuarios con
 * --keep, p. ej. el propio origen, se respetan). Se guarda un respaldo del
 * layout global anterior en storage/app.
 */
class PublishDashboard extends Command
{
    protected $signature = 'aero.sites:publish-dashboard
        {login=alvaro : Usuario del backend cuyo dashboard será la plantilla}
        {--reset-others : Borra los layouts personales de los demás usuarios (vuelven a la plantilla)}
        {--keep=* : Logins cuyo layout personal se conserva con --reset-others}';

    protected $description = 'Publica el dashboard de un usuario como dashboard inicial de todos los tenants.';

    private const KEY = 'dashboard::layout.dashboard_controllers_index.system';

    public function handle(): int
    {
        $user = DB::table('backend_users')->where('login', $this->argument('login'))->first();
        if (!$user) {
            $this->error('No existe ese usuario.');

            return self::FAILURE;
        }

        $pref = DB::table('backend_user_preferences')
            ->where('user_id', $user->id)->where('namespace', 'dashboard')->where('group', 'layout')
            ->where('item', 'dashboard_controllers_index.system')->value('value');
        $rows = json_decode((string) $pref, true);
        if (!is_array($rows) || !$rows) {
            $this->error('Ese usuario no tiene un dashboard personalizado guardado.');

            return self::FAILURE;
        }

        // Filas vacías son restos del editor: no van en la plantilla.
        $rows = array_values(array_filter($rows, fn ($r) => !empty($r['widgets'])));

        $global = DB::table('dashboard_dashboards')->where('code', 'system')->where('is_global', 1)->first();
        if (!$global) {
            $this->error('No se encontró el dashboard global «system».');

            return self::FAILURE;
        }

        $backup = storage_path('app/dashboard-template-' . date('Ymd-His') . '.json');
        file_put_contents($backup, $global->definition);

        DB::table('dashboard_dashboards')->where('id', $global->id)->update([
            'definition' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
        $this->info(count($rows) . " fila(s) de «{$user->login}» publicadas como dashboard inicial. Respaldo: {$backup}");

        if ($this->option('reset-others')) {
            $keepIds = DB::table('backend_users')->whereIn('login', array_merge([$user->login], (array) $this->option('keep')))->pluck('id');
            $n = DB::table('backend_user_preferences')
                ->where('namespace', 'dashboard')->where('group', 'layout')
                ->where('item', 'dashboard_controllers_index.system')
                ->whereNotIn('user_id', $keepIds)->delete();
            $this->info("{$n} layout(s) personal(es) descartado(s).");
        }

        return self::SUCCESS;
    }
}
