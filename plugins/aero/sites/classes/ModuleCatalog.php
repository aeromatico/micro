<?php namespace Aero\Sites\Classes;

use Aero\Sites\Models\Plan;
use Aero\Sites\Models\Tenant;
use System\Classes\PluginManager;

/**
 * Catálogo de módulos que un tenant puede encender o apagar («Mis módulos» y
 * la bienvenida de primera vez). La lista sale de Plan::manageablePlugins()
 * (lo que el rol tenant_admin puede usar, sin Sites ni lo siempre permitido);
 * nombre/descr./ícono amigables, grupo, rubros y dependencias salen de META.
 * Un plugin nuevo sin entrada en META cae en «Otros» y apagado por defecto.
 */
class ModuleCatalog
{
    public const GROUPS = [
        'sales'     => 'Ventas y cobros',
        'clients'   => 'Clientes y mensajes',
        'marketing' => 'Marketing y redes',
        'niche'     => 'Herramientas por rubro',
        'automate'  => 'Automatización e integraciones',
        'other'     => 'Otros',
    ];

    /**
     * code => [group, name?, desc?, essential?, niches?, requires?]
     * `niches` usa las claves de NicheManager::options().
     */
    public const META = [
        // Ventas y cobros
        'Aero.Shop'      => ['group' => 'sales', 'name' => 'Tienda en línea', 'desc' => 'Catálogo, inventario y pedidos.',
                             'niches' => ['tienda_online', 'tienda_whatsapp', 'restaurante', 'deportes', 'libreria', 'farmacia', 'hogar', 'electronicos', 'celulares', 'mercados_abarrotes']],
        'Aero.Pos'       => ['group' => 'sales', 'name' => 'Punto de venta', 'desc' => 'Caja para mostrador y mesas, turnos y cobros.',
                             'niches' => ['restaurante', 'mercados_abarrotes', 'farmacia'], 'requires' => ['Aero.Shop']],
        'Aero.Pay'       => ['group' => 'sales', 'name' => 'Cobros con QR', 'desc' => 'Recibe pagos con QR de cualquier banco.', 'essential' => true],
        'Aero.Invoicing' => ['group' => 'sales', 'name' => 'Facturación', 'desc' => 'Facturas electrónicas válidas ante impuestos.'],
        'Aero.Finance'   => ['group' => 'sales', 'name' => 'Finanzas', 'desc' => 'Ingresos, egresos y balances.'],
        'Aero.Tickets'   => ['group' => 'sales', 'name' => 'Entradas y eventos', 'desc' => 'Vende entradas con QR para tus eventos.', 'requires' => ['Aero.Pay']],
        'Aero.Shopify'   => ['group' => 'sales', 'name' => 'Shopify', 'desc' => 'Cobra con QR los pedidos de tu tienda Shopify.', 'requires' => ['Aero.Pay']],

        // Clientes y mensajes
        'Aero.Crm'       => ['group' => 'clients', 'name' => 'Clientes (CRM)', 'desc' => 'Contactos, oportunidades y seguimiento.', 'essential' => true],
        'Aero.Hello'     => ['group' => 'clients', 'name' => 'Mensajería', 'desc' => 'WhatsApp y otros canales en una sola bandeja.', 'essential' => true],
        'Aero.Notify'    => ['group' => 'clients', 'name' => 'Avisos automáticos', 'desc' => 'Confirmaciones y recordatorios a tus clientes.', 'essential' => true],
        'Aero.Chat'      => ['group' => 'clients', 'name' => 'App de chat', 'desc' => 'Atiende mensajes desde el celular.', 'requires' => ['Aero.Hello']],
        'Aero.Chatbots'  => ['group' => 'clients', 'name' => 'Chatbots', 'desc' => 'Respuestas automáticas a tus clientes.', 'requires' => ['Aero.Hello']],
        'Aero.Livechat'  => ['group' => 'clients', 'name' => 'Chat en tu web', 'desc' => 'Burbuja de chat en vivo para tu sitio.'],
        'Aero.Sms'       => ['group' => 'clients', 'name' => 'SMS', 'desc' => 'Mensajes de texto simples y masivos.'],

        // Marketing
        'Aero.Social'    => ['group' => 'marketing', 'name' => 'Redes sociales', 'desc' => 'Publica y programa posts en tus redes.',
                             'niches' => ['servicios_digitales', 'radioemisora']],
        'Aero.Youtube'   => ['group' => 'marketing', 'name' => 'YouTube', 'desc' => 'Sube y gestiona tus videos.'],
        'Aero.Producer'  => ['group' => 'marketing', 'name' => 'Videos con IA', 'desc' => 'Guion, voz y video listos para redes.'],

        // Rubros
        'Aero.Gym'        => ['group' => 'niche', 'name' => 'Gimnasio', 'desc' => 'Socios, membresías, acceso por QR y clases.', 'niches' => ['gimnasios']],
        'Aero.Clinica'    => ['group' => 'niche', 'name' => 'Clínica', 'desc' => 'Pacientes, consultas e historia clínica.', 'niches' => ['consultorio']],
        'Aero.Medical'    => ['group' => 'niche', 'name' => 'Veterinaria', 'desc' => 'Historia clínica para mascotas y consultorios.', 'niches' => ['veterinaria']],
        'Aero.Office'     => ['group' => 'niche', 'name' => 'Reservas y citas', 'desc' => 'Agenda de profesionales y reservas en línea.',
                              'niches' => ['consultorio', 'veterinaria', 'peluqueria', 'limpieza_lavanderia']],
        'Aero.Realestate' => ['group' => 'niche', 'name' => 'Inmobiliaria', 'desc' => 'Propiedades, propietarios y clientes.', 'niches' => ['inmuebles']],
        'Aero.School'     => ['group' => 'niche', 'name' => 'Colegio', 'desc' => 'Estudiantes, pensiones, asistencia y boletines.'],
        'Aero.Church'     => ['group' => 'niche', 'name' => 'Iglesia', 'desc' => 'Miembros, ministerios, eventos y aportes.'],

        // Automatización e integraciones
        'Aero.Workflows'  => ['group' => 'automate', 'name' => 'Automatizaciones', 'desc' => 'Flujos que trabajan solos.', 'niches' => ['servicios_digitales']],
        'Aero.Workspaces' => ['group' => 'automate', 'name' => 'Equipo con IA', 'desc' => 'Agentes de IA que hacen tareas por ti.'],
        'Aero.Tracking'   => ['group' => 'automate', 'name' => 'Seguimiento GPS', 'desc' => 'Ubica en tiempo real flotas y trabajos.', 'niches' => ['transporte']],
        'Aero.Sheets'     => ['group' => 'automate', 'name' => 'Google Sheets', 'desc' => 'Importa y exporta datos a hojas de cálculo.'],
        'Aero.Api'        => ['group' => 'automate', 'name' => 'API', 'desc' => 'Conecta tus propios sistemas.'],
        'Aero.Oauth'      => ['group' => 'automate', 'name' => 'Cuentas conectadas', 'desc' => 'Conecta tu cuenta de Google y otras.'],
        'Aero.WpFlash'    => ['group' => 'automate', 'name' => 'WordPress', 'desc' => 'Tu sitio en WordPress administrado por nosotros.'],

        // Otros
        'Aero.Plan'       => ['group' => 'other', 'name' => 'Plan de 7 días', 'desc' => 'Te guiamos paso a paso para arrancar.', 'essential' => true],
        'Aero.Rgpd'       => ['group' => 'other', 'name' => 'Aviso de cookies', 'desc' => 'Consentimiento de cookies en tu sitio.'],
    ];

