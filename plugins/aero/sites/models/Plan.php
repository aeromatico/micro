<?php namespace Aero\Sites\Models;

use Aero\Sites\Classes\ProFeatures;
use Model;
use System\Classes\PluginManager;

/**
 * Plan de plataforma. `code` es el valor que guarda Tenant.plan.
 *  - credits:  [{credit_type: 'azul', amount: 500, mode: 'accumulate'|'expiring'}] — se otorga al
 *              activar el plan (una vez por tenant+plan). `mode` distingue créditos acumulables
 *              (suman al saldo fungible, no vencen) de vencibles (se pierden al renovar si no se
 *              usan) — hoy solo se guarda: el motor de renovación que le da efecto aún no existe.
 *  - plugins:  códigos de plugin ('Aero.Hello') accesibles con este plan; null = sin restricción.
 *  - features: [{text}] — lista libre que se muestra en /alta y en la invitación a mejorar.
 *  - is_pro:   el admin del tenant recibe el rol tenant_admin_pro y puede usar dominio propio.
 *  - price_renewal / price_renewal_annual: precio de renovación si difiere del de alta (price /
 *    price_annual); null = mismo precio. Sin efecto todavía (no hay cobro recurrente).
 */
class Plan extends Model
{
    use \October\Rain\Database\Traits\Validation;
    use \October\Rain\Database\Traits\Sortable;

    public $table = 'aero_sites_plans';

    public $fillable = [
        'code', 'name', 'description', 'price', 'price_renewal', 'price_annual', 'price_renewal_annual',
        'trial_days', 'is_active', 'is_featured', 'is_pro', 'sort_order', 'credits', 'plugins', 'features',
    ];

    public $hasMany = [
        'tenants' => [Tenant::class, 'key' => 'plan_id'],
    ];

    public $jsonable = ['credits', 'plugins', 'features'];

    public $rules = [
        'code'  => 'required|alpha_dash|max:40|unique:aero_sites_plans,code',
        'name'  => 'required',
        'price' => 'required|numeric|min:0',
        'price_renewal' => 'nullable|numeric|min:0',
        'price_annual' => 'nullable|numeric|min:0',
        'price_renewal_annual' => 'nullable|numeric|min:0',
        'trial_days' => 'nullable|integer|min:0',
    ];

