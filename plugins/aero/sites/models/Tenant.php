<?php namespace Aero\Sites\Models;

use Aero\Sites\Classes\Niches\NicheManager;
use Model;
use System\Models\SiteDefinition;

class Tenant extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\SoftDelete;

    public $table = 'aero_sites_tenants';

    public $fillable = [
        'site_id', 'backend_user_id', 'root_domain_id', 'name', 'handle',
        'niche_type', 'status', 'primary_color', 'design_theme_id', 'theme_overrides',
    ];

    protected $jsonable = ['theme_overrides'];

    protected $dates = ['deleted_at'];

    public $rules = [
        'name'           => 'required|min:2|max:100',
        'handle'         => 'required|alpha_dash|unique:aero_sites_tenants,handle',
        'root_domain_id' => 'required|exists:aero_sites_root_domains,id',
        'niche_type'     => 'required',
        'status'         => 'in:active,inactive,suspended',
        'primary_color'  => 'regex:/^#[0-9A-Fa-f]{6}$/',
    ];

    public $belongsTo = [
        'rootDomain'   => [RootDomain::class, 'key' => 'root_domain_id'],
        'backendUser'  => [\Backend\Models\User::class, 'key' => 'backend_user_id'],
        'siteDefinition' => [SiteDefinition::class, 'key' => 'site_id'],
        'designTheme'  => [DesignTheme::class, 'key' => 'design_theme_id'],
    ];

    public $hasOne = [
        'seoConfig'     => [SeoConfig::class],
        'contactConfig' => [ContactConfig::class],
    ];

    public $hasMany = [
        'domains'               => [Domain::class],
        'pages'                 => [Page::class],
        'notificationChannels'  => [NotificationChannel::class],
        'contactSubmissions'    => [ContactSubmission::class],
        'apiTokens'             => [ApiToken::class],
        'tenantUsers'           => [TenantUser::class],
    ];

    public $belongsToMany = [
        'users' => [
            \RainLab\User\Models\User::class,
            'table'      => 'aero_sites_tenant_users',
            'key'        => 'tenant_id',
            'otherKey'   => 'user_id',
            'pivot'      => ['role'],
            'timestamps' => true,
        ],
    ];

    public $attachOne = [
        'logo'    => \System\Models\File::class,
        'favicon' => \System\Models\File::class,
    ];

    public function getPrimaryDomainAttribute(): string
    {
        $primary = $this->domains()->where('is_primary', true)->first();
        if ($primary) {
            return $primary->domain;
        }
        return $this->handle . '.' . ($this->rootDomain?->domain ?? 'localhost');
    }

    public function addUser(\Backend\Models\User $user, string $role = 'admin'): void
    {
        TenantUser::updateOrCreate(
            ['tenant_id' => $this->id, 'user_id' => $user->id],
            ['role' => $role]
        );
    }

    public function afterSave(): void
    {
        if (!$this->site_id) return;

        $dirty = $this->getDirty();
        $sync  = [];

        if (array_key_exists('name', $dirty)) {
            $sync['name'] = $this->name;
        }

        if (array_key_exists('status', $dirty)) {
            $sync['is_enabled'] = $this->status === 'active';
        }

        if ($sync) {
            SiteDefinition::where('id', $this->site_id)->update($sync);
        }
    }

    public function getRootDomainIdOptions(): array
    {
        return RootDomain::where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('label', 'id')
            ->toArray();
    }

    public function getNicheTypeOptions(): array
    {
        return app(NicheManager::class)->options();
    }

    public function getStatusOptions(): array
    {
        return [
            'active'    => 'Activo',
            'inactive'  => 'Inactivo',
            'suspended' => 'Suspendido',
        ];
    }

    public function getDesignThemeIdOptions(): array
    {
        return DesignTheme::active()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Variables CSS del tema efectivo del tenant. Si no hay design_theme_id
     * asignado, arma un set mínimo desde el primary_color legacy para no
     * dejar la página sin estilos mientras se migra el tenant a un theme.
     */
    public function getEffectiveCssVars(): array
    {
        if ($this->designTheme) {
            return $this->designTheme->toCssVars($this->theme_overrides ?? []);
        }

        return [
            '--color-primary'      => $this->primary_color ?: '#4f46e5',
            '--color-primary-dark' => $this->primary_color ?: '#3730a3',
            '--color-secondary'    => '#0ea5e9',
            '--color-accent'       => '#f59e0b',
            '--color-neutral-bg'   => '#f8fafc',
            '--color-neutral-text' => '#0f172a',
            '--font-heading'       => 'Inter',
            '--font-body'          => 'Inter',
            '--radius'             => '0.75rem',
        ];
    }

    /**
     * URL de Google Fonts CSS2 para las fuentes heading/body del tema
     * efectivo. Si ambas fuentes son iguales, pide un solo family.
     */
    public function getGoogleFontsUrl(): string
    {
        $vars = $this->getEffectiveCssVars();
        $heading = $vars['--font-heading'] ?? 'Inter';
        $body    = $vars['--font-body'] ?? 'Inter';

        $families = array_unique([$heading, $body]);
        $params = array_map(function ($font) {
            return 'family=' . str_replace(' ', '+', $font) . ':wght@400;500;600;700;800';
        }, $families);

        return 'https://fonts.googleapis.com/css2?' . implode('&', $params) . '&display=swap';
    }

    /**
     * Toggle simple de efectos suaves (scroll-reveal), a nivel de DesignTheme.
     * true por defecto (incluso sin theme asignado).
     */
    public function getEffectiveAnimationsEnabled(): bool
    {
        return $this->designTheme ? (bool) $this->designTheme->enable_animations : true;
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Permanently removes the tenant and ALL associated data:
     * files, pages, SEO, contact, channels, submissions, tokens,
     * domains, site definition, backend user, and frontend users.
     */
    public function purge(): void
    {
        \DB::transaction(function () {
            // Attached files (logo, favicon)
            $this->logo()->delete();
            $this->favicon()->delete();

            // Pages — force delete to trigger file cleanup on each og_image
            $this->pages()->withTrashed()->get()->each(function ($page) {
                $page->og_image()->delete();
                $page->forceDelete();
            });

            // SEO config (with og_image)
            if ($seo = $this->seoConfig) {
                $seo->og_image()->delete();
                $seo->delete();
            }

            // Contact config
            $this->contactConfig?->delete();

            // Notification channels
            $this->notificationChannels()->delete();

            // Contact submissions
            $this->contactSubmissions()->delete();

            // API tokens
            $this->apiTokens()->delete();

            // Domains
            $this->domains()->delete();

            // Backend user assignments — delete pivot records only (users are shared)
            $this->tenantUsers()->delete();

            // OctoberCMS SiteDefinition
            \System\Models\SiteDefinition::find($this->site_id)?->delete();

            // Backend user created exclusively for this tenant
            if ($this->backend_user_id) {
                \Backend\Models\User::find($this->backend_user_id)?->delete();
            }

            // Permanently delete the tenant record
            $this->forceDelete();
        });
    }

    public static function resolveFromDomain(string $host): ?self
    {
        // Buscar primero en dominios custom
        $domain = Domain::where('domain', $host)->with('tenant')->first();
        if ($domain) {
            return $domain->tenant;
        }

        // Buscar por subdominio: {handle}.{root_domain}
        $rootDomains = RootDomain::active()->get();
        foreach ($rootDomains as $root) {
            $suffix = '.' . $root->domain;
            if (str_ends_with($host, $suffix)) {
                $handle = str_replace($suffix, '', $host);
                return static::where('handle', $handle)
                    ->where('status', 'active')
                    ->first();
            }
        }

        return null;
    }
}