    /** Solo para tests: fija la lista de módulos sin leer roles ni plugins. */
    public static ?array $codesForTesting = null;

    /** Códigos de plugin que el tenant puede encender/apagar. */
    public static function codes(): array
    {
        return static::$codesForTesting ?? Plan::manageablePlugins();
    }

    public static function isToggleable(string $code): bool
    {
        return in_array($code, static::codes(), true);
    }

    /**
     * Módulos con su estado para el tenant, agrupados:
     * [groupKey => ['label' => .., 'items' => [code => [...]]]]
     */
    public static function forTenant(Tenant $tenant, ?array $selection = null): array
    {
        $manager = PluginManager::instance();
        $suggested = static::defaultsFor($tenant);
        $groups = [];

        foreach (static::codes() as $code) {
            $meta = static::META[$code] ?? [];
            $plugin = $manager->findByIdentifier($code);
            $details = $plugin ? $plugin->pluginDetails() : [];
            $allowed = $tenant->pluginAllowedByPlan($code);

            $item = [
                'code'      => $code,
                'name'      => $meta['name'] ?? trans($details['name'] ?? $code),
                'desc'      => $meta['desc'] ?? trans($details['description'] ?? ''),
                'icon'      => $details['icon'] ?? 'icon-cube',
                'locked'    => !$allowed,
                'enabled'   => $selection === null ? $tenant->pluginEnabled($code) : ($allowed && in_array($code, $selection, true)),
                'essential' => !empty($meta['essential']),
                'suggested' => in_array($code, $suggested, true),
                'requires'  => array_values(array_filter((array) ($meta['requires'] ?? []), [static::class, 'isToggleable'])),
                'upgrade'   => $allowed ? null : static::planThatIncludes($code, $tenant),
            ];

            $group = isset(static::GROUPS[$meta['group'] ?? '']) ? $meta['group'] : 'other';
            $groups[$group]['label'] = static::GROUPS[$group];
            $groups[$group]['items'][$code] = $item;
        }

        // Orden de GROUPS; dentro de cada grupo, disponibles primero.
        $out = [];
        foreach (array_keys(static::GROUPS) as $key) {
            if (!isset($groups[$key])) {
                continue;
            }
            uasort($groups[$key]['items'], fn ($a, $b) => [$a['locked'], $a['name']] <=> [$b['locked'], $b['name']]);
            $out[$key] = $groups[$key];
        }

        return $out;
    }

