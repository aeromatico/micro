<?php namespace Aero\Shortcuts\Models;

use Backend;
use BackendMenu;
use Model;

class Item extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_shortcuts_items';

    public $fillable = ['tenant_id', 'label', 'kind', 'target', 'url', 'new_tab', 'icon', 'color', 'sort_order', 'is_active'];

    public $rules = [
        'label' => 'required|max:60',
        'kind'  => 'required|in:list,form,url',
    ];

    protected $casts = ['is_active' => 'boolean', 'new_tab' => 'boolean'];

    public function beforeValidate(): void
    {
        if ($this->kind === 'url') {
            // https://... o una ruta relativa al sitio (/backend/aero/hello/compose); nunca //host ni javascript:
            $this->rules['url'] = ['required', 'max:500', 'regex:/^(https?:\/\/\S+|\/(?!\/)\S*)$/i'];
        } else {
            $this->rules['target'] = 'required';
        }
    }

    public function beforeCreate(): void
    {
        if (!$this->sort_order) {
            $this->sort_order = (int) static::where('tenant_id', $this->tenant_id)->max('sort_order') + 1;
        }
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Destino final: ruta del backend (lista o /create del formulario) o la URL externa. */
    public function getHrefAttribute(): string
    {
        return match ($this->kind) {
            'url'   => (string) $this->url,
            'form'  => Backend::url(trim((string) $this->target, '/') . '/create'),
            default => Backend::url(trim((string) $this->target, '/')),
        };
    }

    public function getOpensNewTabAttribute(): bool
    {
        return $this->kind === 'url' && ($this->new_tab ?? true);
    }

    public function getKindOptions(): array
    {
        return ['list' => 'Lista', 'form' => 'Formulario (nuevo registro)', 'url' => 'URL personalizada (pestaña nueva)'];
    }

    /** Pantallas del menú a las que el usuario tiene acceso (ya filtradas por permisos). */
    public function getTargetOptions(): array
    {
        $base = rtrim(Backend::url(''), '/') . '/';
        $options = [];
        foreach (BackendMenu::listMainMenuItemsWithSubitems() as $info) {
            if ($info->mainMenuItem->owner === 'October.Dashboard') {
                continue;
            }
            $group = __($info->mainMenuItem->label);
            $plugin = ucfirst(strtolower(substr(strrchr('.' . $info->mainMenuItem->owner, '.'), 1)));
            if ($plugin !== '' && stripos($group, $plugin) === false) {
                $group .= " ({$plugin})";
            }
            foreach ($info->subMenuItems as $sub) {
                if (!$sub->url) {
                    continue;
                }
                $path = trim(str_starts_with($sub->url, $base) ? substr($sub->url, strlen($base)) : $sub->url, '/');
                // Varios menús pueden apuntar a la misma pantalla: gana el del plugin dueño
                // de la ruta (aero/shop/... → Shop), no el que la enlaza desde otro lado.
                $label = $group . ' › ' . __($sub->label);
                $isOwner = strtolower($info->mainMenuItem->owner) === 'aero.' . (explode('/', $path)[1] ?? '');
                if (!isset($options[$path]) || $isOwner) {
                    $options[$path] = $label;
                }
            }
        }
        asort($options);

        return $options;
    }

    public function getIconOptions(): array
    {
        $icons = [
            'icon-star' => 'Estrella', 'icon-plus' => 'Más', 'icon-list-ul' => 'Lista', 'icon-file-text-o' => 'Documento',
            'icon-envelope' => 'Correo', 'icon-comments' => 'Chat', 'icon-phone' => 'Teléfono', 'icon-calendar' => 'Calendario',
            'icon-users' => 'Personas', 'icon-user' => 'Persona', 'icon-shopping-bag' => 'Tienda', 'icon-credit-card' => 'Pagos',
            'icon-money' => 'Dinero', 'icon-bar-chart' => 'Gráfico', 'icon-globe' => 'Web', 'icon-cog' => 'Ajustes',
            'icon-book' => 'Libro', 'icon-life-ring' => 'Ayuda', 'icon-bolt' => 'Rayo', 'icon-paper-plane' => 'Enviar',
            'icon-bell' => 'Aviso', 'icon-image' => 'Imagen', 'icon-qrcode' => 'QR', 'icon-bullhorn' => 'Campaña',
            'icon-pencil' => 'Editar', 'icon-briefcase' => 'Negocios', 'icon-commenting' => 'Mensaje', 'icon-android' => 'Bot',
            'icon-calculator' => 'Caja / POS',
        ];

        return $icons;
    }
}
