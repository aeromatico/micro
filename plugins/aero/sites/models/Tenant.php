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
        'niche_type', 'status', 'primary_color', 'logo_text', 'logo_text_font',
        'design_theme_id', 'theme_overrides',
        'plan_id', 'plan_price', 'billing_period', 'plan_expires_at', 'signup_qr_code_id', 'signup_payment_reference', 'signup_domain', 'signup_domain_source',
    ];

    protected $jsonable = ['theme_overrides'];

    protected $dates = ['deleted_at', 'plan_expires_at'];

    public $rules = [
        'name'           => 'required|min:2|max:100',
        'handle'         => 'required|alpha_dash|unique:aero_sites_tenants,handle|not_in:panel,www,api,admin,app',
        'root_domain_id' => 'required|exists:aero_sites_root_domains,id',
        'niche_type'     => 'required',
        'status'         => 'in:active,inactive,suspended,pending_payment',
        'primary_color'  => 'regex:/^#[0-9A-Fa-f]{6}$/',
    ];

    public $belongsTo = [
        'rootDomain'   => [RootDomain::class, 'key' => 'root_domain_id'],
        'backendUser'  => [\Backend\Models\User::class, 'key' => 'backend_user_id'],
        'siteDefinition' => [SiteDefinition::class, 'key' => 'site_id'],
        'designTheme'  => [DesignTheme::class, 'key' => 'design_theme_id'],
        'plan'         => [Plan::class, 'key' => 'plan_id'],
    ];

    public $hasOne = [
        'seoConfig'     => [SeoConfig::class],
        'contactConfig' => [ContactConfig::class],
        'layout'        => [Layout::class],
    ];

    public $hasMany = [
        'domains'               => [Domain::class],
        'pages'                 => [Page::class],
        'notificationChannels'  => [\Aero\Notify\Models\Channel::class],
        'contactSubmissions'    => [ContactSubmission::class],
        'apiTokens'             => [ApiToken::class],
        'tenantUsers'           => [TenantUser::class],
        'invites'               => [TenantInvite::class],
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

    // Imágenes subidas desde el editor Puck (Hero/ImageBlock/Gallery/LogoCloud).
    // No usamos la Media Library de October (System.MediaManager / storage/app/media)
    // porque es una biblioteca única y global — cualquier backend user con
    // permiso media.library ve/navega los archivos de TODOS los tenants. Cada
    // archivo queda aislado como su propio System\Models\File, adjunto solo a
    // este tenant, igual que logo/favicon.
    public $attachMany = [
        'puck_uploads' => \System\Models\File::class,
    ];

    public function getPrimaryDomainAttribute(): string
    {
        $primary = $this->domains()->where('is_primary', true)->first();
        if ($primary) {
            return $primary->domain;
        }
        return $this->handle . '.' . ($this->rootDomain?->domain ?? 'localhost');
    }

    /**
     * true mientras el admin no haya generado su primer landing con IA ni
     * guardado contenido propio — ver Page::is_placeholder y home.htm. El
     * layout base la usa para ocultar navbar/footer del sitio público
     * mientras dura ese estado inicial.
     */
    public function isUnderConstruction(): bool
    {
        return (bool) $this->pages()->where('slug', '')->value('is_placeholder');
    }

    public function addUser(\Backend\Models\User $user, string $role = 'admin'): void
    {
        TenantUser::updateOrCreate(
            ['tenant_id' => $this->id, 'user_id' => $user->id],
            ['role' => $role]
        );
    }

    /**
     * Rol de panel que corresponde al plan (is_pro → tenant_admin_pro, cualquier
     * otro → tenant_admin. Solo toca a los usuarios que ya tienen uno de esos
     * dos roles (no pisa roles a medida ni al superadmin).
     */
    public function syncAdminRoles(): void
    {
        $target = \Aero\Sites\Classes\ProFeatures::roleCodeForPlan($this->plan);
        $roles = \Backend\Models\UserRole::whereIn('code', [
            \Aero\Sites\Classes\ProFeatures::ROLE_REGULAR,
            \Aero\Sites\Classes\ProFeatures::ROLE_PRO,
        ])->pluck('id', 'code');

        if (!isset($roles[$target])) {
            return;
        }

        $userIds = TenantUser::where('tenant_id', $this->id)->pluck('user_id')->push($this->backend_user_id)->filter()->unique();
        \Backend\Models\User::whereIn('id', $userIds)
            ->whereIn('role_id', $roles->values())
            ->update(['role_id' => $roles[$target]]);
    }

    public function afterSave(): void
    {
        static::forgetResolvedHosts();

        $dirty = $this->getDirty();

        if (array_key_exists('plan_id', $dirty)) {
            $this->syncAdminRoles();
        }

        // Créditos del plan: al activarse o al cambiar de plan estando activo.
        // Idempotente por tenant+plan+color, así que reintentos no duplican.
        if ($this->status === 'active' && (array_key_exists('plan_id', $dirty) || array_key_exists('status', $dirty))) {
            \Aero\Sites\Classes\PlanCredits::grant($this);
        }

        if (array_key_exists('status', $dirty) && $this->status === 'suspended') {
            $this->notifyTenantSuspended();
        }

        if (!$this->site_id) return;

        $sync = [];

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

    /**
     * Aero.Sites no requiere Aero.Notify — se guarda con class_exists(),
     * mismo patrón que TenantInvite::notify(). Sin campo 'reason' en la
     * tabla (nadie lo pidió al construir el status): se manda null, la
     * variable es opcional en el contrato del evento (ver EventCatalog).
     */
    protected function notifyTenantSuspended(): void
    {
        if (!class_exists(\Aero\Notify\Classes\Notify::class)) {
            return;
        }

        try {
            \Aero\Notify\Classes\Notify::fire('sites.tenant.suspended', [
                'tenant_name' => $this->name,
                'reason'      => null,
            ], [
                'tenant_id' => $this->id,
            ]);
        } catch (\Throwable $e) {
            \Log::error('Aero.Sites: fallo notificando sites.tenant.suspended: ' . $e->getMessage());
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
            'active'          => 'Activo',
            'inactive'        => 'Inactivo',
            'suspended'       => 'Suspendido',
            'pending_payment' => 'Pendiente de pago (alta reservada)',
        ];
    }

    public function getPlanIdOptions(): array
    {
        return \Aero\Sites\Models\Plan::orderBy('sort_order')->pluck('name', 'code')->all();
    }

    public function getDesignThemeIdOptions(): array
    {
        return DesignTheme::active()->orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Variables CSS del tema efectivo del tenant para ambos modos ({ light, dark }).
     * Si no hay design_theme_id asignado, arma un set mínimo desde el
     * primary_color legacy para no dejar la página sin estilos mientras se
     * migra el tenant a un theme.
     */
    public function getEffectiveCssVars(): array
    {
        if ($this->designTheme) {
            return $this->designTheme->toCssVars($this->theme_overrides ?? []);
        }

        $primary = $this->primary_color ?: '#4f46e5';
        $fontOverride = $this->theme_overrides['fonts'] ?? [];
        $shared = [
            '--color-primary'   => $primary,
            '--font-heading'    => $fontOverride['heading'] ?? 'Inter',
            '--font-heading-2'  => $fontOverride['heading2'] ?? $fontOverride['heading'] ?? 'Inter',
            '--font-body'       => $fontOverride['body'] ?? 'Inter',
            '--radius'          => '0.75rem',
        ];

        return [
            'light' => array_merge($shared, [
                '--color-primary-dark'        => $primary,
                '--color-secondary'           => '#0ea5e9',
                '--color-accent'              => '#f59e0b',
                '--color-surface-bg'          => '#f8fafc',
                '--color-surface-alt'         => '#ffffff',
                '--color-surface-text'        => '#0f172a',
                '--color-surface-text-muted'  => '#64748b',
                '--color-surface-border'      => '#e2e8f0',
                '--color-neutral-bg'          => '#f8fafc',
                '--color-neutral-text'        => '#0f172a',
            ]),
            'dark' => array_merge($shared, [
                '--color-primary-dark'        => $primary,
                '--color-secondary'           => '#0ea5e9',
                '--color-accent'              => '#f59e0b',
                '--color-surface-bg'          => '#0b0d12',
                '--color-surface-alt'         => '#151821',
                '--color-surface-text'        => '#f1f5f9',
                '--color-surface-text-muted'  => '#94a3b8',
                '--color-surface-border'      => 'rgba(255, 255, 255, 0.08)',
                '--color-neutral-bg'          => '#0b0d12',
                '--color-neutral-text'        => '#f1f5f9',
            ]),
        ];
    }

    /**
     * Texto del logo (fallback a "Nombre del sitio" si no se personalizó) —
     * usado por el header/footer del tema cuando el tenant no tiene logo de
     * imagen. Ver logo_text_font para la tipografía asociada.
     */
    public function getEffectiveLogoTextAttribute(): string
    {
        return $this->logo_text ?: $this->name;
    }

    /**
     * URL de Google Fonts CSS2 para las fuentes heading/heading2/body del
     * tema efectivo, más logo_text_font si el tenant eligió una para su
     * logo de texto. Familias repetidas se dedupean a un solo family.
     */
    public function getGoogleFontsUrl(): string
    {
        $vars = $this->getEffectiveCssVars()['light'] ?? [];
        $heading  = $vars['--font-heading'] ?? 'Inter';
        $heading2 = $vars['--font-heading-2'] ?? $heading;
        $body     = $vars['--font-body'] ?? 'Inter';

        $families = array_unique(array_filter([$heading, $heading2, $body, $this->logo_text_font]));
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
     * Switch "Sitio activado" en SiteSettings — atajo booleano sobre `status`
     * para el tenant admin. No toca el estado "suspended" (reservado a
     * superadmin) salvo que el propio tenant lo reactive explícitamente.
     */
    public function getIsSiteActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function setIsSiteActiveAttribute(bool $value): void
    {
        $this->status = $value ? 'active' : 'inactive';
    }

    /**
     * Permanently removes the tenant and ALL associated data:
     * files, pages, SEO, contact, channels, submissions, tokens,
     * domains, site definition, backend user, and frontend users.
     */
    public function purge(): void
    {
        \Event::fire('aero.sites.tenant.purging', [$this]);

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

            // Notification channels (Aero.Notify — no requerido por Sites)
            if (class_exists(\Aero\Notify\Models\Channel::class)) {
                $this->notificationChannels()->delete();
            }

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

    /**
     * @var array<string, ?self> resolvedHosts memoiza la resolución por host
     * durante el request. Sin esto cada componente CMS que necesita el tenant
     * (TenantSeo, PageList, PageDetail, ContactSection, y los del shop vía
     * StorefrontContext) dispara su propio par de queries: se medían 4 SELECT
     * a aero_sites_domains y 4 a aero_sites_tenants por página, todos para
     * llegar a la misma fila.
     */
    protected static array $resolvedHosts = [];

    public static function resolveFromDomain(string $host): ?self
    {
        if (array_key_exists($host, static::$resolvedHosts)) {
            return static::$resolvedHosts[$host];
        }

        return static::$resolvedHosts[$host] = static::lookupByDomain($host);
    }

    /**
     * forgetResolvedHosts limpia la memoización. Solo hace falta en tests o en
     * comandos de larga duración que cambian dominios en caliente — el ciclo
     * normal de request se lleva el estado consigo.
     */
    public static function forgetResolvedHosts(): void
    {
        static::$resolvedHosts = [];
    }

    /**
     * Tenant dueño de un host del tipo {handle}.{root_domain}. Es el único
     * criterio para el backend: cada tenant tiene siempre un subdominio único
     * desde que se crea la cuenta, incluso si además apunta un dominio custom
     * (que solo sirve para el sitio público). No filtra por status: un tenant
     * suspendido debe seguir resolviéndose para no caer al fallback de otro.
     */
    public static function resolveFromSubdomain(string $host): ?self
    {
        foreach (RootDomain::active()->get() as $root) {
            $suffix = '.' . $root->domain;
            if (str_ends_with($host, $suffix)) {
                $handle = substr($host, 0, -strlen($suffix));
                return $handle !== '' ? static::where('handle', $handle)->first() : null;
            }
        }

        return null;
    }

    /** URL del backend en el subdominio del tenant (nunca en dominio custom). */
    public function backendUrl(string $path = 'backend'): string
    {
        $host = $this->handle . '.' . ($this->rootDomain?->domain ?? request()->getHost());

        return request()->getScheme() . '://' . $host . parse_url(\Backend::url($path), PHP_URL_PATH);
    }

    /**
     * Cambia el host general del panel (panel.…) por el del tenant en los enlaces
     * del backend de un texto o URL: así cada cliente recibe siempre enlaces a SU
     * panel ({handle}.{dominio}). Sin handle o dominio raíz devuelve el texto igual.
     */
    public static function localizeBackendUrls(int $tenantId, string $text): string
    {
        $tenant = static::with('rootDomain')->find($tenantId);

        if (!$tenant || !$tenant->handle || !$tenant->rootDomain?->domain) {
            return $text;
        }

        $panel = parse_url(\Backend::url('/'), PHP_URL_HOST);

        if (!$panel) {
            return $text;
        }

        $host = $tenant->handle . '.' . $tenant->rootDomain->domain;

        return preg_replace('#(https?://)' . preg_quote($panel, '#') . '(/backend/)#i', '$1' . $host . '$2', $text);
    }

    public function isAccessibleBy(\Backend\Models\User $user): bool
    {
        return (int) $this->backend_user_id === (int) $user->id
            || TenantUser::where('tenant_id', $this->id)->where('user_id', $user->id)->exists();
    }

    /**
     * Tenant con el que trabaja un usuario de backend no superadmin.
     *
     * Si el host es el subdominio de un tenant, ese tenant manda y el
     * usuario debe tener acceso (si no, $denied = true y devuelve null: el
     * llamador debe cortar, nunca caer a otro tenant). Si el host no es de
     * ningún tenant (dominio maestro), se usa el criterio histórico:
     * propietario primero, luego el primer TenantUser.
     */
    public static function resolveForBackendUser(\Backend\Models\User $user, ?string $host = null, ?bool &$denied = null): ?self
    {
        $denied = false;
        $host ??= request()->getHost();

        if ($bySubdomain = static::resolveFromSubdomain($host)) {
            if ($bySubdomain->isAccessibleBy($user)) {
                return $bySubdomain;
            }
            $denied = true;
            return null;
        }

        $tenant = static::where('backend_user_id', $user->id)->first();
        if (!$tenant && ($tenantUser = TenantUser::where('user_id', $user->id)->first())) {
            $tenant = static::find($tenantUser->tenant_id);
        }

        return $tenant;
    }

    protected static function lookupByDomain(string $host): ?self
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