    /** Preselección de la bienvenida: esenciales + los del rubro, dentro del plan. */
    public static function defaultsFor(Tenant $tenant): array
    {
        $out = [];
        foreach (static::codes() as $code) {
            $meta = static::META[$code] ?? [];
            if (!empty($meta['essential']) || in_array($tenant->niche_type, (array) ($meta['niches'] ?? []), true)) {
                $out[] = $code;
            }
        }

        $out = static::withRequirements($out);

        return array_values(array_filter($out, fn ($c) => $tenant->pluginAllowedByPlan($c)));
    }

    /** Suma las dependencias (transitivas) de una lista de módulos. */
    public static function withRequirements(array $codes): array
    {
        $queue = $codes;
        $set = array_fill_keys($codes, true);

        while ($queue) {
            $code = array_shift($queue);
            foreach ((array) (static::META[$code]['requires'] ?? []) as $req) {
                if (!isset($set[$req]) && static::isToggleable($req)) {
                    $set[$req] = true;
                    $queue[] = $req;
                }
            }
        }

        return array_keys($set);
    }

    /** Módulos que dependen (directa o indirectamente) de $code. */
    public static function dependentsOf(string $code): array
    {
        $out = [];
        foreach (static::codes() as $other) {
            if ($other !== $code && in_array($code, static::withRequirements([$other]), true)) {
                $out[] = $other;
            }
        }

        return $out;
    }

    /**
     * Enciende o apaga un módulo respetando plan y dependencias.
     * Devuelve los códigos cuyo estado cambió (incluye arrastrados).
     */
    public static function toggle(Tenant $tenant, string $code, bool $enable): array
    {
        if (!static::isToggleable($code)) {
            throw new \ApplicationException('Ese módulo no se puede cambiar.');
        }

        $disabled = (array) $tenant->disabled_plugins;

        if ($enable) {
            if (!$tenant->pluginAllowedByPlan($code)) {
                throw new \ApplicationException('Tu plan no incluye este módulo. Mejora tu plan para usarlo.');
            }
            $targets = array_filter(static::withRequirements([$code]), fn ($c) => $tenant->pluginAllowedByPlan($c));
            $changed = array_values(array_intersect($targets, $disabled));
            $disabled = array_diff($disabled, $targets);
        } else {
            $targets = array_merge([$code], static::dependentsOf($code));
            $changed = array_values(array_filter($targets, fn ($c) => $tenant->pluginEnabled($c)));
            $disabled = array_merge($disabled, $targets);
        }

        $tenant->saveDisabledPlugins($disabled);

        return $changed;
    }

    /** Guarda la elección de la bienvenida: apaga todo lo no elegido. */
    public static function applySelection(Tenant $tenant, array $selected): void
    {
        $selected = static::withRequirements(array_values(array_filter($selected, [static::class, 'isToggleable'])));
        $disabled = array_values(array_diff(static::codes(), $selected));

        $tenant->saveDisabledPlugins($disabled, true);
    }

    public static function displayName(string $code): string
    {
        if (isset(static::META[$code]['name'])) {
            return static::META[$code]['name'];
        }
        $plugin = PluginManager::instance()->findByIdentifier($code);

        return $plugin ? trans($plugin->pluginDetails()['name'] ?? $code) : $code;
    }

    /** Nombre del plan público más barato que incluye el módulo (para «Mejorar plan»). */
    protected static function planThatIncludes(string $code, Tenant $tenant): ?string
    {
        static $plans = null;
        $plans ??= Plan::where('is_active', true)->where('price', '>', 0)->orderBy('price')->get();

        foreach ($plans as $plan) {
            if ($plan->id !== $tenant->plan_id && $plan->plugins !== null && $plan->allowsPlugin($code)) {
                return $plan->name;
            }
        }

        return null;
    }
}
