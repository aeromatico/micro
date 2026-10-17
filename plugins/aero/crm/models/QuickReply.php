<?php namespace Aero\Crm\Models;

use Aero\Crm\Classes\TenantUsers;
use Model;

/**
 * Respuesta rápida: texto reutilizable que se invoca con /atajo (ej. /catalogo).
 * tenant_id NULL = plataforma; con valor = de ese tenant.
 */
class QuickReply extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_crm_quick_replies';

    public $fillable = ['tenant_id', 'area', 'kind', 'config', 'title', 'shortcut', 'body', 'template_name', 'template_language', 'template_category', 'sort_order', 'is_active'];

    public $jsonable = ['config'];

    public $rules = [
        'tenant_id' => 'nullable|exists:aero_sites_tenants,id',
        'area'      => 'required|max:30',
        'title'     => 'required|max:255',
        'shortcut'  => ['required', 'max:40', 'regex:/^[a-z0-9_-]+$/'],
        'kind'      => 'required|in:text,buttons,list,cta_url,location_request,voice_call,location,contact,poll',
        'body'      => 'required_unless:kind,location,contact,poll',
        'template_name'     => ['nullable', 'max:100', 'regex:/^[a-z0-9_]+$/'],
        'template_language' => 'nullable|max:10',
        'template_category' => 'nullable|in:utility,marketing',
        'sort_order' => 'nullable|integer|min:0',
    ];

    public $customMessages = [
        'shortcut.regex' => 'El atajo solo admite minúsculas, números, guion y guion bajo (sin "/" ni espacios).',
    ];

    public $attributes = ['is_active' => true, 'area' => 'general', 'kind' => 'text', 'sort_order' => 10, 'uses_count' => 0];

    public $belongsTo = [
        'tenant' => [\Aero\Sites\Models\Tenant::class],
    ];

    /** Cuentas Hello a las que se limita (vacío = todas). Acoplamiento blando: la clase puede no existir. */
    public $belongsToMany = [
        'accounts' => [
            \Aero\Hello\Models\Account::class,
            'table'    => 'aero_crm_quick_reply_account',
            'key'      => 'quick_reply_id',
            'otherKey' => 'account_id',
        ],
    ];

    public function getAccountsOptions(): array
    {
        if (!class_exists(\Aero\Hello\Models\Account::class)) {
            return [];
        }
        $q = \Aero\Hello\Models\Account::query()->orderBy('label');
        if ($this->tenant_id) {
            $q->forTenant((int) $this->tenant_id);
        }

        return $q->get()->mapWithKeys(fn ($a) => [$a->id => $a->label . ($a->external_username ? ' (' . $a->external_username . ')' : '')])->all();
    }

    /** Respuestas que aplican a una cuenta: las sin restricción y las que la incluyen. */
    public function scopeForAccount($query, ?int $accountId)
    {
        if (!$accountId) {
            return $query;
        }

        return $query->where(function ($q) use ($accountId) {
            $q->whereDoesntHave('accounts')->orWhereHas('accounts', fn ($a) => $a->where('aero_hello_accounts.id', $accountId));
        });
    }

    public static function areaOptions(): array
    {
        return [
            'general'    => 'General',
            'ventas'     => 'Ventas',
            'soporte'    => 'Soporte',
            'cobranzas'  => 'Cobranzas',
            'postventa'  => 'Postventa',
        ];
    }

    public function getTemplateCategoryOptions(): array
    {
        return ['utility' => 'Utility (utilidad)', 'marketing' => 'Marketing'];
    }

    public static function kindOptions(): array
    {
        return [
            'text'             => 'Texto',
            'buttons'          => 'Botones de respuesta (Cloud API)',
            'list'             => 'Menú desplegable (Cloud API)',
            'cta_url'          => 'Botón de enlace (Cloud API)',
            'location_request' => 'Pedir ubicación (Cloud API)',
            'voice_call'       => 'Botón de llamada (Cloud API)',
            'location'         => 'Ubicación (WhatsApp Web)',
            'contact'          => 'Contacto (WhatsApp Web)',
            'poll'             => 'Encuesta (WhatsApp Web)',
        ];
    }

    public function getKindOptions(): array
    {
        return static::kindOptions();
    }

    public function setBodyAttribute($value): void
    {
        $this->attributes['body'] = (string) $value;
    }

    /**
     * Mensaje listo para enviar por una cuenta: ['body' => texto (o respaldo en texto),
     * 'type' => tipo nativo o null, 'payload' => provider_payload o null].
     * Si la cuenta no admite el tipo (o la configuración no valida) sale el texto de respaldo.
     * $vars reemplaza {{clave}} en todos los textos.
     */
    public function resolve($account = null, array $vars = []): array
    {
        $sub = function ($v) use (&$sub, $vars) {
            if (is_array($v)) {
                return array_map($sub, $v);
            }

            return is_string($v) ? preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $v) : $v;
        };

        $body = $sub((string) $this->body);
        $kind = $this->kind ?: 'text';
        $plain = ['body' => $body, 'type' => null, 'payload' => null];

        if ($kind === 'text' || !class_exists(\Aero\Hello\Classes\StructuredMessage::class)) {
            return $plain;
        }

        $c = $sub((array) $this->config);
        $titles = fn ($rows) => array_map(fn ($r) => is_array($r) ? ($r['title'] ?? '') : $r, (array) $rows);
        $rows = (array) ($c['list_rows'] ?? []);

        $input = array_merge($c, [
            'ix_body'           => $body,
            'buttons'           => $titles($c['buttons'] ?? []),
            'list_rows'         => $titles($rows),
            'list_descriptions' => array_map(fn ($r) => is_array($r) ? ($r['description'] ?? '') : '', $rows),
            'poll_options'      => $titles($c['poll_options'] ?? []),
        ]);

        try {
            $built = \Aero\Hello\Classes\StructuredMessage::build($kind, $input);
        } catch (\Throwable $e) {
            return $plain;
        }

        if ($kind === 'location' && !empty($input['location'])) {
            try {
                [$lat, $lng] = \Aero\Hello\Classes\StructuredMessage::parseCoordinates((string) $input['location']);
                $built['fallback'] = '📍 ' . trim(($input['location_name'] ?? '') . " https://www.google.com/maps?q={$lat},{$lng}");
            } catch (\Throwable $e) {
            }
        }

        if ($account && method_exists($account, 'can') && $account->can($kind)) {
            return ['body' => $built['body'], 'type' => $kind, 'payload' => $built['payload']];
        }

        return ['body' => $built['fallback'] ?? $built['body'], 'type' => null, 'payload' => null];
    }

    public function getAreaOptions(): array
    {
        return static::areaOptions();
    }

    /** "/Catalogo " → "catalogo": el usuario puede teclear la barra o mayúsculas. */
    public function setShortcutAttribute($value): void
    {
        $this->attributes['shortcut'] = ltrim(mb_strtolower(trim((string) $value)), '/');
    }

    public function getAreaLabelAttribute(): string
    {
        return static::areaOptions()[$this->area] ?? ucfirst((string) $this->area);
    }

    public function scopeInScope($query, ?int $tenantId)
    {
        return $tenantId ? $query->where('tenant_id', $tenantId) : $query->whereNull('tenant_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function beforeValidate(): void
    {
        $this->rules['shortcut'][] = \Illuminate\Validation\Rule::unique($this->table, 'shortcut')
            ->where(fn ($q) => $this->tenant_id ? $q->where('tenant_id', $this->tenant_id) : $q->whereNull('tenant_id'))
            ->ignore($this->id);
    }
}
