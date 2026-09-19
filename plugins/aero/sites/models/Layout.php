<?php namespace Aero\Sites\Models;

use Model;

/**
 * "Plantilla" del tenant: el layout que envuelve todas sus páginas.
 *
 * modo 'default' — usa el shell del theme (themes/microsites/layouts/base.htm)
 * con las dependencias por defecto (Tailwind/Alpine servidos desde el propio
 * dominio), pero header_html/footer_html permiten reemplazar el navbar/footer
 * armado por el theme (partials/site/header.htm y footer.htm) sin tocar el
 * resto del documento. extra_head_html se imprime tal cual justo antes de
 * </head> (después de nuestro CSS/JS de stock): sirve para sumar una
 * dependencia propia (un <link>/<script> adicional) sin renunciar al resto
 * del stock ni pasar a modo 'custom'.
 *
 * modo 'custom' — el tenant pega su propio documento HTML completo (head,
 * dependencias CDN propias, header/footer si quiere, scripts) en custom_html.
 * base.htm reemplaza dos placeholders literales en ese HTML: <!-- AERO:CONTENT -->
 * por el contenido de la página y <!-- AERO:SCRIPTS --> por los assets que
 * October necesita para los formularios AJAX (data-request) del sitio —
 * carrito, contacto, etc. Sin el segundo placeholder esos formularios quedan
 * inertes, mismo trade-off que Page.content_mode='code' ya acepta hoy: HTML
 * de confianza del propio tenant, renderizado tal cual (| raw), mismo nivel
 * de riesgo que ya existe en Page::content.
 */
class Layout extends Model
{
    use \October\Rain\Database\Traits\Validation;

    public $table = 'aero_sites_layouts';

    public $fillable = ['tenant_id', 'mode', 'show_header', 'show_footer', 'header_html', 'footer_html', 'extra_head_html', 'custom_html'];

    public $rules = [
        'tenant_id' => 'required|exists:aero_sites_tenants,id|unique:aero_sites_layouts,tenant_id',
        'mode'      => 'in:default,custom',
    ];

    public $belongsTo = [
        'tenant' => [Tenant::class],
    ];

    public function getIsCustomAttribute(): bool
    {
        return $this->mode === 'custom' && trim((string) $this->custom_html) !== '';
    }
}
