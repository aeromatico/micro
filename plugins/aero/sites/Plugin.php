<?php namespace Aero\Sites;

use Aero\Sites\Classes\Mail\ZeptomailTransport;
use Aero\Sites\Classes\TenantAwareDashManager;
use Aero\Sites\Classes\TenantAwareSiteManager;
use Aero\Sites\Models\Tenant;
use Aero\Sites\Models\TenantUser;
use App;
use Backend;
use BackendAuth;
use Config;
use Event;
use Mail;
use Route;
use System\Classes\PluginBase;
use System\Models\SiteDefinition;

class Plugin extends PluginBase
{
    /**
     * Dependencia requerida: el motor multi-proveedor de IA (API keys, cache, rate-limit).
     */
    public $require = ['Aero.AiFields'];

    public function pluginDetails(): array
    {
        return [
            'name'        => 'Sites',
            'description' => 'SaaS multitenant para micrositios web por nichos',
            'author'      => 'Aero',
            'icon'        => 'icon-globe',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function register(): void
    {
        $this->registerNiches();
        $this->registerConsoleCommand('aero.sites:assign-themes', \Aero\Sites\Console\AssignDesignThemes::class);
        $this->registerConsoleCommand('aero.sites:release-expired-signups', \Aero\Sites\Console\ReleaseExpiredSignups::class);
        $this->registerConsoleCommand('aero.sites:expire-trials', \Aero\Sites\Console\ExpireTrials::class);
        $this->registerConsoleCommand('aero.sites:publish-dashboard', \Aero\Sites\Console\PublishDashboard::class);
        $this->registerConsoleCommand('aero.sites:refresh-devlog', \Aero\Sites\Console\RefreshDevLog::class);
        $this->registerConsoleCommand('aero.sites:generate-renewals', \Aero\Sites\Console\GenerateRenewals::class);
        $this->registerConsoleCommand('aero.sites:expire-renewals', \Aero\Sites\Console\ExpireRenewals::class);
        $this->registerConsoleCommand('aero.sites:purge-event-logs', \Aero\Sites\Console\PurgeEventLogs::class);
    }

    public function boot(): void
    {
        $this->registerApiRoutes();
        $this->bootPanelRootRedirect();
        $this->bootSiteContext();
        $this->bootDashboardIndicators();
        $this->bootDashboardGuards();
        $this->bootSiteSwitcherVisibility();
        $this->bootRainLabIntegration();
        $this->bootHelloIntegration();
        $this->bootApiIntegration();
        $this->bootChatbotsIntegration();
        $this->bootWorkflowsIntegration();
        $this->bootBackendCompactUi();
        $this->bootPaySignupBridge();
        $this->bootPayRenewalBridge();
        $this->bootZeptomailMailer();
        $this->bootProFeaturesGate();
        $this->bootHideSystemMenuForTenants();
        $this->bootImpersonationBar();
        $this->bootTenantSwitcher();
        $this->bootMenuSearch();
        \Aero\Sites\Classes\ListExporter::boot();
    }

    /**
     * El menú "Ajustes" (October.System → system) se registra con
     * `permissions => []`, así que el núcleo lo muestra a CUALQUIER usuario
     * del backend sin filtrar por permiso — incluidos los tenants, que no
     * deberían ver la configuración global de la plataforma (solo la suya,
     * en "Sitio Web → Configuración"). Se oculta para cualquier usuario que
     * resuelva a un tenant y no sea superadmin (tenant_admin, tenant_admin_pro
     * o un rol a medida asignado a un tenant).
     */
    protected function bootHideSystemMenuForTenants(): void
    {
        Event::listen('backend.menu.extendItems', function ($manager) {
            $user = \BackendAuth::getUser();

            if (!$user || $user->is_superuser) {
                return;
            }

            if (\Aero\Sites\Models\Tenant::resolveForBackendUser($user)) {
                $manager->removeMainMenuItem('October.System', 'system');
            }
        });
    }

    /**
     * Pantallas PRO (Settings → Sites): el tenant_admin regular ve la
     * invitación a mejorar el plan en lugar de la pantalla. Además, el rol
     * tenant_admin_pro replica siempre los permisos de tenant_admin.
     */
    protected function bootProFeaturesGate(): void
    {
        Event::listen('backend.page.beforeDisplay', function ($controller, $action, $params) {
            if ($controller instanceof \Aero\Sites\Controllers\Upgrade) {
                return;
            }
            $key = \Aero\Sites\Classes\ProFeatures::keyForController($controller);
            $user = \BackendAuth::getUser();
            if (!\Aero\Sites\Classes\ProFeatures::blocks($user, $key)
                && !\Aero\Sites\Classes\ProFeatures::blocksByPlan($user, $controller)) {
                return;
            }
            if (request()->ajax()) {
                throw new \ApplicationException('Esta función es parte del plan PRO.');
            }
            return \Backend::redirect('aero/sites/upgrade?from=' . urlencode($key));
        });

        Event::listen('backend.form.extendFields', function ($widget) {
            $controller = $widget->getController();
            if ($controller) {
                \Aero\Sites\Classes\ProFeatures::lockFormElements($widget, $controller);
            }
        });

        \Backend\Models\UserRole::extend(function ($model) {
            $model->bindEvent('model.afterSave', function () use ($model) {
                if ($model->code !== \Aero\Sites\Classes\ProFeatures::ROLE_REGULAR) {
                    return;
                }
                \Backend\Models\UserRole::where('code', \Aero\Sites\Classes\ProFeatures::ROLE_PRO)
                    ->update(['permissions' => json_encode($model->permissions ?? [])]);
            });
        });
    }

    /**
     * SMTP quedó rechazado por Zoho (535 Authentication Failed, tanto con
     * el token de API como con la password SMTP dedicada del dashboard),
     * mientras la misma cuenta sí envía por su API HTTP. Este driver evita
     * SMTP por completo — ver conversación 2026-09-16.
     */
    protected function bootZeptomailMailer(): void
    {
        // Deben registrarse ANTES de Mail::extend(): esa llamada resuelve
        // 'mail.manager' por primera vez, lo que dispara de inmediato el
        // evento system.mail.applyConfigValues — si el listener llega
        // después, esa primera resolución queda sin el mailer configurado.
        Event::listen('system.mail.getSendModeOptions', function (&$options) {
            $options['zeptomail'] = 'Zeptomail (API)';
        });

        Event::listen('system.mail.applyConfigValues', function ($settings) {
            if ($settings->send_mode !== 'zeptomail') {
                return;
            }

            Config::set('mail.mailers.zeptomail', [
                'transport' => 'zeptomail',
                'token' => config('services.zeptomail.token'),
                'endpoint' => config('services.zeptomail.endpoint'),
            ]);
        });

        Mail::extend('zeptomail', function (array $config) {
            return new ZeptomailTransport(
                $config['token'] ?? config('services.zeptomail.token'),
                $config['endpoint'] ?? config('services.zeptomail.endpoint')
            );
        });
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('aero.sites:expire-trials')->dailyAt('03:40');
        // Cada minuto, no cada hora: la ventana real
        // (Settings::getSignupPaymentTtlMinutes(), default 3 min) es corta
        // a propósito — con un cron horario, un alta expirada podría
        // quedar "reservada" hasta 59 minutos de más antes de liberarse.
        $schedule->command('aero.sites:release-expired-signups')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('aero.sites:generate-renewals')
            ->dailyAt('03:50')
            ->withoutOverlapping()
            ->onOneServer();
        $schedule->command('aero.sites:expire-renewals')
            ->dailyAt('03:55')
            ->withoutOverlapping()
            ->onOneServer();
        // Retención de 30 días del registro de eventos (llegó a 2,9 GB).
        $schedule->command('aero.sites:purge-event-logs --days=30')
            ->dailyAt('04:20')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Cuando aero/pay confirma el pago de un QR de alta (webhook del banco
     * o reconciliación), busca el tenant reservado con ese
     * `signup_payment_reference` y termina de aprovisionarlo (site
     * definition, dominio, páginas del niche) — el usuario administrador se
     * crea aparte, cuando el propio dueño completa el formulario final del
     * wizard (ver SignupWizard::onCreateAdmin). Nunca deja escapar una
     * excepción: si algo falla aquí, el webhook de aero/pay igual debe
     * terminar de procesarse y el pago quedar registrado.
     */
    protected function bootPaySignupBridge(): void
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return;
        }

        Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            $tenant = \Aero\Sites\Models\Tenant::where('signup_payment_reference', $qrCode->internal_reference)
                ->where('status', 'pending_payment')
                ->first();

            if (!$tenant) {
                return;
            }

            try {
                app(\Aero\Sites\Classes\TenantProvisioner::class)->provisionSite($tenant);
                $tenant->status = 'active';
                $tenant->plan_expires_at = $tenant->billing_period === 'annual' ? now()->addYear() : now()->addMonth();
                $tenant->save();
            } catch (\Exception $e) {
                \Log::error("Aero\\Sites: fallo al aprovisionar el sitio del tenant {$tenant->id} tras pago confirmado: " . $e->getMessage());
            }
        });
    }

    /**
     * Espejo de bootPaySignupBridge() para renovaciones: cuando se confirma
     * el pago de un QR generado por Console\GenerateRenewals, marca la
     * PlanRenewal como pagada, extiende plan_expires_at del tenant desde su
     * fecha anterior (no desde now(), para no perder/regalar días según
     * cuándo exactamente pague dentro de la ventana de 5 días) y otorga los
     * créditos del ciclo. Mismo evento que el puente de alta —cada uno
     * revisa si la referencia le pertenece, sin pisarse— y nunca deja
     * escapar una excepción por el mismo motivo que el otro puente.
     */
    protected function bootPayRenewalBridge(): void
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return;
        }

        Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            $renewal = \Aero\Sites\Models\PlanRenewal::where('payment_reference', $qrCode->internal_reference)
                ->where('status', 'pending')
                ->first();

            if (!$renewal) {
                return;
            }

            try {
                $tenant = $renewal->tenant;
                if (!$tenant) {
                    return;
                }

                $renewal->status = 'paid';
                $renewal->paid_at = now();
                $renewal->save();

                $base = ($tenant->plan_expires_at && $tenant->plan_expires_at->gt(now())) ? $tenant->plan_expires_at : now();
                $tenant->plan_expires_at = $renewal->period === 'annual' ? $base->copy()->addYear() : $base->copy()->addMonth();
                $tenant->save();

                \Aero\Sites\Classes\PlanCredits::grant($tenant, "renewal:{$renewal->id}");

                if (class_exists(\Aero\Notify\Classes\Notify::class)) {
                    try {
                        \Aero\Notify\Classes\Notify::fire('sites.tenant.plan_renewed', [
                            'tenant_name'     => $tenant->name,
                            'plan_expires_at' => $tenant->plan_expires_at->toFormattedDateString(),
                        ], ['tenant_id' => $tenant->id]);
                    } catch (\Throwable $e) {
                        \Log::error('Aero.Sites: fallo notificando sites.tenant.plan_renewed: ' . $e->getMessage());
                    }
                }
            } catch (\Throwable $e) {
                \Log::error("Aero\\Sites: fallo procesando renovación pagada (tenant {$renewal->tenant_id}): " . $e->getMessage());
            }
        });
    }

    /**
     * Nodo de Aero.Workflows con el estado del plan del tenant. Dependencia
     * blanda: si Aero.Workflows no está instalado este evento nunca se dispara.
     */
    protected function bootWorkflowsIntegration(): void
    {
        Event::listen('aero.workflows.registerNodes', fn () => \Aero\Sites\Classes\Workflows\PlanNodes::definitions());
    }

    /**
     * Aero.Chatbots no conoce el contenido del microsite ni los datos de
     * contacto de un tenant — pregunta por evento qué "AI tools" hay
     * disponibles para el modo Súper IA. Dependencia blanda: si
     * Aero.Chatbots no está instalado, este listener simplemente nunca se
     * dispara.
     */
    protected function bootChatbotsIntegration(): void
    {
        Event::listen('aero.chatbots.registerAiTools', function () {
            return [
                'sites_contact_get' => [
                    'description' => 'Obtiene los datos de contacto del negocio: nombre, teléfono, WhatsApp, email y dirección.',
                    'category'    => 'site',
                    'parameters'  => ['type' => 'object', 'properties' => []],
                    'handler'     => [\Aero\Sites\Classes\Ai\ChatbotTools::class, 'getContactInfo'],
                ],
                'sites_landing_get' => [
                    'description' => 'Obtiene el contenido publicado de una página del sitio web del negocio (por defecto, la página de inicio).',
                    'category'    => 'site',
                    'parameters'  => [
                        'type'       => 'object',
                        'properties' => [
                            'slug' => [
                                'type'        => 'string',
                                'description' => 'Slug de la página a consultar. Vacío para la página de inicio.',
                            ],
                        ],
                    ],
                    'handler' => [\Aero\Sites\Classes\Ai\ChatbotTools::class, 'getLandingContent'],
                ],
            ];
        });
    }

    /**
     * Densifica el backend cuando esta activo el modo de menu izquierdo
     * (Settings > Backend Preferences > menu_mode = left): el rail de
     * iconos, el sidenav de seccion, tablas, formularios y el dashboard
     * quedan ~40% mas compactos. Ver assets/css/backend-compact.css.
     */
    protected function bootBackendCompactUi(): void
    {
        Event::listen('backend.layout.extendHead', function ($controller, $context = 'default') {
            // Login (layout auth): rediseño propio; el resto del backend, el compacto.
            $file = $context === 'auth' ? 'backend-login.css' : ($context === 'default' ? 'backend-compact.css' : null);
            if ($file === null) {
                return '';
            }

            $cssPath = plugins_path('aero/sites/assets/css/' . $file);
            if (!file_exists($cssPath)) {
                return '';
            }

            $assetUrl = \Url::asset('/plugins/aero/sites/assets/css/' . $file) . '?v=' . filemtime($cssPath);

            $tags = '<link rel="stylesheet" href="' . $assetUrl . '">';

            // Video de fondo del login (YouTube vía IFrame API): mudo, en bucle, sin
            // interfaz de YouTube; se muestra solo cuando ya reproduce.
            if ($context === 'auth') {
                $tags .= '<script src="https://www.youtube.com/iframe_api" async></script>'
                    . '<script>(function(){var IDS=["ogpw_9oJl58","xDgkLihHmdc","V871IYGsa-s"],ID=IDS[Math.floor(Math.random()*IDS.length)],box;'
                    . 'document.addEventListener("DOMContentLoaded",function(){box=document.createElement("div");box.className="lg-video";box.setAttribute("aria-hidden","true");box.innerHTML="<div id=\\"lg-yt\\"></div>";document.body.prepend(box)});'
                    . 'window.onYouTubeIframeAPIReady=function(){var go=function(){if(!box)return setTimeout(go,50);'
                    . 'new YT.Player("lg-yt",{videoId:ID,host:"https://www.youtube-nocookie.com",'
                    . 'playerVars:{autoplay:1,mute:1,controls:0,disablekb:1,fs:0,rel:0,modestbranding:1,iv_load_policy:3,cc_load_policy:0,playsinline:1,loop:1,playlist:ID,enablejsapi:1,origin:location.origin},'
                    . 'events:{onReady:function(e){e.target.mute();e.target.playVideo()},'
                    . 'onStateChange:function(e){if(e.data===1)box.classList.add("is-playing");else if(e.data===2||e.data===0||e.data===-1){e.target.playVideo()}}}})};go()}'
                    . '})()</script>';
            }

            return $tags;
        });
    }

    /**
     * La raíz del host del panel (APP_URL, panel.…) solo sirve para entrar: va
     * directo al backend. El resto de hosts (market.com.bo, tenants) no cambia.
     */
    protected function bootPanelRootRedirect(): void
    {
        if (App::runningInConsole()) {
            return;
        }

        $panelHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (!$panelHost) {
            return;
        }

        Route::middleware(['web'])->domain($panelHost)->get('/', fn () => redirect(Backend::url('/')));
    }

    /**
     * CSS compartido de las ventanas flotantes del panel (selector de espacio
     * y buscador de menú): <dialog> modal centrado, fondo sólido y telón con
     * desenfoque, así no dependen de dónde esté el rail ni de si se contrae.
     */
    protected function floatingModalCss(): string
    {
        return '<style>'
            . 'dialog.aero-modal{border:0;padding:0;border-radius:16px;background:#111827;color:#f9fafb;width:min(560px,92vw);max-height:76vh;'
            . 'box-shadow:0 24px 60px rgba(0,0,0,.55),0 0 0 1px rgba(255,255,255,.08);overflow:hidden;font-size:14px;'
            . 'margin:12vh auto auto;animation:aero-modal-in .14s ease-out}'
            . 'dialog.aero-modal.is-center{margin:auto;width:min(420px,92vw)}'
            . 'dialog.aero-modal::backdrop{background:rgba(3,7,18,.55);backdrop-filter:blur(4px);-webkit-backdrop-filter:blur(4px)}'
            . '@keyframes aero-modal-in{from{opacity:0;transform:translateY(-8px) scale(.98)}to{opacity:1;transform:none}}'
            . 'dialog.aero-modal .am-head{display:flex;align-items:center;gap:10px;padding:14px 18px;border-bottom:1px solid rgba(255,255,255,.08)}'
            . 'dialog.aero-modal .am-head i{opacity:.6;font-size:16px}'
            . 'dialog.aero-modal .am-head input{flex:1;background:transparent;border:0;outline:0;color:#f9fafb;font-size:16px;padding:0;box-shadow:none}'
            . 'dialog.aero-modal .am-title{font-weight:600;font-size:15px}'
            . 'dialog.aero-modal .am-list{padding:8px;overflow-y:auto;max-height:calc(76vh - 110px)}'
            . 'dialog.aero-modal .am-list a{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border-radius:10px;color:#f9fafb;text-decoration:none;cursor:pointer}'
            . 'dialog.aero-modal .am-list a small{color:#9ca3af;font-size:12px}'
            . 'dialog.aero-modal .am-list a:hover,dialog.aero-modal .am-list a.is-sel{background:#1f2937}'
            . 'dialog.aero-modal .am-list a.is-current{background:#1e3a8a}'
            . 'dialog.aero-modal .am-empty{padding:18px;text-align:center;color:#9ca3af}'
            . 'dialog.aero-modal .am-foot{padding:8px 18px;border-top:1px solid rgba(255,255,255,.08);color:#9ca3af;font-size:12px;display:flex;gap:14px}'
            . 'dialog.aero-modal kbd{background:#1f2937;border-radius:4px;padding:1px 6px;font-size:11px;color:#d1d5db}'
            . '</style>';
    }

    /**
     * Selector de espacio en el panel general (panel.…): quien administra más
     * de un tenant elige con cuál trabaja; el primero es el propio. Es un botón
     * del menú inferior (encima de «Vista previa del Sitio») que abre una
     * ventana flotante centrada. Elegir = recargar la página actual con
     * ?tenant={handle} (ver Tenant::resolveForBackendUser). En el subdominio
     * de un tenant no aparece.
     */
    protected function bootTenantSwitcher(): void
    {
        Event::listen('backend.layout.extendMainMenuToolbar', function () {
            $user = BackendAuth::getUser();
            if (!$user || $user->is_superuser || BackendAuth::isImpersonator()) {
                return '';
            }

            if (Tenant::resolveFromSubdomain(request()->getHost())) {
                return '';
            }

            $tenants = Tenant::accessibleFor($user);
            if ($tenants->count() < 2) {
                return '';
            }

            $current = Tenant::resolveForBackendUser($user);
            $options = $tenants->map(fn ($t) => [
                'handle'  => $t->handle,
                'label'   => $t->name,
                'own'     => (int) $t->backend_user_id === (int) $user->id,
                'current' => $current && $current->id === $t->id,
            ])->all();
            $json = json_encode($options, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
            $name = e($current?->name ?? 'Espacio');

            return '<div class="toolbar-item fix-width" style="padding:0"><ul class="mainmenu-items" data-main-menu style="margin:0;padding:0">'
                . '<li class="mainmenu-item" id="tenant-switch" title="Cambiar de espacio"><a href="javascript:;">'
                . '<span class="nav-icon"><i class="icon-exchange"></i></span><span class="nav-label">' . $name . '</span></a></li></ul></div>'
                . $this->floatingModalCss()
                . '<script>(function(){var o=' . $json . ';function init(){var li=document.getElementById("tenant-switch");if(!li)return;'
                . 'li.addEventListener("click",function(e){e.preventDefault();var d=document.createElement("dialog");d.className="aero-modal is-center";'
                . 'd.innerHTML=\'<div class="am-head"><i class="icon-exchange"></i><span class="am-title">Cambiar de espacio</span></div><div class="am-list"></div>\';'
                . 'var box=d.querySelector(".am-list");'
                . 'o.forEach(function(t){var a=document.createElement("a");if(t.current)a.className="is-current";var n=document.createElement("span");n.textContent=t.label;a.appendChild(n);'
                . 'var s=document.createElement("small");s.textContent=t.current?"Actual":(t.own?"Tu espacio":"Miembro");a.appendChild(s);'
                . 'a.onclick=function(){var u=new URL(location.href);u.searchParams.set("tenant",t.handle);location.href=u.toString()};box.appendChild(a)});'
                . 'd.addEventListener("click",function(ev){if(ev.target===d)d.close()});d.addEventListener("close",function(){d.remove()});'
                . 'document.body.appendChild(d);d.showModal()})}'
                . 'document.readyState==="loading"?document.addEventListener("DOMContentLoaded",init):init()})()</script>';
        });
    }

    /**
     * Buscador rápido del menú: primer ítem del rail izquierdo. Abre una
     * ventana flotante centrada (clic, «/» o Ctrl+K) que busca en los ítems
     * principales y en los del menú lateral que el usuario puede ver. Los
     * ítems salen del propio BackendMenu, así que ya vienen filtrados por
     * permisos (y por el tenant) igual que el menú.
     */
    protected function bootMenuSearch(): void
    {
        Event::listen('backend.layout.extendHead', function ($controller, $context = 'default') {
            if ($context !== 'default' || !BackendAuth::getUser()) {
                return '';
            }

            $entries = [];
            $seen = [];
            $add = function (string $group, $item) use (&$entries, &$seen) {
                $label = trim(strip_tags((string) __($item->label)));
                $url = (string) $item->url;
                if ($label === '' || $url === '' || $url === '#' || str_starts_with($url, 'javascript') || isset($seen[$url . '|' . $label])) {
                    return;
                }
                $seen[$url . '|' . $label] = true;
                $entries[] = ['l' => $label, 'g' => $group, 'u' => $url];
            };

            foreach (\BackendMenu::listMainMenuItemsWithSubitems() as $info) {
                $main = $info->mainMenuItem;
                $group = trim(strip_tags((string) __($main->label)));
                $add('', $main);
                foreach ($info->subMenuItems as $sub) {
                    if (in_array($sub->itemType ?? null, ['section', 'ruler'], true) || !\BackendMenu::isSideMenuItemVisible($sub)) {
                        continue;
                    }
                    $add($group, $sub);
                }
            }

            if (!$entries) {
                return '';
            }

            $json = json_encode($entries, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return '<style>#menu-search-item{position:sticky;top:0;z-index:5;background-color:#1f2937}#menu-search-item .ms-kbd{margin-left:6px;padding:1px 6px;border:1px solid rgba(255,255,255,.3);border-bottom-width:2px;border-radius:5px;background:rgba(255,255,255,.08);color:inherit;font:600 10px/1.4 ui-monospace,monospace;vertical-align:middle;white-space:nowrap}</style>'
                . $this->floatingModalCss()
                . '<script>(function(){var E=' . $json . ';'
                . 'function n(t){return String(t).normalize("NFD").replace(/[̀-ͯ]/g,"").toLowerCase()}'
                . 'E.forEach(function(e){e.k=n(e.l+" "+e.g)});'
                . 'function init(){var ul=document.querySelector("#layout-mainmenu-left ul.mainmenu-general");if(!ul||document.getElementById("menu-search-item"))return;'
                . 'var li=document.createElement("li");li.id="menu-search-item";li.className="mainmenu-item";li.title="Buscar en el menú (/ o Ctrl+K)";'
                . 'li.innerHTML=\'<a href="javascript:;"><span class="nav-icon"><i class="icon-search"></i></span><span class="nav-label">Buscar <kbd class="ms-kbd"></kbd></span></a>\';'
                . 'li.querySelector(".ms-kbd").textContent=/Mac|iPhone|iPad/.test(navigator.platform)?"⌘ K":"Ctrl K";'
                . 'ul.insertBefore(li,ul.firstChild);'
                . 'for(var p=ul,bg;p&&p!==document;p=p.parentElement){bg=getComputedStyle(p).backgroundColor;if(bg&&bg!=="transparent"&&!/rgba\(.*,\s*0\)$/.test(bg)){li.style.backgroundColor=bg;break}}'
                . 'var dlg=null,sel=0;'
                . 'function render(q){var box=dlg.querySelector(".am-list");q=n(q).trim();'
                . 'var shown=(q?E.filter(function(e){return q.split(/\s+/).every(function(w){return e.k.indexOf(w)>-1})}):E.filter(function(e){return !e.g})).slice(0,12);'
                . 'sel=0;box.innerHTML="";if(!shown.length){box.innerHTML=\'<div class="am-empty">Sin resultados</div>\';return}'
                . 'shown.forEach(function(e,i){var a=document.createElement("a");a.href=e.u;a.className=i?"":"is-sel";var t=document.createElement("span");t.textContent=e.l;a.appendChild(t);'
                . 'if(e.g){var s=document.createElement("small");s.textContent=e.g;a.appendChild(s)}box.appendChild(a)})}'
                . 'function mark(d){var as=dlg.querySelectorAll(".am-list a");if(!as.length)return;as[sel].classList.remove("is-sel");'
                . 'sel=(sel+d+as.length)%as.length;as[sel].classList.add("is-sel");as[sel].scrollIntoView({block:"nearest"})}'
                . 'function open(){if(dlg)return;dlg=document.createElement("dialog");dlg.className="aero-modal";'
                . 'dlg.innerHTML=\'<div class="am-head"><i class="icon-search"></i><input type="text" placeholder="Buscar en el menú…" autocomplete="off"></div><div class="am-list"></div>'
                . '<div class="am-foot"><span><kbd>↑</kbd> <kbd>↓</kbd> navegar</span><span><kbd>Enter</kbd> abrir</span><span><kbd>Esc</kbd> cerrar</span></div>\';'
                . 'document.body.appendChild(dlg);var inp=dlg.querySelector("input");render("");dlg.showModal();inp.focus();'
                . 'inp.addEventListener("input",function(){render(inp.value)});'
                . 'inp.addEventListener("keydown",function(ev){if(ev.key==="ArrowDown"){ev.preventDefault();mark(1)}'
                . 'else if(ev.key==="ArrowUp"){ev.preventDefault();mark(-1)}'
                . 'else if(ev.key==="Enter"){ev.preventDefault();var a=dlg.querySelectorAll(".am-list a")[sel];if(a)location.href=a.href}});'
                . 'dlg.addEventListener("click",function(ev){if(ev.target===dlg)dlg.close()});'
                . 'dlg.addEventListener("close",function(){dlg.remove();dlg=null})}'
                . 'li.addEventListener("click",function(ev){ev.preventDefault();open()});'
                . 'document.addEventListener("keydown",function(ev){var t=ev.target,typing=t&&(t.isContentEditable||/^(input|textarea|select)$/i.test(t.tagName));'
                . 'if((ev.key==="k"&&(ev.ctrlKey||ev.metaKey))||(ev.key==="/"&&!typing&&!ev.ctrlKey&&!ev.metaKey)){ev.preventDefault();open()}})}'
                . 'document.readyState==="loading"?document.addEventListener("DOMContentLoaded",init):init()})()</script>';
        });
    }

    /**
     * Barra fija mientras un superadmin está "ingresado como" el admin de un
     * tenant (Tenants → Ingresar), con salida a la sesión original.
     */
    protected function bootImpersonationBar(): void
    {
        Route::middleware(['web'])->get('backend/aero/sites/stop-impersonation', function () {
            if (\BackendAuth::isImpersonator()) {
                \BackendAuth::stopImpersonate();
            }
            return redirect(Backend::url('aero/sites/tenants'));
        });

        Event::listen('backend.layout.extendHead', function ($controller, $context = 'default') {
            if ($context !== 'default' || !\BackendAuth::isImpersonator()) {
                return '';
            }
            $who = e(\BackendAuth::getUser()?->login ?? '');
            $url = Backend::url('aero/sites/stop-impersonation');

            return '<style>#imp-bar{position:fixed;bottom:12px;left:50%;transform:translateX(-50%);z-index:99999;'
                . 'background:#b45309;color:#fff;padding:8px 16px;border-radius:999px;font-size:13px;box-shadow:0 2px 10px rgba(0,0,0,.3)}'
                . '#imp-bar a{color:#fff;font-weight:700;text-decoration:underline;margin-left:10px}</style>'
                . '<script>document.addEventListener("DOMContentLoaded",function(){var d=document.createElement("div");d.id="imp-bar";'
                . 'd.innerHTML="Viendo como <b>' . $who . '</b> <a href=\"' . $url . '\">Volver a mi cuenta</a>";document.body.appendChild(d)})</script>';
        });
    }

    /**
     * Aero.Api no conoce a ningún plugin de tenants: pregunta por evento quién
     * es el dueño actual para aislar las API keys por tenant. Mismo patrón que
     * bootHelloIntegration(), aplicado al par owner_type/owner_id genérico de
     * ApiKey en vez de un tenant_id fijo.
     */
    protected function bootApiIntegration(): void
    {
        if (!class_exists(\Aero\Api\Classes\ApiAuth::class)) {
            return;
        }

        Event::listen('aero.api.resolveOwner', function (&$owner) {
            if ($owner !== null) {
                return;
            }

            $tenantId = (new class { use \Aero\Sites\Traits\ResolvesCurrentTenant; public function id() { return $this->getCurrentTenantId(); } })->id();

            if ($tenantId) {
                $owner = ['type' => \Aero\Sites\Models\Tenant::class, 'id' => $tenantId];
            }
        });
    }

    /**
     * Aero.Hello es un plugin independiente: no conoce a Sites ni a los tenants.
     * Expone `tenant_id` como columna suelta y pregunta por evento a quién
     * pertenece el contexto actual. Aquí Sites responde esa pregunta y repone
     * las relaciones `tenant` que Hello ya no puede declarar por sí mismo.
     *
     * Dependencia blanda en las dos direcciones: sin Hello instalado esto no
     * hace nada, y sin Sites instalado Hello funciona sin aislamiento.
     */
    protected function bootHelloIntegration(): void
    {
        if (!class_exists(\Aero\Hello\Models\Profile::class)) {
            return;
        }

        Event::listen('aero.hello.resolveOwnerId', function (&$id) {
            if ($id !== null) {
                return;
            }

            $id = (new class { use \Aero\Sites\Traits\ResolvesCurrentTenant; public function id() { return $this->getCurrentTenantId(); } })->id();
        });

        foreach ([
            \Aero\Hello\Models\Profile::class,
            \Aero\Hello\Models\Contact::class,
            \Aero\Hello\Models\Conversation::class,
            \Aero\Hello\Models\Call::class,
        ] as $model) {
            $model::extend(function ($model) {
                $model->belongsTo['tenant'] = [\Aero\Sites\Models\Tenant::class];
            });
        }
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
                    'label'  => 'Tenants asignados',
                    'type'   => 'repeater',
                    'tab'    => 'Tenants',
                    'prompt' => 'Asignar tenant',
                    'form'   => [
                        'fields' => [
                            'tenant_id' => ['label' => 'Tenant', 'type' => 'dropdown'],
                            'role'      => ['label' => 'Rol',    'type' => 'dropdown'],
                        ],
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

            // El subdominio del host decide el tenant. Si el usuario no tiene
            // acceso a ese tenant se corta acá: devolver null haría caer al
            // Primary Site y mostraría datos de toda la plataforma.
            $tenant = Tenant::resolveForBackendUser($user, null, $denied);
            if ($denied) {
                abort(403, 'No tenés acceso a este espacio.');
            }

            if ($tenant?->site_id) {
                return SiteDefinition::find($tenant->site_id);
            }

            return null;
        });
    }

    /**
     * Oculta del panel "Indicators" del dashboard las fuentes de datos que
     * describen infraestructura global (no scopeable por sitio) cuando quien
     * mira el dashboard es un tenant_admin. Se resuelve de forma perezosa
     * (App::extend solo corre al primer App::make('dashboard.dashboards')
     * de la request) para no consultar BackendAuth antes de que el usuario
     * de backend esté disponible — mismo motivo que bootSiteContext().
     */
    protected function bootDashboardIndicators(): void
    {
        App::extend('dashboard.dashboards', function ($manager) {
            $user = BackendAuth::getUser();
            if (!$user || $user->is_superuser) {
                return $manager;
            }

            $proxy = new TenantAwareDashManager();

            $dataSources = new \ReflectionProperty(\Dashboard\Classes\DashManager::class, 'dataSources');
            $dataSources->setAccessible(true);
            $dataSources->setValue($proxy, $dataSources->getValue($manager));

            return $proxy;
        });
    }

    /**
     * dashboard.manage permite al tenant editar su layout personal (se guarda
     * en la preferencia del usuario). Estas guardas impiden que, con ese mismo
     * permiso, modifique lo que ven TODOS: el layout global/del sistema y la
     * pantalla de gestión de dashboards (roles, visibilidad global).
     */
    protected function bootDashboardGuards(): void
    {
        $isRestricted = function (): bool {
            $user = BackendAuth::getUser();

            return $user && !$user->is_superuser;
        };

        Event::listen('backend.page.beforeDisplay', function ($controller) use ($isRestricted) {
            if ($controller instanceof \Dashboard\Controllers\Dashboards && $isRestricted()) {
                return Backend::redirect('dashboard');
            }
        });

        // Widgets que leen datos de toda la instalación (no acotables por
        // tenant): solo el superadmin puede agregarlos al dashboard.
        Event::listen('system.reportwidgets.extendItems', function ($manager) use ($isRestricted) {
            if (!$isRestricted()) {
                return;
            }

            foreach ([
                \System\ReportWidgets\Status::class,
                \Cms\ReportWidgets\ActiveTheme::class,
                'Vdlp\\Redirect\\ReportWidgets\\TopTenRedirects',
                'Vdlp\\Redirect\\VueComponents\\CreateRedirect',
            ] as $class) {
                $manager->removeReportWidget($class);
            }
        });

        if (!class_exists(\Dashboard\Models\Dashboard::class)) {
            return;
        }

        \Dashboard\Models\Dashboard::extend(function ($model) use ($isRestricted) {
            $guard = function () use ($model, $isRestricted) {
                if (!$isRestricted()) {
                    return;
                }

                // Altas por syncAll() (sistema) no pasan por esta guarda.
                $touchesShared = $model->exists
                    ? ($model->getOriginal('is_global') || $model->getOriginal('is_system') || $model->is_global)
                    : ($model->is_global && !$model->is_system);

                if ($touchesShared) {
                    throw new \ApplicationException('Solo el superadministrador puede cambiar el dashboard global.');
                }
            };

            $model->bindEvent('model.beforeSave', $guard);
            $model->bindEvent('model.beforeDelete', $guard);
        });
    }

    /**
     * Reemplaza el SiteManager del contenedor para que hasMultiEditSite()
     * devuelva false a cualquier backend user que no sea superadmin, y así
     * Backend\Widgets\SiteSwitcher no dibuje el selector de sitios — ver
     * TenantAwareSiteManager. No se copia estado por reflection porque, a
     * diferencia de DashManager, SiteManager no acumula nada entre boot() y
     * la primera resolución de esta request.
     */
    protected function bootSiteSwitcherVisibility(): void
    {
        App::extend('system.sites', function () {
            return new TenantAwareSiteManager();
        });

        // A diferencia de 'dashboard.dashboards', 'system.sites' ya está
        // resuelto por el core antes de que los plugins terminen de bootear
        // (verificado: app()->resolved('system.sites') es true desde el
        // arranque). El Facade Site:: cachea su propia instancia aparte del
        // contenedor y no se entera del extend() si ya resolvió antes —
        // sin este forget, tenant_admin seguiría viendo el selector.
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('system.sites');
    }

    /**
     * Resuelve el tenant del usuario de backend actual (mismo criterio que
     * Aero\Sites\Traits\ResolvesCurrentTenant) y devuelve si su sitio está
     * activado (Tenant.status === 'active'). Usado para ocultar/mostrar el
     * ítem de menú "Sitio Web" según el switch "Sitio activado" en SiteSettings.
     */
    protected function isCurrentTenantSiteActive(): bool
    {
        $user = BackendAuth::getUser();
        if (!$user) {
            return false;
        }

        $tenantId = null;

        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id) {
            $tenantId = Tenant::where('site_id', $site->id)->value('id');
        }

        if (!$tenantId) {
            $tenantId = Tenant::resolveForBackendUser($user)?->id;
        }

        if (!$tenantId) {
            return false;
        }

        return Tenant::where('id', $tenantId)->value('status') === 'active';
    }

    public function registerReportWidgets(): array
    {
        return [
            \Aero\Sites\ReportWidgets\SpeedDial::class => [
                'label'   => 'Acceso rápido',
                'context' => 'dashboard',
            ],
        ];
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

    /** Para los temas: {% if contact_enabled() %}. Resuelve el tenant por el dominio del request. */
    public function registerMarkupTags(): array
    {
        return [
            'functions' => [
                'contact_enabled' => function () {
                    $tenant = \Aero\Sites\Models\Tenant::resolveFromDomain(request()->getHost());

                    return $tenant ? \Aero\Sites\Models\ContactConfig::isEnabledFor($tenant->id) : false;
                },
            ],
            // Uso: {{ page.content | aeroDynamic | raw }} — resuelve los marcadores
            // data-aero-dynamic (ver Classes\DynamicSources).
            'filters' => [
                'aeroDynamic' => fn ($html) => \Aero\Sites\Classes\DynamicSources::render((string) $html),
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
            \Aero\Sites\Components\SignupWizard::class   => 'signupWizard',
            \Aero\Sites\Components\PlatformLeadForm::class => 'platformLeadForm',
            \Aero\Sites\Components\BlocksGallery::class  => 'sitesBlocksGallery',
            \Aero\Sites\Components\BlocksPreview::class  => 'sitesBlocksPreview',
        ];
    }

    public function registerNavigation(): array
    {
        $miSitioMenu = [
            'configuracion' => [
                'label'       => 'aero.sites::lang.menu.settings',
                'icon'        => 'icon-cog',
                'url'         => Backend::url('aero/sites/sitesettings'),
                'permissions' => ['aero.sites.manage_seo'],
            ],
        ];

        // "Sitio Web" solo visible si el tenant activó el switch "Sitio activado"
        // en SiteSettings (Tenant.status === 'active'). "Configuración" queda
        // siempre visible: es donde vive el switch para poder reactivarlo.
        if ($this->isCurrentTenantSiteActive()) {
            $miSitioMenu = [
                'contenidos' => [
                    'label'       => 'aero.sites::lang.menu.contents',
                    'icon'        => 'icon-file-text-o',
                    'url'         => Backend::url('aero/sites/contenteditor'),
                    'permissions' => ['aero.sites.manage_pages'],
                ],
                'paginas' => [
                    'label'       => 'aero.sites::lang.menu.pages',
                    'icon'        => 'icon-copy',
                    'url'         => Backend::url('aero/sites/pages'),
                    'permissions' => ['aero.sites.manage_pages'],
                ],
            ] + $miSitioMenu;
        }

        return [
            // Panel del tenant admin — acceso simplificado al propio sitio
            'sitio-web' => [
                'label'       => 'aero.sites::lang.menu.my_site',
                'url'         => Backend::url('aero/sites/contenteditor'),
                'icon'        => 'icon-desktop',
                'permissions' => ['aero.sites.manage_pages', 'aero.sites.manage_seo'],
                'order'       => 100,
                'sideMenu'    => $miSitioMenu,
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
                    'plans' => [
                        'label'       => 'Planes',
                        'icon'        => 'icon-th-list',
                        'url'         => Backend::url('aero/sites/plans'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'planrenewals' => [
                        'label'       => 'Renovaciones',
                        'icon'        => 'icon-refresh',
                        'url'         => Backend::url('aero/sites/planrenewals'),
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
                    'contactsubmissions' => [
                        'label'       => 'aero.sites::lang.menu.submissions',
                        'icon'        => 'icon-envelope',
                        'url'         => Backend::url('aero/sites/contactsubmissions'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'platformleads' => [
                        'label'       => 'Leads (Trial / Empresa)',
                        'icon'        => 'icon-bullhorn',
                        'url'         => Backend::url('aero/sites/platformleads'),
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
                    'archetypes' => [
                        'label'       => 'aero.sites::lang.menu.archetypes',
                        'icon'        => 'icon-sitemap',
                        'url'         => Backend::url('aero/sites/archetypes'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'designthemes' => [
                        'label'       => 'aero.sites::lang.menu.design_themes',
                        'icon'        => 'icon-paint-brush',
                        'url'         => Backend::url('aero/sites/designthemes'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'profeatures' => [
                        'label'       => 'Funciones PRO',
                        'icon'        => 'icon-star',
                        'url'         => Backend::url('system/settings/update/aero/sites/settings'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                    'componentgallery' => [
                        'label'       => 'aero.sites::lang.menu.component_gallery',
                        'icon'        => 'icon-th-large',
                        'url'         => Backend::url('aero/sites/componentgallery'),
                        'permissions' => ['aero.sites.superadmin'],
                    ],
                ],
            ],
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'Sites',
                'description' => 'API key del banco de imágenes usado por el generador de sitios con IA.',
                'category'    => 'Sistema',
                'icon'        => 'icon-image',
                'class'       => \Aero\Sites\Models\Settings::class,
                'order'       => 510,
                'permissions' => ['aero.sites.superadmin'],
                'keywords'    => 'imagenes fotos unsplash ia generador',
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
    // Niche Drivers
    // -------------------------------------------------------------------------

    protected function registerNiches(): void
    {
        $this->app->singleton(
            \Aero\Sites\Classes\Niches\NicheManager::class,
            fn() => new \Aero\Sites\Classes\Niches\NicheManager()
        );

        Event::listen('aero.sites.registerNiches', function ($manager) {
            $manager->register('generic',             \Aero\Sites\Classes\Niches\GenericNiche::class);
            $manager->register('inmuebles',            \Aero\Sites\Classes\Niches\InmueblesNiche::class);
            $manager->register('consultorio',          \Aero\Sites\Classes\Niches\ConsultorioNiche::class);
            $manager->register('tienda_whatsapp',      \Aero\Sites\Classes\Niches\TiendaWhatsappNiche::class);
            $manager->register('tienda_online',        \Aero\Sites\Classes\Niches\TiendaOnlineNiche::class);
            $manager->register('radioemisora',         \Aero\Sites\Classes\Niches\RadioemisoraNiche::class);
            $manager->register('gimnasios',            \Aero\Sites\Classes\Niches\GimnasiosNiche::class);
            $manager->register('transporte',           \Aero\Sites\Classes\Niches\TransporteNiche::class);
            $manager->register('deportes',             \Aero\Sites\Classes\Niches\DeportesNiche::class);
            $manager->register('restaurante',          \Aero\Sites\Classes\Niches\RestauranteNiche::class);
            $manager->register('peluqueria',           \Aero\Sites\Classes\Niches\PeluqueriaNiche::class);
            $manager->register('libreria',             \Aero\Sites\Classes\Niches\LibreriaNiche::class);
            $manager->register('farmacia',             \Aero\Sites\Classes\Niches\FarmaciaNiche::class);
            $manager->register('veterinaria',          \Aero\Sites\Classes\Niches\VeterinariaNiche::class);
            $manager->register('limpieza_lavanderia',  \Aero\Sites\Classes\Niches\LimpiezaLavanderiaNiche::class);
            $manager->register('hogar',                \Aero\Sites\Classes\Niches\HogarNiche::class);
            $manager->register('electronicos',         \Aero\Sites\Classes\Niches\ElectronicosNiche::class);
            $manager->register('celulares',            \Aero\Sites\Classes\Niches\CelularesNiche::class);
            $manager->register('mercados_abarrotes',   \Aero\Sites\Classes\Niches\MercadosAbarrotesNiche::class);
            $manager->register('servicios_digitales',  \Aero\Sites\Classes\Niches\ServiciosDigitalesNiche::class);
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
