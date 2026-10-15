<?php namespace Aero\Workspaces\Components;

use Aero\Workspaces\Classes\Hiring;
use Aero\Workspaces\Classes\Workspace;
use Aero\Workspaces\Models\Settings;
use BackendAuth;
use Cms\Classes\ComponentBase;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Mercado de agentes PÚBLICO (themes/master → /workspaces/agentes): el catálogo lo
 * ve cualquiera; contratar exige sesión del panel de un tenant con permiso
 * `aero.workspaces.use`. La contratación usa la misma regla del panel (Hiring):
 * el tenant sale de la sesión, nunca de la petición.
 *
 * Lo que se expone es lo mismo que ve un tenant en el panel (Workspace::agent):
 * nunca el prompt de sistema ni el modelo.
 */
class AgentsMarket extends ComponentBase
{
    protected ?array $cache = null;

    public function componentDetails(): array
    {
        return [
            'name'        => 'Mercado de agentes (público)',
            'description' => 'Catálogo público de agentes de IA; contratar requiere sesión de un tenant.',
        ];
    }

    /** Catálogo + quién mira (para pintar el botón correcto). */
    public function market(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $viewer = $this->viewer();
        $hired = $viewer['hired'];
        $owner = (int) $viewer['tenant_id'];

        $agents = array_map(function (array $a) use ($hired) {
            $a['hired'] = in_array($a['slug'], $hired, true);

            return $a;
        }, Workspace::market($owner));

        return $this->cache = [
            'agents'   => $agents,
            'viewer'   => array_diff_key($viewer, ['tenant_id' => 1]),
            'charging' => Settings::chargeEnabled(),
            'avatars'  => url('plugins/aero/workspaces/assets/img/avatars.png'),
            'login_url' => url(\Backend::uri()),
            'office_url' => $this->officeUrl($owner),
            'cats'     => array_values(array_unique(array_filter(array_column($agents, 'category')))),
        ];
    }

    /**
     * @return array{logged: bool, can_hire: bool, tenant: ?string, tenant_id: ?int, hired: string[], reason: ?string}
     */
    protected function viewer(): array
    {
        $none = ['logged' => false, 'can_hire' => false, 'tenant' => null, 'tenant_id' => null, 'hired' => [], 'reason' => null];
        $user = BackendAuth::check() ? BackendAuth::getUser() : null;

        if (!$user) {
            return $none;
        }

        $out = ['logged' => true] + $none;

        if (!$user->hasAccess('aero.workspaces.use') && !$user->hasAccess('aero.workspaces.superadmin')) {
            return $out + ['reason' => 'Tu usuario no tiene permiso para contratar agentes. Pídelo al administrador de tu cuenta.'];
        }

        $tenant = class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::resolveForBackendUser($user) : null;

        if (!$tenant) {
            return ['reason' => 'No encontramos un negocio asociado a tu usuario.'] + $out;
        }

        return [
            'can_hire' => true, 'tenant' => (string) $tenant->name, 'tenant_id' => (int) $tenant->id, 'reason' => null,
            'hired' => array_map(fn ($id) => (string) \Aero\Workspaces\Models\Staff::where('id', $id)->value('slug'), Workspace::hiredIds((int) $tenant->id)),
        ] + $out;
    }

    public function onHire(): array
    {
        $viewer = $this->viewer();

        if (!$viewer['logged']) {
            throw new \ApplicationException('Inicia sesión con tu cuenta de Market para contratar agentes.');
        }

        if (!$viewer['can_hire']) {
            throw new \ApplicationException($viewer['reason'] ?: 'No puedes contratar agentes con este usuario.');
        }

        $user = BackendAuth::getUser();
        $key = 'ws-public-hire:' . $user->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            throw new \ApplicationException('Demasiados intentos. Espera un minuto.');
        }

        RateLimiter::hit($key, 60);
        $slug = (string) post('slug');

        try {
            $result = Hiring::hire((int) $viewer['tenant_id'], $slug, $user->id);
        }
        catch (\DomainException $e) {
            throw new \ApplicationException($e->getMessage());
        }

        return ['#hire-' . $slug => $this->renderPartial('@hired', ['name' => $result['agent']['name'], 'charged' => $result['charged'], 'office_url' => $this->officeUrl((int) $viewer['tenant_id'])])];
    }

    /** La Oficina siempre en el panel del propio tenant. */
    protected function officeUrl(int $tenantId): string
    {
        $path = \Backend::url('aero/workspaces/office');

        try {
            return $tenantId && class_exists(\Aero\Sites\Models\Tenant::class) ? \Aero\Sites\Models\Tenant::localizeBackendUrls($tenantId, $path) : $path;
        }
        catch (\Throwable) {
            return $path;
        }
    }
}
