<?php namespace Aero\Sites;

use Aero\Sites\Models\Tenant;
use Backend;
use BackendAuth;
use Event;
use Route;
use System\Classes\PluginBase;
use System\Models\SiteDefinition;

class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'Sites',
            'description' => 'SaaS multitenant para micrositios web por nichos',
            'author'      => 'Aero',
            'icon'        => 'icon-globe',
            'homepage'    => 'https://micro.clouds.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerNotificationDrivers();
        $this->registerNiches();
    }

    public function boot(): void
    {
        $this->registerApiRoutes();
        $this->bootSiteContext();
        $this->bootRainLabIntegration();
    }

    protected function bootRainLabIntegration(): void
    {
        if (!class_exists(\RainLab\User\Models\User::class)) {
            return;
        }

        // Add "Tenants" tab to RainLab.User profile in backend
        \RainLab\User\Models\User::extend(function ($model) {
            $model->hasMany['tenantAssignments'] = [
                \Aero\Sites\Models\TenantUser::class,
                'key' => 'user_id',
            ];
            $model->belongsToMany['tenants'] = [
                \Aero\Sites\Models\Tenant::class,
                'table'    => 'aero_sites_tenant_users',
                'key'      => 'user_id',
                'otherKey' => 'tenant_id',
                'pivot'    => ['role'],
            ];
        });

        \RainLab\User\Controllers\Users::extendFormFields(function ($form, $model) {
            if (!$model instanceof \RainLab\User\Models\User) {
                return;
            }

            $form->addTabFields([
                'tenantAssignments' => [
                    'label'   => 'Tenants asignados',
                    'type'    => 'hasmanytable',
                    'tab'     => 'Tenants',
                    'columns' => [
                        'tenant_id' => ['label' => 'Tenant', 'type' => 'dropdown'],
                        'role'      => ['label' => 'Rol',    'type' => 'dropdown'],
                    ],
                ],
            ]);
        });
    }

    protected function bootSiteContext(): void
    {
        // Force tenant admins to always work in their own site context.
        // Superusers see all sites normally via the native site selector.
        Event::listen('system.site.getEditSite', function () {
            $user = BackendAuth::getUser();
            if (!$user || $user->is_superuser) {
                return null; // let OctoberCMS handle it natively
            }

            $tenant = Tenant::where('backend_user_id', $user->id)->first();
            if ($tenant?->site_id) {
                return SiteDefinition::find($tenant->site_id);
            }

            return null;
        });
    }

    public function registerFormWidgets(): array
    {
        return [
            \Aero\Sites\FormWidgets\PuckEditor::class => [
                'label' => 'Puck Visual Editor',
                'code'  => 'puckEditor',
            ],
        ];
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Sites\Components\TenantSeo::class     => 'sitesSeo',
            \Aero\Sites\Components\PageList::class      => 'sitesPageList',
            \Aero\Sites\Components\PageDetail::class    => 'sitesPageDetail',
            \Aero\Sites\Components\ContactSection::class => 'sitesContact',
        ];
    }

    public function registerNavigation(): array
    {
        return [
            // Panel del tenant admin — acceso simplificado al propio sitio
            'mi-sitio' => [
                'label'       => 'aero.sites::lang.menu.my_site',
                'url'         => Backend::url('aero/sites/contenteditor'),
                'icon'        => 'icon-desktop',
                'permissions' => ['aero.sites.manage_pages'],
                'order'       => 100,
                'sideMenu'    => [
                    'contenidos' => [
                        'label'       => 'aero.sites::lang.menu.contents',
                        'icon'        => 'icon-file-text-o',
                        'url'         => Backend::url('aero/sites/contenteditor'),
                        'permissions' => ['aero.sites.manage_pages'],
                    ],
                    'configuracion' => [
                        'label'       => 'aero.sites::lang.menu.settings',
                        'icon'        => 'icon-cog',
                        'url'         => Backend::url('aero/sites/sitesettings'),
                        'permissions' => ['aero.sites.manage_seo'],
                    ],
                ],
            ],
            // Panel del superadmin — gestión de la plataforma
            'sites' => [
                'label'       => 'aero.sites::lang.menu.sites',
                'url'         => Backend::url('aero/sites/tenants'),
                'icon'        => 'icon-globe',
                'permissions' => ['aero.sites.superadmin'],
                'order'       => 200,
                'sideMenu'    => [
                    'tenants' => [
                        'label'       => 'aero.sites::lang.menu.tenants',
                        'icon'        => 'icon-users',
                        'url'         => Backend::url('aero/sites/tenants'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'rootdomains' => [
                        'label'       => 'aero.sites::lang.menu.root_domains',
                        'icon'        => 'icon-server',
                        'url'         => Backend::url('aero/sites/rootdomains'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'pages' => [
                        'label'       => 'aero.sites::lang.menu.pages',
                        'icon'        => 'icon-copy',
                        'url'         => Backend::url('aero/sites/pages'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'seoconfigs' => [
                        'label'       => 'aero.sites::lang.menu.seo',
                        'icon'        => 'icon-search',
                        'url'         => Backend::url('aero/sites/seoconfigs'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'contactconfigs' => [
                        'label'       => 'aero.sites::lang.menu.contact',
                        'icon'        => 'icon-phone',
                        'url'         => Backend::url('aero/sites/contactconfigs'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'notificationchannels' => [
                        'label'       => 'aero.sites::lang.menu.channels',
                        'icon'        => 'icon-bell',
                        'url'         => Backend::url('aero/sites/notificationchannels'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'contactsubmissions' => [
                        'label'       => 'aero.sites::lang.menu.submissions',
                        'icon'        => 'icon-envelope',
                        'url'         => Backend::url('aero/sites/contactsubmissions'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'apitokens' => [
                        'label'       => 'aero.sites::lang.menu.api_tokens',
                        'icon'        => 'icon-key',
                        'url'         => Backend::url('aero/sites/apitokens'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'tenantusers' => [
                        'label'       => 'aero.sites::lang.menu.tenant_users',
                        'icon'        => 'icon-user-plus',
                        'url'         => Backend::url('aero/sites/tenantusers'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                ],
            ],
        ];
    }

    public function registerPermissions(): array
    {
        return [
            'aero.sites.superadmin' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.superadmin',
            ],
            'aero.sites.manage_pages' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.manage_pages',
            ],
            'aero.sites.manage_seo' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.manage_seo',
            ],
            'aero.sites.manage_contact' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.manage_contact',
            ],
            'aero.sites.view_submissions' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.view_submissions',
            ],
            'aero.sites.manage_api_tokens' => [
                'tab'   => 'Sites',
                'label' => 'aero.sites::lang.permissions.manage_api_tokens',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Notification Drivers
    // -------------------------------------------------------------------------

    protected function registerNotificationDrivers(): void
    {
        $dispatcher = $this->app->singleton(
            \Aero\Sites\Classes\Notifications\NotificationDispatcher::class,
            fn() => new \Aero\Sites\Classes\Notifications\NotificationDispatcher()
        );

        Event::listen('aero.sites.registerNotificationDrivers', function ($manager) {
            $manager->register('email',    \Aero\Sites\Classes\Notifications\EmailNotificationDriver::class);
            $manager->register('whatsapp', \Aero\Sites\Classes\Notifications\WhatsappNotificationDriver::class);
            $manager->register('telegram', \Aero\Sites\Classes\Notifications\TelegramNotificationDriver::class);
            $manager->register('sms',      \Aero\Sites\Classes\Notifications\SmsNotificationDriver::class);
        });
    }

    // -------------------------------------------------------------------------
    // Niche Drivers
    // -------------------------------------------------------------------------

    protected function registerNiches(): void
    {
        $this->app->singleton(
            \Aero\Sites\Classes\Niches\NicheManager::class,
            fn() => new \Aero\Sites\Classes\Niches\NicheManager()
        );

        Event::listen('aero.sites.registerNiches', function ($manager) {
            $manager->register('generic',         \Aero\Sites\Classes\Niches\GenericNiche::class);
            $manager->register('inmuebles',       \Aero\Sites\Classes\Niches\InmueblesNiche::class);
            $manager->register('consultorio',     \Aero\Sites\Classes\Niches\ConsultorioNiche::class);
            $manager->register('tienda_whatsapp', \Aero\Sites\Classes\Niches\TiendaWhatsappNiche::class);
            $manager->register('radioemisora',    \Aero\Sites\Classes\Niches\RadioemisoraNiche::class);
        });
    }

    // -------------------------------------------------------------------------
    // API Routes
    // -------------------------------------------------------------------------

    protected function registerApiRoutes(): void
    {
        Route::prefix('api/v1')
            ->middleware([
                \Aero\Sites\Http\Middleware\ResolveTenantFromDomain::class,
                \Aero\Sites\Http\Middleware\AuthenticateApiToken::class,
            ])
            ->group(function () {
                Route::get('site',          [\Aero\Sites\Http\Controllers\Api\SiteController::class, 'show']);
                Route::get('pages',         [\Aero\Sites\Http\Controllers\Api\PagesController::class, 'index']);
                Route::get('pages/{slug}',  [\Aero\Sites\Http\Controllers\Api\PagesController::class, 'show']);
                Route::get('seo',           [\Aero\Sites\Http\Controllers\Api\SiteController::class, 'seo']);
                Route::post('contact',      [\Aero\Sites\Http\Controllers\Api\ContactController::class, 'submit']);
            });
    }
}
