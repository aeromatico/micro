<?php namespace Aero\Sites\Controllers;

use Aero\Sites\Classes\Niches\NicheManager;
use Aero\Sites\Models\ApiToken;
use Aero\Sites\Models\Domain;
use Aero\Sites\Models\RootDomain;
use Aero\Sites\Models\Tenant;
use Backend;
use BackendMenu;
use Backend\Classes\Controller;
use BackendAuth;
use Flash;
use Str;
use System\Models\SiteDefinition;

class Tenants extends Controller
{
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    public $formConfig = 'config_form.yaml';
    public $listConfig = 'config_list.yaml';

    public $requiredPermissions = ['aero.sites.superadmin'];

    protected ?array $lastCredentials = null;

    public function __construct()
    {
        parent::__construct();
        BackendMenu::setContext('Aero.Sites', 'sites', 'tenants');
    }

    // -------------------------------------------------------------------------
    // Provisioning
    // -------------------------------------------------------------------------

    public function onCreate(): void
    {
        // Delegate to FormController, but we intercept after save
    }

    public function formAfterCreate(Tenant $tenant): void
    {
        $this->provisionTenant($tenant);
    }

    protected function provisionTenant(Tenant $tenant): void
    {
        // 1. Create OctoberCMS SiteDefinition
        $rootDomain = RootDomain::find($tenant->root_domain_id);
        $primaryDomain = $tenant->handle . '.' . $rootDomain->domain;

        $site = $this->createSiteDefinition($tenant, $primaryDomain);
        $tenant->update(['site_id' => $site?->id]);

        // 2. Create primary domain record
        Domain::create([
            'tenant_id'    => $tenant->id,
            'domain'       => $primaryDomain,
            'is_primary'   => true,
            'is_subdomain' => true,
        ]);

        // 3. Provision via niche driver (pages, seo, contact, channel)
        $nicheManager = app(NicheManager::class);
        $niche = $nicheManager->make($tenant->niche_type);
        $niche->provision($tenant);

        // 4. Create initial read-only API token
        ['plain' => $plain, 'hashed' => $hashed] = ApiToken::generateToken();
        ApiToken::create([
            'tenant_id' => $tenant->id,
            'name'      => 'Token inicial (solo lectura)',
            'token'     => $hashed,
            'abilities' => ['pages:read', 'seo:read', 'contact:submit'],
        ]);

        // 5. Create backend user for tenant
        $password = Str::random(12);
        $email    = $tenant->handle . '@' . $rootDomain->domain;
        $user     = $this->createBackendUser($tenant, $email, $password, $site);

        // 6. Link backend user to tenant for native site context resolution
        if ($user) {
            $tenant->update(['backend_user_id' => $user->id]);
        }

        // 7. Create frontend (RainLab) user with admin group + tenant assignment
        $this->createFrontendUser($tenant, $email, $password);

        // 8. Store credentials to show once
        $this->lastCredentials = [
            'backend_url'      => Backend::baseUrl(),
            'email'            => $email,
            'password'         => $password,
            'api_token'        => $plain,
            'primary_domain'   => $primaryDomain,
        ];
    }

    protected function createSiteDefinition(Tenant $tenant, string $primaryDomain): ?object
    {
        if (!class_exists(SiteDefinition::class)) return null;

        $site = new SiteDefinition();
        $site->name               = $tenant->name;
        $site->code               = $tenant->handle;
        $site->is_enabled         = true;
        $site->theme              = 'microsites';
        $site->is_custom_url      = true;
        $site->app_url            = 'https://' . $primaryDomain;
        $site->is_host_restricted = true;
        $site->allow_hosts        = [['hostname' => $primaryDomain]];
        $site->is_enabled_edit    = true; // visible en admin/system/sites selector

        $site->save();
        return $site;
    }

    protected function createBackendUser(Tenant $tenant, string $email, string $password, ?object $site): ?object
    {
        try {
            $user = BackendAuth::register([
                'first_name' => $tenant->name,
                'last_name'  => 'Admin',
                'login'      => $tenant->handle,
                'email'      => $email,
                'password'   => $password,
                'password_confirmation' => $password,
            ]);

            $role = \Backend\Models\UserRole::where('code', 'tenant_admin')->first();
            if ($role) {
                $user->role_id = $role->id;
            }

            $user->is_activated = true;
            $user->save();
            return $user;
        } catch (\Exception $e) {
            \Log::error("Aero\\Sites: Failed to create backend user for tenant {$tenant->id}: " . $e->getMessage());
            return null;
        }
    }

    protected function createFrontendUser(Tenant $tenant, string $email, string $password): ?object
    {
        if (!class_exists(\RainLab\User\Models\User::class)) {
            return null;
        }

        try {
            $user = new \RainLab\User\Models\User;
            $user->name                  = $tenant->name;
            $user->email                 = $email;
            $user->password              = $password;
            $user->password_confirmation = $password;
            $user->is_activated          = true;
            $user->activated_at          = now();
            $user->save();

            $tenant->addUser($user, 'admin');
            return $user;
        } catch (\Exception $e) {
            \Log::error("Aero\\Sites: Failed to create frontend user for tenant {$tenant->id}: " . $e->getMessage());
            return null;
        }
    }

    // -------------------------------------------------------------------------
    // Show credentials after create
    // -------------------------------------------------------------------------

    public function create_onSave(): mixed
    {
        $result = $this->asExtension('FormController')->create_onSave();

        if ($this->lastCredentials) {
            $this->vars['credentials'] = $this->lastCredentials;
            Flash::success('Tenant creado correctamente. Guarda las credenciales mostradas.');
            return $this->makePartial('credentials_flash', $this->vars);
        }

        return $result;
    }

}