    protected $casts = [
        'price'       => 'float',
        'trial_days'  => 'integer',
        'is_active'   => 'boolean',
        'is_featured' => 'boolean',
        'is_pro'      => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function findByCode(?string $code): ?self
    {
        return $code ? static::where('code', $code)->first() : null;
    }

    public const PERIODS = ['trial', 'monthly', 'annual'];

    /**
     * Plugins que ningún plan puede quitar: la propia plataforma, la billetera
     * para poder pagar/recargar, y el catálogo para comprar servicios extra
     * (Aero.Services) — bloquearlo por plan no tendría sentido: es justamente
     * el lugar donde el tenant paga por más cosas, no una función premium.
     */
    public const ALWAYS_ALLOWED = ['Aero.Sites', 'Aero.Credits', 'Aero.Docs', 'Aero.Services'];

    /** Periodos que este plan ofrece: ['trial' => 0, 'monthly' => 49.0, 'annual' => 490.0]. */
    public function periods(): array
    {
        $out = [];

        if ($this->trial_days > 0) {
            $out['trial'] = 0.0;
        }

        $out['monthly'] = (float) $this->price;

        if ($this->price_annual !== null && (float) $this->price_annual > 0) {
            $out['annual'] = (float) $this->price_annual;
        }

        return $out;
    }

    public function beforeValidate()
    {
        foreach (['price_annual', 'price_renewal', 'price_renewal_annual'] as $field) {
            if ($this->$field === '') {
                $this->$field = null;
            }
        }
        $this->trial_days = (int) $this->trial_days;
    }

    /** Precio de renovación del periodo, o el de alta si no se definió uno propio. */
    public function renewalPrice(string $period): float
    {
        $value = $period === 'annual'
            ? ($this->price_renewal_annual ?? $this->price_annual)
            : ($this->price_renewal ?? $this->price);

        return (float) $value;
    }

    public function beforeDelete()
    {
        if ($n = $this->tenants()->count()) {
            throw new \ApplicationException("No se puede eliminar: {$n} tenant(s) usan este plan. Desactívalo o muévelos a otro plan primero.");
        }
    }

    public const CREDIT_MODES = ['accumulate', 'expiring'];

    public function beforeSave()
    {
        // Un solo monto por color (gana el último) y sin montos vacíos.
        $byType = [];
        foreach ((array) $this->credits as $row) {
            $amount = (int) ($row['amount'] ?? 0);
            if (!empty($row['credit_type']) && $amount > 0) {
                $mode = in_array($row['mode'] ?? null, static::CREDIT_MODES, true) ? $row['mode'] : 'accumulate';
                $byType[$row['credit_type']] = ['credit_type' => $row['credit_type'], 'amount' => $amount, 'mode' => $mode];
            }
        }
        $this->credits = array_values($byType);
    }

    /** ['azul' => 500, ...] */
    public function creditsByType(): array
    {
        return collect((array) $this->credits)->pluck('amount', 'credit_type')->map(fn ($n) => (int) $n)->all();
    }

    /** Filas completas [{credit_type, amount, mode}], para cuando el motor de renovación exista. */
    public function creditsConfig(): array
    {
        return collect((array) $this->credits)->map(fn ($row) => [
            'credit_type' => $row['credit_type'] ?? null,
            'amount' => (int) ($row['amount'] ?? 0),
            'mode' => in_array($row['mode'] ?? null, static::CREDIT_MODES, true) ? $row['mode'] : 'accumulate',
        ])->all();
    }

    /** Lista de textos de características, sin filas vacías. */
    public function featureList(): array
    {
        return array_values(array_filter(array_map(fn ($r) => trim((string) ($r['text'] ?? '')), (array) $this->features)));
    }

    /** ¿Incluye este plan el plugin? (null = sin restricción) */
    public function allowsPlugin(string $pluginCode): bool
    {
        if ($this->plugins === null) {
            return true;
        }

        return in_array($pluginCode, (array) $this->plugins, true);
    }

    // ---- opciones de formulario ----

    public function getCreditTypeOptions(): array
    {
        if (!class_exists(\Aero\Credits\Models\CreditType::class)) {
            return [];
        }

        return \Aero\Credits\Models\CreditType::active()->pluck('label', 'code')->all();
    }

    /** Plugins que puede activar un plan, con su nombre legible. */
    public function getPluginsOptions(): array
    {
        $manager = PluginManager::instance();
        $out = [];

        foreach (static::manageablePlugins() as $code) {
            $plugin = $manager->findByIdentifier($code);
            $name = $plugin ? trans($plugin->pluginDetails()['name'] ?? $code) : $code;
            $out[$code] = $name === $code ? $code : "{$name} ({$code})";
        }

        return $out;
    }

    /**
     * Plugins que un plan puede incluir o excluir: los que el rol tenant_admin
     * tiene permitidos (aero.<plugin>.<permiso>), menos Sites. Se calcula desde
     * los permisos del rol y NO desde el menú de quien mira: algunos plugins
     * (Hello) recortan su menú para el superadmin y desaparecerían del
     * formulario, y guardar el plan los descartaba en silencio.
     */
    public static function manageablePlugins(): array
    {
        static $cache = null;

        if ($cache !== null) {
            return $cache;
        }

        $manager = PluginManager::instance();
        $granted = \Backend\Models\UserRole::where('code', ProFeatures::ROLE_REGULAR)->first()->permissions ?? [];
        $codes = [];

        foreach ($granted as $permission => $allowed) {
            if (!$allowed || substr_count($permission, '.') < 2) {
                continue;
            }

            [$vendor, $name] = explode('.', $permission, 3);
            $plugin = $manager->findByIdentifier("{$vendor}.{$name}");

            if ($plugin && !in_array($manager->getIdentifier($plugin), static::ALWAYS_ALLOWED, true)) {
                $codes[$manager->getIdentifier($plugin)] = true;
            }
        }

        // Plugins cuyo menú no exige permisos (ej. Chat) no aparecen en el rol:
        // se suman desde el menú visible.
        foreach (ProFeatures::catalog() as $item) {
            if (!in_array($item['plugin'] ?? 'Aero.Sites', static::ALWAYS_ALLOWED, true)) {
                $codes[$item['plugin']] = true;
            }
        }

        $codes = array_keys($codes);
        sort($codes);

        return $cache = $codes;
    }
}
