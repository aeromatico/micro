<?php namespace Aero\Crm;

use Backend;
use Event;
use System\Classes\PluginBase;

class Plugin extends PluginBase
{
    public $require = ['Aero.Sites', 'Aero.Hello'];

    public function register(): void
    {
        $this->registerConsoleCommand('crm:generate-cobranza-reminders', \Aero\Crm\Console\GenerateCollectionRemindersCommand::class);
        $this->registerConsoleCommand('crm:sync-hello', \Aero\Crm\Console\SyncHelloContacts::class);
    }

    public function registerSchedule($schedule): void
    {
        $schedule->command('crm:generate-cobranza-reminders')
            ->dailyAt('09:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->name('aero-crm-cobranza-reminders');
    }

    public function registerComponents(): array
    {
        return [
            \Aero\Crm\Components\SupportPortal::class => 'supportPortal',
        ];
    }

    public function pluginDetails(): array
    {
        return [
            'name'        => 'CRM',
            'description' => 'CRM minimalista por tenant — contactos, empresas, leads, pipeline y actividades',
            'author'      => 'Aero',
            'icon'        => 'icon-address-book',
            'homepage'    => 'https://panel.market.com.bo',
        ];
    }

    public function boot(): void
    {
        $this->bootTenantPurgeCleanup();
        $this->bootShopCustomerSync();
        $this->bootPayPaymentBridge();
        $this->bootHelloSync();
        $this->bootSupportToolbarItem();
    }

    /**
     * Hello → CRM: todo contacto de WhatsApp que aparece en el chat se crea o
     * se enlaza en el CRM del tenant, y los cambios de nombre en Hello se
     * copian al CRM (el otro sentido vive en Contact::syncHelloContact()).
     */
    /**
     * «Soporte» en el menú inferior del panel: los tenants abren tickets en la
     * mesa de ayuda de la PLATAFORMA. No confundir con CRM → Tickets (el
     * sistema de tickets propio del tenant). Un superadmin sin tenant ya
     * atiende esos tickets desde CRM → Tickets, así que no lo ve.
     */
    protected function bootSupportToolbarItem(): void
    {
        Event::listen('backend.layout.extendMainMenuToolbar', function () {
            if (!\BackendAuth::getUser() || !\Aero\Crm\Classes\TenantUsers::currentTenantId()) {
                return '';
            }

            return '<div class="toolbar-item fix-width" style="padding:0"><ul class="mainmenu-items" data-main-menu style="margin:0;padding:0"><li class="mainmenu-item" title="Soporte de la plataforma"><a href="' . e(Backend::url('aero/crm/support')) . '"><span class="nav-icon"><i class="icon-life-ring"></i></span><span class="nav-label">Soporte</span></a></li></ul></div>';
        });
    }

    protected function bootHelloSync(): void
    {
        if (!class_exists(\Aero\Hello\Models\ContactIdentity::class)) {
            return;
        }

        \Aero\Hello\Models\ContactIdentity::extend(function ($model) {
            $model->bindEvent('model.afterCreate', function () use ($model) {
                if ($model->platform === 'whatsapp') {
                    try {
                        \Aero\Crm\Classes\HelloSync::mirror($model->contact, $model->external_id);
                    }
                    catch (\Throwable $e) {
                        // Nunca tumbar un mensaje entrante por la sincronía del CRM.
                        \Log::warning('aero.crm: falló la sincronía Hello → CRM: ' . $e->getMessage());
                    }
                }
            });
        });

        \Aero\Hello\Models\Contact::extend(function ($model) {
            $model->bindEvent('model.afterUpdate', function () use ($model) {
                if ($model->isDirty('name') || $model->wasChanged('name')) {
                    try {
                        \Aero\Crm\Classes\HelloSync::pushName($model);
                    }
                    catch (\Throwable $e) {
                        \Log::warning('aero.crm: falló el cambio de nombre Hello → CRM: ' . $e->getMessage());
                    }
                }
            });
        });
    }

    /**
     * Cuando aero/pay confirma el pago de un QR (webhook del banco o
     * reconciliación cada 5 min), busca el cobro con ese `payment_reference`
     * y lo marca pagado sin intervención del vendedor — mismo patrón que
     * aero/shop (ver Aero\Shop\Plugin::bootPayPaymentBridge()). No aplica a
     * cuentas estáticas (sin API): esas nunca disparan este evento, se
     * confirman a mano desde el cobro (ver controllers/collections/_payment_info.htm).
     */
    protected function bootPayPaymentBridge(): void
    {
        if (!class_exists(\Aero\Pay\Models\QrCode::class)) {
            return;
        }

        Event::listen('aero.pay.paymentReceived', function ($payment, $qrCode) {
            if (!$qrCode || $qrCode->origin !== 'crm') {
                return;
            }

            $item = \Aero\Crm\Models\CollectionItem::where('tenant_id', $qrCode->tenant_id)
                ->where('payment_reference', $qrCode->internal_reference)
                ->where('status', 'pending')
                ->first();

            $item?->markAsPaid();
        });
    }

    /**
     * Menú superior propio "CRM" (independiente de "Sitio Web" de Aero.Sites
     * y de "Tienda" de Aero.Shop). "Configuración" siempre visible (es donde
     * está el switch "CRM activado"); el resto de los ítems solo se muestran
     * si el CRM está activado para el tenant actual.
     */
    public function registerNavigation(): array
    {
        $sideMenu = [
            'crm-configuracion' => [
                'label'       => 'Configuración de CRM',
                'icon'        => 'icon-cog',
                'url'         => Backend::url('aero/crm/crmsettings'),
                'permissions' => ['aero.crm.manage_settings'],
            ],
        ];

        // Mesa de ayuda: también para el superadmin sin tenant (sitio principal).
        if ($this->isCrmEnabledForCurrentTenant() || $this->isPlatformDesk()) {
            $sideMenu += [
                'crm-tickets' => [
                    'label'       => 'Tickets',
                    'icon'        => 'icon-life-ring',
                    'url'         => Backend::url('aero/crm/tickets'),
                    'permissions' => ['aero.crm.manage_tickets'],
                ],
                'crm-departamentos' => [
                    'label'       => 'Departamentos',
                    'icon'        => 'icon-sitemap',
                    'url'         => Backend::url('aero/crm/departments'),
                    'permissions' => ['aero.crm.manage_departments'],
                ],
            ];
        }

        if ($this->isCrmEnabledForCurrentTenant()) {
            $sideMenu += [
                'crm-empresas' => [
                    'label'       => 'Empresas',
                    'icon'        => 'icon-building',
                    'url'         => Backend::url('aero/crm/companies'),
                    'permissions' => ['aero.crm.manage_companies'],
                ],
                'crm-contactos' => [
                    'label'       => 'Contactos',
                    'icon'        => 'icon-address-book',
                    'url'         => Backend::url('aero/crm/contacts'),
                    'permissions' => ['aero.crm.manage_contacts'],
                ],
                'crm-listas' => [
                    'label'       => 'Listas',
                    'icon'        => 'icon-list-ul',
                    'url'         => Backend::url('aero/crm/contactlists'),
                    'permissions' => ['aero.crm.manage_contacts'],
                ],
                'crm-cobranzas' => [
                    'label'       => 'Cobranzas',
                    'icon'        => 'icon-money',
                    'url'         => Backend::url('aero/crm/collections'),
                    'permissions' => ['aero.crm.manage_collections'],
                ],
                'crm-leads' => [
                    'label'       => 'Leads',
                    'icon'        => 'icon-bullseye',
                    'url'         => Backend::url('aero/crm/leads'),
                    'permissions' => ['aero.crm.manage_leads'],
                ],
                'crm-pipeline' => [
                    'label'       => 'Pipeline',
                    'icon'        => 'icon-columns',
                    'url'         => Backend::url('aero/crm/deals/board'),
                    'permissions' => ['aero.crm.manage_deals'],
                ],
                'crm-actividades' => [
                    'label'       => 'Actividades',
                    'icon'        => 'icon-tasks',
                    'url'         => Backend::url('aero/crm/activities'),
                    'permissions' => ['aero.crm.manage_activities'],
                ],
                'crm-respuestas' => [
                    'label'       => 'Respuestas',
                    'icon'        => 'icon-comment-o',
                    'url'         => Backend::url('aero/crm/quickreplies'),
                    'permissions' => ['aero.crm.manage_quick_replies'],
                ],
                'crm-equipo' => [
                    'label'       => 'Equipo',
                    'icon'        => 'icon-users',
                    'url'         => Backend::url('aero/crm/team'),
                    'permissions' => ['aero.crm.manage_teams'],
                ],
            ];
        }

        return [
            'crm' => [
                'label'       => 'CRM',
                'url'         => Backend::url('aero/crm/crmsettings'),
                'icon'        => 'icon-address-book',
                'permissions' => [
                    'aero.crm.manage_companies', 'aero.crm.manage_contacts', 'aero.crm.manage_leads',
                    'aero.crm.manage_deals', 'aero.crm.manage_activities', 'aero.crm.manage_teams',
                    'aero.crm.manage_settings', 'aero.crm.manage_collections',
                    'aero.crm.manage_tickets', 'aero.crm.manage_departments', 'aero.crm.manage_quick_replies',
                ],
                'order'       => 160,
                'sideMenu'    => $sideMenu,
            ],
        ];
    }

    /**
     * Resuelve el tenant del usuario de backend actual (mismo criterio que
     * Aero\Sites\Traits\ResolvesCurrentTenant, replicado aquí porque Plugin.php
     * no es un controlador) y devuelve si su CRM está activado.
     */
    protected function isCrmEnabledForCurrentTenant(): bool
    {
        $tenantId = $this->resolveCurrentBackendTenantId();
        if (!$tenantId) {
            return false;
        }

        return (bool) \Aero\Crm\Models\CrmSettings::where('tenant_id', $tenantId)->value('is_enabled');
    }

    /** Superadmin sin tenant resuelto: atiende la mesa de ayuda de la plataforma. */
    protected function isPlatformDesk(): bool
    {
        return (bool) \BackendAuth::getUser()?->is_superuser && !$this->resolveCurrentBackendTenantId();
    }

    protected function resolveCurrentBackendTenantId(): ?int
    {
        $user = \BackendAuth::getUser();
        if (!$user) {
            return null;
        }

        $tenantId = null;

        $site = \System\Classes\SiteManager::instance()->getEditSite();
        if ($site?->id) {
            $tenantId = \Aero\Sites\Models\Tenant::where('site_id', $site->id)->value('id');
        }

        if (!$tenantId) {
            $tenantId = \Aero\Sites\Models\Tenant::resolveForBackendUser($user)?->id;
        }

        return $tenantId ?: null;
    }

    /**
     * Al purgar un tenant (Aero\Sites\Models\Tenant::purge()), borra en cascada
     * todos los datos de crm asociados, para no dejar huérfanos. Orden: hijos
     * antes que padres (deals/activities antes que pipelines/contacts/etc.).
     */
    protected function bootTenantPurgeCleanup(): void
    {
        Event::listen('aero.sites.tenant.purging', function ($tenant) {
            $tenantId = $tenant->id;

            \Aero\Crm\Models\Activity::where('tenant_id', $tenantId)->delete();
            \Aero\Crm\Models\Deal::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\PipelineStage::where('tenant_id', $tenantId)->delete();
            \Aero\Crm\Models\Pipeline::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\Lead::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\CollectionReminderLog::whereIn('collection_item_id', function ($q) use ($tenantId) {
                $q->select('id')->from('aero_crm_collection_items')->where('tenant_id', $tenantId);
            })->delete();
            \Aero\Crm\Models\CollectionReminderRule::where('tenant_id', $tenantId)->delete();
            \Aero\Crm\Models\CollectionItem::where('tenant_id', $tenantId)->delete();
            \Aero\Crm\Models\ContactList::whereIn('id', function ($q) use ($tenantId) {
                $q->select('id')->from('aero_crm_contact_lists')->where('tenant_id', $tenantId);
            })->each(fn ($list) => $list->contacts()->detach());
            \Aero\Crm\Models\ContactList::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\Contact::where('tenant_id', $tenantId)->delete();
            \Aero\Crm\Models\Company::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\TeamMember::whereIn('team_id', function ($q) use ($tenantId) {
                $q->select('id')->from('aero_crm_teams')->where('tenant_id', $tenantId);
            })->delete();
            \Aero\Crm\Models\Team::where('tenant_id', $tenantId)->delete();

            \Aero\Crm\Models\CrmSettings::where('tenant_id', $tenantId)->delete();
        });
    }

    /**
     * Cada vez que se crea un Aero\Shop\Models\Customer, lo espeja como
     * Aero\Crm\Models\Contact (si el CRM está activado para ese tenant).
     * Solo se registra si Aero.Shop está instalado, ya que no es un
     * `$require` de este plugin.
     */
    protected function bootShopCustomerSync(): void
    {
        if (!class_exists(\Aero\Shop\Models\Customer::class)) {
            return;
        }

        \Aero\Shop\Models\Customer::extend(function ($model) {
            $model->bindEvent('model.afterCreate', function () use ($model) {
                \Aero\Crm\Classes\ShopCustomerSync::syncContactFromCustomer($model);
            });
        });
    }

    public function registerPermissions(): array
    {
        return [
            'aero.crm.manage_companies' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar empresas',
            ],
            'aero.crm.manage_contacts' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar contactos',
            ],
            'aero.crm.manage_leads' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar leads',
            ],
            'aero.crm.manage_deals' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar pipeline / negocios',
            ],
            'aero.crm.manage_activities' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar actividades',
            ],
            'aero.crm.manage_teams' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar equipos',
            ],
            'aero.crm.manage_tickets' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar tickets',
            ],
            'aero.crm.manage_departments' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar departamentos',
            ],
            'aero.crm.manage_quick_replies' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar respuestas rápidas',
            ],
            'aero.crm.manage_settings' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar configuración del CRM',
            ],
            'aero.crm.manage_collections' => [
                'tab'   => 'CRM',
                'label' => 'Gestionar cobranzas',
            ],
        ];
    }
}
